<?php

declare(strict_types=1);

namespace App\Services\Telephony\Plivo;

use App\Contracts\Telephony\VerificationProviderInterface;
use App\Models\UserKycVerification;
use App\Services\Telephony\TelephonyManager;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Plivo KYC phone verification.
 *
 * Plivo has no verification product to hand the code to, so this app
 * owns the code: it is generated here, sent as an ordinary SMS through
 * the installation's own SMS capability, and kept as a hash on the
 * verification row. Never in plain text (phone_verification_code is
 * cleared on the way past — the Twilio SMS fallback is the only thing
 * that column exists for), never in a log line, never in a response
 * (UserKycVerification hides it).
 *
 * Hash::make(), not a fast digest: a 6-digit code has only a million
 * candidates, which a leaked SHA-256 of it gives up in well under a
 * second. At the installation's configured bcrypt cost the same search
 * costs hours per code — against a code that stops being worth anything
 * once UserKycVerification::PHONE_VERIFICATION_TTL_MINUTES have passed.
 *
 * How long a code lasts and how many guesses it survives are not this
 * class's to decide: both are the product's one phone verification
 * policy, on UserKycVerification, and this class enforces them.
 *
 * The SMS goes out through TelephonyManager rather than a Plivo client
 * built here, so this class sends the same way, from the same account,
 * with the same delivery callbacks as every other message the
 * installation sends.
 */
class PlivoVerificationProvider implements VerificationProviderInterface
{
    /** @var array<string, mixed> */
    private array $credentials = [];

    public function __construct(private readonly TelephonyManager $telephony) {}

    public function initialize(array $credentials): static
    {
        $this->credentials = $credentials;

        return $this;
    }

    public function start(UserKycVerification $kyc): void
    {
        $code = $this->generateCode();

        // The new code replaces the old one in the same write that resets
        // the attempt counter and the expiry, and before anything here can
        // fail. Resetting those limits without replacing the code would
        // hand the previous code a fresh ceiling and a fresh window — as
        // many times as a send could be made to fail. A code nobody
        // received is harmless; an old one with its limits wiped is not.
        //
        // This covers only what start() itself resets. A caller that
        // resets the limits before start() runs — KycController::
        // resendCode() does — must clear the code in that write as well,
        // because the send can fail before start() is ever reached.
        $kyc->forceFill([
            'phone_verification_code' => null,
            'phone_verification_code_hash' => Hash::make($code),
            'phone_verification_expires_at' => now()->addMinutes(UserKycVerification::PHONE_VERIFICATION_TTL_MINUTES),
            'phone_verification_attempts' => 0,
        ])->save();

        $from = $this->senderNumber();

        $result = $this->telephony->sms()->initialize($this->credentials)->send(
            $from,
            (string) $kyc->phone_number,
            'Your KYC verification code is: '.$code.'. Valid for '.UserKycVerification::PHONE_VERIFICATION_TTL_MINUTES.' minutes.'
        );

        if (! ($result['success'] ?? false)) {
            // The provider's own words stay in the log. What the customer
            // is told names no provider: this is a white-label product and
            // the message is flashed straight onto their screen.
            Log::error('Phone verification SMS failed', [
                'user_id' => $kyc->user_id,
                'error' => $result['error'] ?? null,
            ]);

            throw new Exception('Could not send the verification code. Please try again in a moment.');
        }

        Log::info('Phone verification SMS sent', [
            'user_id' => $kyc->user_id,
            'message_id' => $result['message_id'] ?? null,
        ]);
    }

    public function check(UserKycVerification $kyc, string $code): array
    {
        $hash = $kyc->phone_verification_code_hash;

        // No outstanding code, or one that has run out of time: both mean
        // the same thing to the customer, and neither counts as an attempt
        // — there is nothing left to guess at.
        if (! $hash || ! $kyc->phone_verification_expires_at || $kyc->phone_verification_expires_at->isPast()) {
            return $this->expired();
        }

        // Cheap early answers from the row as this request read it. They
        // are not what enforces anything: see the reservation below.
        if ($kyc->phone_verification_attempts >= UserKycVerification::PHONE_VERIFICATION_MAX_ATTEMPTS) {
            return $this->exhausted();
        }

        // The attempt is taken before the comparison, in one conditional
        // write against the row as it is *now* — not as this request read
        // it. Requests that share a session are not serialised, and bcrypt
        // holds each one for hundreds of milliseconds, so a count read at
        // the start and incremented after the comparison let every
        // concurrent request through: the ceiling became the server's
        // concurrency. Here, once the count reaches the ceiling, no further
        // request can take an attempt at all.
        //
        // Bound to this exact hash, not merely to "a code is outstanding":
        // a request that read the previous code while a resend replaced it
        // must not spend the new code's attempts guessing at the old one.
        $reserved = UserKycVerification::query()
            ->whereKey($kyc->getKey())
            ->where('phone_verification_code_hash', $hash)
            ->where('phone_verification_attempts', '<', UserKycVerification::PHONE_VERIFICATION_MAX_ATTEMPTS)
            ->where('phone_verification_expires_at', '>', now())
            ->increment('phone_verification_attempts');

        if ($reserved === 0) {
            $current = $kyc->fresh();

            return $current && $current->phone_verification_attempts >= UserKycVerification::PHONE_VERIFICATION_MAX_ATTEMPTS
                ? $this->exhausted()
                : $this->expired();
        }

        if (! Hash::check($code, $hash)) {
            return ['verified' => false, 'error' => 'Invalid verification code. Please try again.'];
        }

        // Good once. The hash goes as it is spent, so a code that leaks
        // after the fact — a forwarded SMS, a shared screenshot — cannot
        // be replayed against the row. Conditional for the same reason as
        // the reservation: a concurrent request holding the same code may
        // have spent it while this one was still comparing, and a code is
        // not accepted twice.
        $spent = UserKycVerification::query()
            ->whereKey($kyc->getKey())
            ->where('phone_verification_code_hash', $hash)
            ->update(['phone_verification_code_hash' => null]);

        if ($spent === 0) {
            return $this->expired();
        }

        return ['verified' => true, 'error' => null];
    }

    public function getName(): string
    {
        return 'plivo';
    }

    // ─── Private Helpers ─────────────────────────

    /** @return array{verified: false, error: string} */
    private function expired(): array
    {
        return ['verified' => false, 'error' => 'Verification code has expired. Please request a new code.'];
    }

    /** @return array{verified: false, error: string} */
    private function exhausted(): array
    {
        return ['verified' => false, 'error' => 'Maximum verification attempts exceeded. Please request a new code.'];
    }

    /**
     * The number the code is sent from: the installation's own first
     * SMS-capable number, falling back to its first number of any kind —
     * the same order the Twilio path picks a sender in, so an
     * installation behaves the same way whichever provider it is locked
     * to.
     */
    private function senderNumber(): string
    {
        $numbers = $this->telephony->phoneNumbers()->initialize($this->credentials)->listNumbers();

        foreach ($numbers as $number) {
            if ($number['capabilities']['sms'] ?? false) {
                return (string) ($number['number'] ?? '');
            }
        }

        if (isset($numbers[0]['number']) && $numbers[0]['number'] !== '') {
            return (string) $numbers[0]['number'];
        }

        throw new Exception('No phone number is available to send the verification code. Please contact support.');
    }

    /**
     * random_int(), not rand()/mt_rand(): this is a credential, and the
     * only difference between a guessable one and this is which generator
     * produced it. Same shape (six digits, leading zeros kept) as the code
     * KycController has always generated.
     */
    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
