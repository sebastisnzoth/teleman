<?php

declare(strict_types=1);

namespace App\Services\Telephony\Plivo;

use App\Contracts\Telephony\VoiceProviderInterface;
use Exception;
use Illuminate\Support\Facades\Log;
use Plivo\RestClient;

/**
 * Plivo implementation of the voice provider interface.
 *
 * Key differences from Twilio:
 * - makeCall: create($from, array $to, $answerUrl, $optionalArgs) — $to is an array.
 * - Call ID: call/request UUID (not call SID).
 * - Status values: ringing, in-progress, completed, busy, failed, timeout, no-answer.
 *
 * Ported from an earlier prototype with its REST call shapes corrected
 * against the installed plivo/plivo-php SDK (v4.71). The load-bearing fixes:
 *
 *  - create() takes ($from, array $to, $answerUrl, $optionalArgs)
 *    positionally, not one associative array, and its response is a
 *    CallCreateResponse object (read via getRequestUuid(), not array
 *    access — that object has no ArrayAccess and would fatal on
 *    $response['requestUuid']).
 *  - There is no calls->update() on this SDK at all — redirecting a live
 *    call is calls->transfer($liveCallUuid, ['legs' => 'aleg',
 *    'aleg_url' => ...]).
 *  - Never isset()/empty() on an SDK resource. Plivo\Resources\Resource
 *    serves every field through __get() off a public $properties bag and
 *    defines no __isset(), so isset($call->billDuration) is false even
 *    when the value is right there, and empty() is true for the same
 *    reason. `??` is the one safe form: with no __isset() declared, PHP
 *    falls straight through to __get() for a null-coalesce, which is why
 *    every read below uses it. Note the flip side — __get() *throws*
 *    PlivoRestException for a name the bag does not hold, which `??`
 *    cannot suppress, so reads go through property() below rather than
 *    letting one absent field lose the whole response.
 */
class PlivoVoiceProvider implements VoiceProviderInterface
{
    private ?RestClient $client = null;

    private array $credentials = [];

    public function initialize(array $credentials): static
    {
        $this->credentials = $credentials;
        $this->client = new RestClient(
            $credentials['auth_id'] ?? null,
            $credentials['auth_token'] ?? null
        );

        return $this;
    }

    /**
     * `call_id` here is a Plivo **RequestUUID**, not a CallUUID.
     *
     * Plivo's create-call API is asynchronous: it queues the request and
     * answers with a RequestUUID, and there is no synchronous way to turn
     * that into a CallUUID — the CallUUID only exists once the call is
     * actually placed, and first reaches this application on the call's
     * first webhook (`CallUUID` in the answer/hangup payload). Twilio has
     * no such split, so VoiceProviderInterface's single `call_id` field
     * unavoidably means two different kinds of identifier per provider:
     * a CallUUID on Twilio, a RequestUUID here until the first webhook
     * replaces it. Whoever wires this up must store the RequestUUID and
     * overwrite it with the CallUUID from that webhook — `request_uuid`
     * is returned alongside so the two kinds can be told apart without
     * guessing from the string, which is impossible (both are plain
     * UUIDs).
     *
     * Everything downstream — getCallDetails(), updateCall(),
     * hangupCall() — needs the CallUUID; only hangupCall() can do
     * anything useful with a RequestUUID, and it falls back to the SDK's
     * dedicated calls->cancel($requestUuid) for exactly that case.
     *
     * @return array{success: bool, call_id: ?string, request_uuid: ?string, status: ?string, error: ?string}
     */
    public function makeCall(string $to, string $from, string $answerUrl, array $options = []): array
    {
        try {
            $this->ensureInitialized();

            $optionalArgs = ['answer_method' => 'POST'];

            if (! empty($options['statusCallback'])) {
                $optionalArgs['hangup_url'] = $options['statusCallback'];
                $optionalArgs['hangup_method'] = 'POST';

                // Without this, a Plivo call gets no status notification
                // at all until hangup_url — it sits at whatever status it
                // was created with for its entire duration, not briefly
                // at the start the way a Twilio call's own ringing/
                // answered statusCallback events already move it. That is
                // reachable by the stale-campaign sweeper (which treats
                // any non-final call status as "stuck" once its contact's
                // last-dialled timestamp is old enough) and by anything
                // else that assumes status advances promptly. ring_url is
                // a genuinely separate, documented Plivo notification —
                // Account/{id}/Call/, "ring_url: The URL that is notified
                // by Plivo when the call is ringing" (see the installed
                // SDK's own CallInterface::create() docblock) — fired
                // once, seconds after the call is placed for the common
                // case, carrying the real CallUUID. Pointed at the same
                // URL as hangup_url: both are plain notifications the
                // shared webhookStatus handler already processes
                // generically by CallStatus/CallUUID, so no new endpoint
                // is needed.
                $optionalArgs['ring_url'] = $options['statusCallback'];
                $optionalArgs['ring_method'] = 'POST';
            }

            // Notification of the END of the call only, with no ring
            // notification at all — a separate option because the two are
            // otherwise indistinguishable once they arrive. Plivo
            // documents no parameter that tells a ring_url POST from a
            // hangup_url one: CallUUID is the only field named for either
            // (and it can fire more than once on ring), while the
            // standard CallStatus/From/To/Direction set is documented for
            // the answer, action, redirect and fallback URLs instead. So
            // a caller whose handler must act only when the call has
            // ended cannot decide that from the payload, and says so in
            // the request instead. statusCallback keeps meaning both URLs
            // for every caller that already uses it.
            if (! empty($options['hangupCallback'])) {
                $optionalArgs['hangup_url'] = $options['hangupCallback'];
                $optionalArgs['hangup_method'] = 'POST';
            }

            if (! empty($options['timeout'])) {
                $optionalArgs['ring_timeout'] = $options['timeout'];
            }

            if (! empty($options['machineDetection'])) {
                $optionalArgs['machine_detection'] = 'true';
                $optionalArgs['machine_detection_time'] = $options['machine_detection_time'] ?? 5000;
            }

            // Plivo's Call API wants bare digits on both numbers — the
            // installed SDK's own CallInterface::create() docblock:
            // "Regular number must be prefixed with country code but
            // without the + sign". Every caller here hands over this
            // app's own '+'-prefixed E.164, so the conversion belongs at
            // this boundary. A SIP endpoint destination
            // ("sip:john1234@phone.plivo.com", equally valid here) has no
            // leading '+' and passes through untouched.
            //
            // This is the REST boundary only: <Dial><Number> in the XML is
            // a separate one that takes the '+' form, and
            // PlivoXmlGenerator deliberately does not convert.
            $response = $this->client->calls->create(
                PlivoNumberFormat::toPlivoFormat($from),
                [PlivoNumberFormat::toPlivoFormat($to)],
                $answerUrl,
                $optionalArgs
            );

            $requestUuid = $response->getRequestUuid();

            return [
                'success' => true,
                // A RequestUUID, not a CallUUID — see the docblock above.
                'call_id' => $requestUuid,
                'request_uuid' => $requestUuid,
                'status' => 'initiated',
                'error' => null,
            ];
        } catch (Exception $e) {
            Log::error('Plivo: Failed to make call', [
                'to' => $to,
                'from' => $from,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'call_id' => null,
                'request_uuid' => null,
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Expects a **CallUUID** — Plivo's `Call/{callUuid}/` detail endpoint
     * knows nothing about a RequestUUID and 404s on one. See makeCall()
     * for where the CallUUID comes from.
     */
    public function getCallDetails(string $callId): ?array
    {
        try {
            $this->ensureInitialized();

            $call = $this->client->calls->get($callId);

            $billDuration = self::property($call, 'billDuration');
            $totalAmount = self::property($call, 'totalAmount');

            return [
                'call_id' => self::property($call, 'callUuid') ?? $callId,
                'status' => $this->mapCallStatus((string) (self::property($call, 'callState') ?? '')),
                'duration' => $billDuration !== null ? (int) $billDuration : null,
                // Plivo\Resources\Call\Call names these `from`/`to` (from
                // the API's from_number/to_number) — there is no
                // fromNumber/toNumber on it, and asking for one throws.
                'from' => (string) (self::property($call, 'from') ?? ''),
                'to' => (string) (self::property($call, 'to') ?? ''),
                'direction' => (string) (self::property($call, 'callDirection') ?? 'outbound'),
                'price' => $totalAmount !== null ? abs((float) $totalAmount) : null,
                'price_unit' => 'USD',
                'start_time' => self::property($call, 'answerTime'),
                'end_time' => self::property($call, 'endTime'),
                // Plivo reports answering-machine detection on the webhook
                // (`Machine` in the answer payload), not on the CDR this
                // endpoint returns, so there is nothing to read here.
                'answered_by' => null,
                'recordings' => [], // Fetch separately via getRecording() if needed.
            ];
        } catch (Exception $e) {
            Log::error('Plivo: Failed to fetch call details', [
                'call_id' => $callId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Expects a **CallUUID**: calls->transfer() addresses a live call leg
     * and a RequestUUID is not one. See makeCall().
     */
    public function updateCall(string $callId, array $params): bool
    {
        try {
            $this->ensureInitialized();

            if (isset($params['status']) && $params['status'] === 'completed') {
                return $this->hangupCall($callId);
            }

            if (! isset($params['url'])) {
                return false;
            }

            $this->client->calls->transfer($callId, [
                'legs' => 'aleg',
                'aleg_url' => $params['url'],
                'aleg_method' => 'POST',
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Plivo: Failed to update call', [
                'call_id' => $callId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Accepts either kind of identifier (see makeCall()), because this is
     * the one operation that has a meaningful answer for both.
     *
     * calls->delete($callUuid) hangs up a live call — `DELETE
     * Call/{callUuid}/`. A RequestUUID is not a CallUUID and that
     * endpoint rejects it, so a failure is retried through
     * calls->cancel($requestUuid) — `DELETE Request/{requestUuid}/`, the
     * SDK's dedicated "cancel a queued call request" call. Which one the
     * caller holds is not knowable from the string (both are plain
     * UUIDs), and a call that has not been placed yet is precisely the
     * case where the caller still only has the RequestUUID, so trying
     * both is the honest behaviour rather than guessing.
     */
    public function hangupCall(string $callId): bool
    {
        try {
            $this->ensureInitialized();
        } catch (Exception $e) {
            Log::error('Plivo: Failed to hangup call', [
                'call_id' => $callId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        try {
            $this->client->calls->delete($callId);

            return true;
        } catch (Exception $e) {
            Log::warning('Plivo: Hangup by call UUID failed, retrying as a queued request cancel', [
                'call_id' => $callId,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $this->client->calls->cancel($callId);

            return true;
        } catch (Exception $e) {
            Log::error('Plivo: Failed to hangup call', [
                'call_id' => $callId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getRecording(string $recordingId): ?array
    {
        try {
            $this->ensureInitialized();
            $recording = $this->client->recordings->get($recordingId);

            $durationMs = self::property($recording, 'recordingDurationMs');

            return [
                'id' => (string) (self::property($recording, 'recordingId') ?? $recordingId),
                'url' => self::property($recording, 'recordingUrl'),
                'duration' => $durationMs !== null
                    ? (int) ceil(((float) $durationMs) / 1000)
                    : null,
                'status' => 'completed',
            ];
        } catch (Exception $e) {
            Log::error('Plivo: Failed to fetch recording', [
                'recording_id' => $recordingId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function deleteRecording(string $recordingId): bool
    {
        try {
            $this->ensureInitialized();
            $this->client->recordings->delete($recordingId);

            return true;
        } catch (Exception $e) {
            Log::error('Plivo: Failed to delete recording', [
                'recording_id' => $recordingId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function verifyCredentials(array $credentials): array
    {
        try {
            $client = new RestClient($credentials['auth_id'] ?? null, $credentials['auth_token'] ?? null);
            $account = $client->accounts->get();

            return [
                'valid' => true,
                'account_name' => $account->name ?? 'Plivo Account',
                'error' => null,
            ];
        } catch (Exception $e) {
            return [
                'valid' => false,
                'account_name' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * VoiceProviderInterface's signature predates Plivo's V3 scheme and
     * carries no nonce argument, so a caller must fold the nonce into
     * $payload under this key (the real HTTP header name) — e.g.
     * $request->all() + ['X-Plivo-Signature-V3-Nonce' => $request->header('X-Plivo-Signature-V3-Nonce')].
     * Production webhook routes should prefer the plivo.signature
     * middleware (App\Http\Middleware\ValidatePlivoSignature), which reads
     * the nonce header directly; this method exists to satisfy the
     * interface contract for any caller that already has payload +
     * signature in hand. The algorithm itself is PlivoSignature — the one
     * place it is implemented, shared with the middleware and
     * PlivoSMSProvider so a correction lands once rather than diverging.
     */
    public function validateWebhook(array $payload, string $signature, string $url): bool
    {
        try {
            $authToken = $this->credentials['auth_token'] ?? '';
            if ($authToken === '') {
                return false;
            }

            $nonce = $payload['X-Plivo-Signature-V3-Nonce'] ?? null;
            if (empty($nonce)) {
                return false;
            }

            unset($payload['X-Plivo-Signature-V3-Nonce']);

            return PlivoSignature::matches(
                PlivoSignature::compute($url, $payload, (string) $nonce, $authToken),
                $signature
            );
        } catch (Exception $e) {
            Log::error('Plivo: Webhook validation failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function estimateCallCost(string $to, int $durationSeconds): float
    {
        $costPerMinute = config('telephony.providers.plivo.pricing.api_per_minute', 0.010);
        $minutes = ceil($durationSeconds / 60);

        return round($minutes * $costPerMinute, 4);
    }

    public function getName(): string
    {
        return 'plivo';
    }

    // ─── Private ─────────────────────────────────

    /**
     * Read one field off a Plivo SDK resource, safely.
     *
     * Plivo\Resources\Resource has no __isset(), so isset()/empty() on
     * one of these fields is always false — that was a real bug here:
     * getCallDetails() reported duration and price as null on every
     * completed call, and getRecording() did the same for duration. `??`
     * is the form that works (with no __isset() to consult, PHP falls
     * through to __get()), but __get() throws PlivoRestException when the
     * property bag genuinely lacks the name, which `??` does not catch —
     * and one absent field would then discard the entire response. This
     * catches that one read instead. Verified against
     * vendor/plivo/plivo-php/src/Plivo/Resources/Resource.php.
     */
    private static function property(object $resource, string $name): mixed
    {
        try {
            return $resource->{$name} ?? null;
        } catch (Exception) {
            return null;
        }
    }

    private function mapCallStatus(string $plivoStatus): string
    {
        return match (strtolower($plivoStatus)) {
            'ringing' => 'ringing',
            'in-progress', 'in_progress' => 'in-progress',
            'completed' => 'completed',
            'busy' => 'busy',
            'failed' => 'failed',
            'timeout', 'no-answer' => 'no-answer',
            'canceled', 'cancelled' => 'canceled',
            default => $plivoStatus,
        };
    }

    private function ensureInitialized(): void
    {
        if (! $this->client) {
            throw new Exception('Plivo voice provider not initialized. Call initialize() first.');
        }
    }
}
