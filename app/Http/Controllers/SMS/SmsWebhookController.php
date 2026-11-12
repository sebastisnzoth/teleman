<?php

namespace App\Http\Controllers\SMS;

use App\Enums\TelephonyProvider;
use App\Events\SmsMessageReceived;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateAISmsResponseJob;
use App\Models\PhoneNumber;
use App\Models\SmsConversation;
use App\Models\SmsMessage;
use App\Services\CreditService;
use App\Services\PricingService;
use App\Services\SMS\TwilioSMSProvider;
use App\Services\Telephony\Plivo\PlivoNumberFormat;
use App\Services\Telephony\TelephonyManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SmsWebhookController extends Controller
{
    /**
     * Handle incoming SMS webhook from provider.
     *
     * $provider is read from the route's own 'provider' parameter
     * (sms/webhook/{provider}/incoming for Twilio — a real URI segment;
     * sms/webhook/plivo/incoming sets it via ->defaults() instead)
     * rather than read from the installation's current lock — the same
     * reasoning TwimlController::inboundCall() documents. It is
     * deliberately not a typed method parameter: with two class
     * dependencies (Request, TelephonyManager) ahead of it, Laravel's
     * controller-dependency resolution splices them into the
     * route-parameter list positionally
     * (ResolvesRouteDependencies::resolveMethodDependencies()) and
     * mis-assigns a scalar default sitting after them — reading it from
     * the route explicitly sidesteps that.
     *
     * A Plivo payload is normalised straight through
     * TelephonyManager::webhookNormalizer() rather than through
     * SmsProviderFactory/TelephonyManager::sms(): which parser applies
     * here must be driven by which webhook URL was hit (this route, vs.
     * the Twilio-signed sibling below), not by the installation's current
     * lock — the exact reasoning TelephonyManager::webhookNormalizer()'s
     * own docblock gives for taking an explicit provider argument instead
     * of asking the lock. normalizeIncomingSms()'s output
     * (message_id/from/to/body/media_urls) already matches the fields
     * this method needs below. Below, the check is specifically "===
     * 'plivo'", not "!== 'twilio'": the {provider} segment on the Twilio
     * route is a wildcard Twilio itself does not control, and
     * twilio.signature already guarantees only a genuinely Twilio-signed
     * request reaches that branch regardless of what the segment holds,
     * so anything other than the literal 'plivo' should still get
     * Twilio's own parser and its real, provider-reported segment count.
     *
     * For the same reason, the Twilio branch below builds its own
     * TwilioSMSProvider directly instead of going through
     * SmsProviderFactory::makeForUser(), which is now lock-driven (it
     * resolves whichever provider the installation is actually locked
     * to, for the send path) — using it here would parse a
     * Twilio-signed payload with a Plivo parser on the (currently
     * unreachable, since a Plivo lock has no Twilio credentials to sign
     * with — see ResetTelephonyProviderAction) installation that is
     * locked to Plivo but still receiving traffic on this route.
     * initializeForUser() throwing when neither a user-specific nor a
     * global Twilio credential exists is unchanged from before this
     * method routed anything through the abstraction.
     */
    public function handleIncoming(Request $request, TelephonyManager $telephony)
    {
        $provider = $request->route('provider', 'twilio');

        try {
            // Get all request data
            $payload = $request->all();

            Log::info('SMS Webhook Received', [
                'provider' => $provider,
                'payload' => $payload,
            ]);

            // Find which SMS phone number received the message
            $toNumber = $payload['To'] ?? null;

            if (! $toNumber) {
                Log::error('SMS webhook missing To number', ['payload' => $payload]);

                return response($this->twilioResponse(), 200)->header('Content-Type', 'text/xml');
            }

            // Plivo's real webhook sends To/From bare, with no leading
            // '+', while every PhoneNumber row this app stores carries
            // one (PhoneNumberService normalises a Plivo number to E.164
            // the moment it enters the app — see PlivoNumberFormat's own
            // docblock). Left unnormalised, this lookup could never match
            // a real Plivo payload against a real customer's number, and
            // an inbound message to a number they had paid for would
            // silently fail to route. A no-op for Twilio, whose own
            // webhook already sends E.164 with the '+'.
            if ($provider === 'plivo') {
                $toNumber = PlivoNumberFormat::toE164($toNumber);
            }

            $phoneNumber = PhoneNumber::where('number', $toNumber)
                ->where('status', 'assigned')
                ->whereRaw("JSON_EXTRACT(capabilities, '$.sms') = true")
                ->first();

            if (! $phoneNumber) {
                Log::warning('SMS received for unregistered number', ['number' => $toNumber]);

                return response($this->twilioResponse(), 200)->header('Content-Type', 'text/xml');
            }

            // Check if AI agent is assigned
            if (! $phoneNumber->ai_agent_id) {
                Log::warning('SMS received but no AI agent assigned', [
                    'phone_number_id' => $phoneNumber->id,
                    'number' => $toNumber,
                ]);

                return response($this->twilioResponse(), 200)->header('Content-Type', 'text/xml');
            }

            // Parse incoming message. Twilio's own path is untouched — a
            // fresh, uninitialized-until-now TwilioSMSProvider resolved
            // for $phoneNumber->user exactly as SmsProviderFactory used
            // to do internally when it only ever supported Twilio — and
            // is also the default for anything that isn't specifically
            // 'plivo' — not just anything that IS 'twilio'. This route's
            // {provider} segment is a wildcard Twilio itself does not
            // control (it just posts to whatever URL is configured), and
            // twilio.signature already guarantees that only a genuinely
            // Twilio-signed request ever reaches this branch regardless
            // of what that segment contains — so an unexpected value here
            // (a stale config, a typo) must still get Twilio's own
            // parser, which reads the real provider-reported segment
            // count, rather than normalizeIncomingSms()'s shape, which
            // has no segment count at all and would silently under-bill
            // on a multi-segment message. Plivo's own route always sets
            // this segment to the literal 'plivo' via ->defaults(), never
            // anything else.
            $messageData = $provider === 'plivo'
                ? $telephony->webhookNormalizer(TelephonyProvider::PLIVO)->normalizeIncomingSms($payload)
                : (new TwilioSMSProvider)->initializeForUser($phoneNumber->user)->parseIncoming($payload);

            // Find or create conversation
            $conversation = SmsConversation::firstOrCreate(
                [
                    'phone_number_id' => $phoneNumber->id,
                    'contact_phone' => $messageData['from'],
                ],
                [
                    'ai_agent_id' => $phoneNumber->ai_agent_id,
                    'status' => 'active',
                    'last_message_at' => now(),
                ]
            );

            // Update conversation timestamp
            $conversation->updateLastMessageTime();

            // Store incoming message
            $message = SmsMessage::create([
                'conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'message_body' => $messageData['body'],
                'sender_phone' => $messageData['from'],
                'receiver_phone' => $messageData['to'],
                'status' => 'received',
                'ai_generated' => false,
                'provider' => $provider,
                'provider_message_id' => $messageData['message_id'],
                'num_segments' => $messageData['num_segments'] ?? 1,
                'metadata' => [
                    'media_urls' => $messageData['media_urls'] ?? [],
                ],
            ]);

            // Broadcast to frontend (real-time)
            broadcast(new SmsMessageReceived($message, $conversation))->toOthers();

            // Trigger AI response (async job)
            if ($phoneNumber->aiAgent) {
                GenerateAISmsResponseJob::dispatch(
                    $conversation,
                    $message,
                    $phoneNumber->aiAgent,
                    $phoneNumber
                );
            }

            // Return 200 OK (Twilio expects this)
            return response($this->twilioResponse(), 200)->header('Content-Type', 'text/xml');

        } catch (\Exception $e) {
            Log::error('SMS webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // 200, not 500: both providers retry a non-2xx response to
            // this endpoint, and nothing here is idempotent on a repeat
            // delivery (unlike the call webhooks, which key off
            // ProcessedWebhook) — a retry storm into a handler that
            // already failed once would just fail the same way again,
            // or worse, partially succeed twice. Acknowledge and rely on
            // the log line above to surface the failure.
            return response($this->twilioResponse(), 200)->header('Content-Type', 'text/xml');
        }
    }

    /**
     * Handle message status updates (delivery, failed, etc.)
     *
     * Field names differ by provider, unlike the shared 'OK'/'Error' text
     * response both are happy with: Twilio posts MessageSid/SmsSid,
     * MessageStatus/SmsStatus, Price and ErrorMessage; Plivo posts
     * MessageUUID, Status, TotalAmount and ErrorCode (see the installed
     * SDK's own MessageInterface::create() docblock for the 'url'
     * parameter's documented callback fields).
     * extractStatusFields() is the one place that difference is read;
     * everything below it works on the normalised shape regardless of
     * which provider called back.
     *
     * The status itself is translated too, by mapMessageStatus(). Both
     * providers' vocabularies are wider than the `sms_messages.status`
     * column's — Twilio alone documents accepted, scheduled, sending,
     * read and canceled on top of it — and this handler used to write
     * whatever arrived straight in. See mapMessageStatus() for what that
     * cost a Twilio installation.
     *
     * $provider is read from the route — a URL segment on the Twilio
     * group, a ->defaults() value on the Plivo one — and not declared as
     * a typed scalar parameter, which is what handleIncoming() and
     * CallController's three webhook handlers all do. Laravel's
     * controller-dependency resolution splices class dependencies into
     * the route-parameter list positionally
     * (ResolvesRouteDependencies::resolveMethodDependencies()), so a
     * scalar default sitting after one can be mis-bound; reading it from
     * the route sidesteps that whatever this signature grows later.
     */
    public function handleStatus(Request $request)
    {
        try {
            $provider = $request->route('provider', 'twilio');
            $payload = $request->all();

            Log::info('SMS Status Webhook Received', [
                'provider' => $provider,
                'payload' => $payload,
            ]);

            $fields = $this->extractStatusFields($provider, $payload);
            $messageSid = $fields['message_id'];
            $status = $fields['status'];

            if (! $messageSid || ! $status) {
                return response('OK', 200);
            }

            // Find message by provider ID
            $message = SmsMessage::where('provider_message_id', $messageSid)->first();

            if ($message) {
                $message->update([
                    'status' => $status,
                    'delivered_at' => in_array($status, ['delivered', 'sent']) ? now() : null,
                    'error_message' => $fields['error'],
                ]);

                // Deduct credits on successful delivery. $fields['billable']
                // is what keeps a receipt that merely re-confirms an
                // already-delivered message from becoming that message's
                // first charge — see extractStatusFields().
                if ($fields['billable'] && in_array($status, ['delivered', 'sent']) && ! $message->credits_deducted && $message->direction === 'outbound') {
                    $this->deductSmsCredits($message, $fields['cost']);
                }

                Log::info('SMS status updated', [
                    'message_id' => $message->id,
                    'status' => $status,
                ]);
            }

            return response('OK', 200);

        } catch (\Exception $e) {
            Log::error('SMS status webhook failed', [
                'error' => $e->getMessage(),
            ]);

            return response('Error', 500);
        }
    }

    /**
     * Provider statuses that describe something happening to a message
     * that has already arrived, rather than its arrival.
     *
     * `read` maps to `delivered` because that is what it implies, and
     * `delivered` is one of the two statuses that trigger the credit
     * deduction — so on a message that had somehow never been billed, a
     * `read` receipt would become its *first* charge. That is a new
     * billing trigger on a paying installation, not the end of a 500, and
     * Twilio does not send Price on every callback, so the charge could
     * be recorded at zero cost and show up as a 100%-margin transaction
     * in the profit analytics. A genuinely unbilled delivered row is what
     * the sms:audit-unbilled backstop is for.
     */
    private const NON_BILLING_PROVIDER_STATUSES = ['read'];

    /**
     * Normalise a status-callback payload's provider-specific field names
     * into one shape the rest of handleStatus() reads regardless of which
     * provider called back.
     *
     * `billable` says whether this receipt may start a deduction. It is
     * decided from the provider's own word, not from the mapped status,
     * because the mapping deliberately collapses values the billing
     * decision has to keep apart.
     *
     * @return array{message_id: ?string, status: ?string, cost: float, error: ?string, billable: bool}
     */
    private function extractStatusFields(string $provider, array $payload): array
    {
        if ($provider === 'plivo') {
            $providerStatus = $payload['Status'] ?? null;

            return [
                'message_id' => $payload['MessageUUID'] ?? null,
                'status' => $this->mapMessageStatus($providerStatus),
                // TotalAmount is already the real charge (TotalRate *
                // Units, i.e. already scaled by segment count) — not a
                // per-segment rate.
                'cost' => isset($payload['TotalAmount']) ? abs((float) $payload['TotalAmount']) : 0.0,
                'error' => $payload['ErrorCode'] ?? null,
                'billable' => $this->isBillableReceipt($providerStatus),
            ];
        }

        $providerStatus = $payload['MessageStatus'] ?? $payload['SmsStatus'] ?? null;

        return [
            'message_id' => $payload['MessageSid'] ?? $payload['SmsSid'] ?? null,
            'status' => $this->mapMessageStatus($providerStatus),
            'cost' => isset($payload['Price']) ? abs((float) $payload['Price']) : 0.0,
            'error' => $payload['ErrorMessage'] ?? null,
            'billable' => $this->isBillableReceipt($providerStatus),
        ];
    }

    private function isBillableReceipt(?string $providerStatus): bool
    {
        return ! in_array(
            strtolower(trim((string) $providerStatus)),
            self::NON_BILLING_PROVIDER_STATUSES,
            true
        );
    }

    /**
     * Translate a provider's delivery-receipt status into a member of the
     * `sms_messages.status` column's own vocabulary.
     *
     * This handler used to write the provider's string verbatim, and both
     * providers send values that column has never had. `undelivered` is
     * the one that mattered: Twilio sends it for a message an operator
     * rejected or could not deliver, the write was refused (strict-mode
     * enum on MySQL, CHECK constraint on SQLite), handleStatus()'s catch
     * answered 500, Twilio retried into the same 500, and the row stayed
     * at `sent` with error_message never written — so the customer saw a
     * message that looked sent, had already been charged for it on the
     * earlier `sent` receipt, and was never told it had failed. The
     * column now carries `undelivered`; everything else is mapped.
     *
     * Where a provider value has no member of its own it maps to the
     * nearest one that does not overstate the outcome. `sending`,
     * `accepted` and `scheduled` all become `queued` rather than `sent`,
     * because `sent` is one of the two statuses that trigger the credit
     * deduction below — a message still on its way must not be billed as
     * one that left. `read` implies delivery, so it is `delivered`.
     * `canceled`, `rejected` and `deleted` are `failed`. `read` becomes
     * `delivered` for the status, but never bills — see
     * NON_BILLING_PROVIDER_STATUSES.
     *
     * An unmapped value returns null, which handleStatus() treats as
     * nothing to act on: the row keeps the status it has and the receipt
     * is acknowledged, so no retry storm follows. Guessing instead would
     * mean inventing a delivery outcome for a message the customer is
     * billed on, which is worse than leaving the row alone — the warning
     * names the value so it can be mapped deliberately.
     */
    private function mapMessageStatus(?string $providerStatus): ?string
    {
        $status = strtolower(trim((string) $providerStatus));

        if ($status === '') {
            return null;
        }

        return match ($status) {
            'queued', 'accepted', 'scheduled', 'sending' => 'queued',
            'sent' => 'sent',
            'delivered', 'read' => 'delivered',
            'undelivered' => 'undelivered',
            'failed', 'canceled', 'cancelled', 'rejected', 'deleted' => 'failed',
            'received', 'receiving' => 'received',
            default => $this->unmappedMessageStatus($status),
        };
    }

    private function unmappedMessageStatus(string $status): ?string
    {
        Log::warning('A delivery receipt carried an unmapped message status; leaving the row unchanged', [
            'received' => $status,
        ]);

        return null;
    }

    /**
     * Deduct credits for SMS message.
     *
     * @param  float  $actualCost  Already extracted and made positive by
     *                             extractStatusFields() — provider-specific
     *                             (Twilio's Price vs Plivo's TotalAmount),
     *                             so this method no longer reads a raw
     *                             webhook payload itself.
     */
    private function deductSmsCredits(SmsMessage $message, float $actualCost)
    {
        try {
            $conversation = $message->conversation;
            if (! $conversation) {
                Log::warning('Cannot deduct SMS credits: Conversation not found', [
                    'message_id' => $message->id,
                ]);

                return;
            }

            $phoneNumber = $conversation->phoneNumber;
            if (! $phoneNumber) {
                Log::warning('Cannot deduct SMS credits: Phone number not found', [
                    'message_id' => $message->id,
                    'conversation_id' => $conversation->id,
                ]);

                return;
            }

            $user = $phoneNumber->user;
            if (! $user) {
                Log::warning('Cannot deduct SMS credits: User not found', [
                    'message_id' => $message->id,
                    'phone_number_id' => $phoneNumber->id,
                ]);

                return;
            }

            // Extract country code from receiver phone number
            $countryCode = $this->extractCountryCode($message->receiver_phone);

            // Store country code in metadata if not already set
            $metadata = $message->metadata ?? [];
            if (! isset($metadata['country_code'])) {
                $metadata['country_code'] = $countryCode;
                $message->update(['metadata' => $metadata]);
            }

            // Get pricing with markup
            $pricingService = app(PricingService::class);
            $pricing = $pricingService->calculateSmsCost(
                $countryCode,
                $message->num_segments ?? 1,
                $user->pricing_tier ?? 'standard'
            );

            // Check if user can afford
            $creditService = app(CreditService::class);
            if (! $creditService->canAfford($user, $pricing['charge'])) {
                Log::error('Insufficient credits for SMS', [
                    'message_id' => $message->id,
                    'user_id' => $user->id,
                    'required' => $pricing['charge'],
                    'balance' => $user->credit_balance,
                ]);

                // Mark as failed due to insufficient funds
                $message->update([
                    'status' => 'failed',
                    'error_message' => 'Insufficient credits',
                ]);

                return;
            }

            // Deduct credits with profit tracking
            DB::beginTransaction();
            try {
                $transaction = $creditService->deductForSms(
                    $user,
                    $message,
                    $actualCost,
                    $pricing['charge']
                );

                // Mark as deducted to prevent double charging
                $message->update([
                    'credits_deducted' => true,
                    'transaction_id' => $transaction->id,
                ]);

                DB::commit();

                Log::info('SMS credits deducted successfully', [
                    'message_id' => $message->id,
                    'user_id' => $user->id,
                    'charged' => $pricing['charge'],
                    'actual_cost' => $actualCost,
                    'profit' => $transaction->profit_amount,
                    'margin' => $transaction->profit_percentage.'%',
                    'transaction_id' => $transaction->id,
                    'segments' => $message->num_segments,
                    'country_code' => $countryCode,
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Failed to deduct SMS credits', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Extract country code from phone number
     */
    private function extractCountryCode(string $phoneNumber): string
    {
        // Remove all non-numeric characters
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);

        // Common country code mappings (check multi-digit codes first)
        $countryCodeMap = [
            '1' => 'US',     // USA, Canada
            '44' => 'GB',    // UK
            '61' => 'AU',    // Australia
            '91' => 'IN',    // India
            '49' => 'DE',    // Germany
            '33' => 'FR',    // France
            '81' => 'JP',    // Japan
            '55' => 'BR',    // Brazil
            '52' => 'MX',    // Mexico
            '86' => 'CN',    // China
            '34' => 'ES',    // Spain
            '39' => 'IT',    // Italy
            '7' => 'RU',     // Russia
            '82' => 'KR',    // South Korea
            '31' => 'NL',    // Netherlands
        ];

        // Check for multi-digit codes first (2-3 digits)
        foreach (['44', '61', '91', '49', '33', '81', '55', '52', '86', '34', '39', '82', '31'] as $code) {
            if (str_starts_with($cleaned, $code)) {
                return $countryCodeMap[$code];
            }
        }

        // Check for single-digit codes
        if (str_starts_with($cleaned, '1')) {
            return 'US';
        }

        if (str_starts_with($cleaned, '7')) {
            return 'RU';
        }

        // Default to US
        return 'US';
    }

    /**
     * Generate Twilio TwiML response
     */
    /**
     * An empty acknowledgement document. Deliberately takes no message
     * argument: a <Message> element here is not a comment or a status
     * code — in a reply to an incoming-message webhook, both Twilio and
     * Plivo interpret it as an instruction to send a *real* outbound SMS
     * back to the sender, billed by the provider and delivered to that
     * person's phone. This method used to accept one and every error
     * path passed it a human-readable diagnostic string; because the
     * response was never actually sent (see handleIncoming()'s history —
     * response()->xml() didn't exist), that auto-reply never fired. Once
     * the transport bug was fixed, every one of those diagnostics would
     * have started being texted to the original sender. Do not
     * reintroduce a message parameter without treating it as the
     * product decision it is, not a debugging convenience.
     */
    private function twilioResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
    }
}
