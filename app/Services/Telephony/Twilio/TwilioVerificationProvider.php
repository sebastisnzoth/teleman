<?php

declare(strict_types=1);

namespace App\Services\Telephony\Twilio;

use App\Contracts\Telephony\VerificationProviderInterface;
use App\Models\UserKycVerification;
use Exception;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

/**
 * Twilio KYC phone verification: the Verify API when a verify service SID
 * is configured, an ordinary SMS from the account's own first SMS-capable
 * number otherwise.
 *
 * This is the path every existing installation of this product already
 * runs — it was lifted here out of SendPhoneVerificationSms and
 * KycController::verifyPhone() unchanged, down to the log lines and the
 * exact wording of every message a customer can see, because the only
 * acceptable outcome of moving it is that nothing about it moves.
 * PhoneVerificationProviderTest pins it, and KycVerificationTest — which
 * predates any of this and was not touched — still exercises the SMS
 * branch end to end through the controller.
 *
 * Which service SID (if any) arrives in initialize() is the caller's
 * decision, not this class's: see VerificationProviderFactory, where the
 * send and check halves deliberately resolve it differently, as they
 * always have.
 *
 * Not final, and the SDK client is reached through client() rather than
 * constructed inline, so a test can subclass this and replace the one
 * method that touches the network while every decision below still runs
 * for real.
 */
class TwilioVerificationProvider implements VerificationProviderInterface
{
    private string $accountSid = '';

    private string $authToken = '';

    private ?string $verifyServiceSid = null;

    private ?Client $client = null;

    public function initialize(array $credentials): static
    {
        $this->accountSid = (string) ($credentials['account_sid'] ?? '');
        $this->authToken = (string) ($credentials['auth_token'] ?? '');

        $verifyServiceSid = $credentials['verify_service_sid'] ?? null;
        $this->verifyServiceSid = ($verifyServiceSid === null || $verifyServiceSid === '')
            ? null
            : (string) $verifyServiceSid;

        // Credentials just changed; the client built from the previous set
        // must not be reused.
        $this->client = null;

        return $this;
    }

    public function start(UserKycVerification $kyc): void
    {
        $user = $kyc->user;

        // Use Twilio Verify API if service SID is configured, otherwise fall back to regular SMS
        if ($this->verifyServiceSid) {
            // Use Twilio Verify API (recommended)
            $verification = $this->client()->verify->v2
                ->services($this->verifyServiceSid)
                ->verifications
                ->create($kyc->phone_number, 'sms');

            Log::info('Phone verification SMS sent via Twilio Verify API', [
                'user_id' => $user->id,
                'phone_number' => $kyc->phone_number,
                'verification_sid' => $verification->sid,
                'status' => $verification->status,
                'channel' => 'sms',
            ]);

            return;
        }

        // Fallback to regular SMS - fetch phone number from Twilio API
        $fromNumber = $this->firstSendableNumber();

        if (! $fromNumber) {
            throw new Exception('No phone numbers available in your Twilio account. Please purchase a phone number from Twilio Console: https://console.twilio.com/us1/develop/phone-numbers/manage/incoming');
        }

        $message = "Your KYC verification code is: {$kyc->phone_verification_code}. Valid for 10 minutes.";

        $response = $this->client()->messages->create(
            $kyc->phone_number,
            [
                'from' => $fromNumber,
                'body' => $message,
            ]
        );

        Log::info('Phone verification SMS sent via regular SMS', [
            'user_id' => $user->id,
            'phone_number' => $kyc->phone_number,
            'message_sid' => $response->sid,
            'status' => $response->status,
        ]);
    }

    public function check(UserKycVerification $kyc, string $code): array
    {
        if (! $this->verifyServiceSid) {
            // Fallback to manual code verification
            if ($code !== $kyc->phone_verification_code) {
                $kyc->increment('phone_verification_attempts');

                return $this->rejected();
            }

            return ['verified' => true, 'error' => null];
        }

        // Use Twilio Verify API to check the code
        try {
            if (! $this->accountSid || ! $this->authToken) {
                return ['verified' => false, 'error' => 'Twilio is not configured properly.'];
            }

            $verificationCheck = $this->client()->verify->v2
                ->services($this->verifyServiceSid)
                ->verificationChecks
                ->create([
                    'to' => $kyc->phone_number,
                    'code' => $code,
                ]);

            if ($verificationCheck->status !== 'approved') {
                $kyc->increment('phone_verification_attempts');
                Log::warning('Twilio Verify API verification failed', [
                    'user_id' => $kyc->user_id,
                    'status' => $verificationCheck->status,
                    'attempts' => $kyc->phone_verification_attempts + 1,
                ]);

                return $this->rejected();
            }

            Log::info('Twilio Verify API verification successful', [
                'user_id' => $kyc->user_id,
                'phone_number' => $kyc->phone_number,
            ]);
        } catch (Exception $e) {
            $kyc->increment('phone_verification_attempts');
            Log::error('Twilio Verify API check failed', [
                'user_id' => $kyc->user_id,
                'error' => $e->getMessage(),
            ]);

            return $this->rejected();
        }

        return ['verified' => true, 'error' => null];
    }

    public function getName(): string
    {
        return 'twilio';
    }

    // ─── Private Helpers ─────────────────────────

    /**
     * The account's first SMS-capable number, or its first number of any
     * kind, or null — in exactly the original control flow, quirks
     * included: an SMS-capable entry with an empty number falls through to
     * the first number, and a number found before a later failure inside
     * the try is kept. A failure to reach the API is not fatal here: it
     * leaves the result null and start() raises the "no phone numbers"
     * error the customer can act on, which is what this path has always
     * done.
     */
    private function firstSendableNumber(): ?string
    {
        $fromNumber = null;

        // Fetch available phone numbers directly from Twilio API
        try {
            Log::info('Fetching phone numbers from Twilio API');

            $incomingNumbers = $this->client()->incomingPhoneNumbers->read(['limit' => 20]);

            if (count($incomingNumbers) > 0) {
                // Get first SMS-capable number
                foreach ($incomingNumbers as $number) {
                    if ($number->capabilities->sms) {
                        $fromNumber = $number->phoneNumber;
                        Log::info('Found SMS-capable phone number from Twilio API', [
                            'number' => $fromNumber,
                            'friendly_name' => $number->friendlyName,
                            'sid' => $number->sid,
                        ]);
                        break;
                    }
                }

                // If no SMS-capable found, use first available
                if (! $fromNumber && count($incomingNumbers) > 0) {
                    $fromNumber = $incomingNumbers[0]->phoneNumber;
                    Log::info('Using first available phone number from Twilio API', [
                        'number' => $fromNumber,
                    ]);
                }
            } else {
                Log::warning('No phone numbers found in Twilio account');
            }
        } catch (Exception $e) {
            Log::error('Failed to fetch phone numbers from Twilio API', [
                'error' => $e->getMessage(),
            ]);
        }

        return $fromNumber;
    }

    /**
     * What the customer is told for every rejected code, whichever way it
     * was rejected: a caller who learns *why* a code failed learns
     * something an attacker can use.
     *
     * @return array{verified: bool, error: string}
     */
    private function rejected(): array
    {
        return ['verified' => false, 'error' => 'Invalid verification code. Please try again.'];
    }

    /**
     * Refuses to build a client without credentials of its own. The Twilio
     * SDK does not: handed a falsy SID or token — '0' as much as '' — it
     * quietly falls back to TWILIO_ACCOUNT_SID and TWILIO_AUTH_TOKEN in the
     * process environment, which Laravel fills from .env, so a caller that
     * reached this point unconfigured would act on whatever account that
     * file names. The test below is the SDK's own (BaseClient::getArg()),
     * so nothing it would replace gets through. Neither path here can
     * arrive without credentials (VerificationProviderFactory refuses to
     * send, and check() answers "not configured" first, both on the same
     * falsy test), so this changes nothing that runs; it keeps it that way.
     */
    protected function client(): Client
    {
        if (! $this->accountSid || ! $this->authToken) {
            throw new Exception('Twilio is not configured properly.');
        }

        return $this->client ??= new Client($this->accountSid, $this->authToken);
    }
}
