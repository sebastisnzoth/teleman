<?php

declare(strict_types=1);

namespace App\Services\Telephony;

use App\Contracts\Telephony\VerificationProviderInterface;
use App\Enums\TelephonyProvider;
use App\Models\TelephonyProviderConfig;
use App\Models\TwilioGlobalConfig;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Resolves a credential-initialized phone verification provider for
 * whichever provider this installation is locked to — the same job
 * SmsProviderFactory does for outbound SMS, and for the same reason:
 * TelephonyManager hands back uninitialised, credential-free instances,
 * so somebody has to know where this installation's account details
 * live. Doing that in one place keeps the two halves of a verification
 * (sending a code, checking it) from drifting apart.
 *
 * There is no per-customer resolution here. The admin configures one
 * account for the whole installation; customers only ever buy numbers.
 *
 * ── Why sending and checking are not the same call ──
 *
 * They differ, and they differed before this class existed. The send path
 * has always suppressed the Verify service SID whenever an admin-entered
 * global Twilio configuration is present (that configuration has no field
 * for one), while the check path has always read the SID straight from
 * configuration regardless. On an installation that has both, that means
 * a code sent as an ordinary SMS is then checked against Verify, which
 * cannot know it.
 *
 * That is a real inconsistency and it is deliberately preserved:
 * hundreds of paying installations upgrade into this code and the one
 * thing this move may not do is change what any of them does. Repairing
 * it is a behaviour change and belongs to whoever decides to make it, in
 * daylight, on its own.
 */
final class VerificationProviderFactory
{
    public static function forSending(TelephonyManager $telephony): VerificationProviderInterface
    {
        $credentials = $telephony->provider() === TelephonyProvider::PLIVO
            ? self::resolvePlivoCredentials(required: true)
            : self::resolveTwilioSendingCredentials();

        return $telephony->verification()->initialize($credentials);
    }

    public static function forChecking(TelephonyManager $telephony): VerificationProviderInterface
    {
        $credentials = $telephony->provider() === TelephonyProvider::PLIVO
            ? self::resolvePlivoCredentials(required: false)
            : self::resolveTwilioCheckingCredentials();

        return $telephony->verification()->initialize($credentials);
    }

    /**
     * The admin's global configuration first, the .env/services account
     * second — including the log lines that resolution has always
     * written, and the exact error a customer sees when neither exists.
     *
     * @return array{account_sid: ?string, auth_token: ?string, verify_service_sid: ?string}
     */
    private static function resolveTwilioSendingCredentials(): array
    {
        // Try to get global Twilio config from database first
        $globalConfig = TwilioGlobalConfig::active();

        if ($globalConfig) {
            // Use database config
            $accountSid = $globalConfig->account_sid;
            $authToken = $globalConfig->getDecryptedAuthToken();
            $verifyServiceSid = null; // Global config doesn't have verify service yet

            Log::info('Using TwilioGlobalConfig from database', [
                'config_id' => $globalConfig->id,
                'account_sid' => substr((string) $accountSid, 0, 10).'...',
            ]);
        } else {
            // Fallback to config/env (config() only, not env() as env doesn't work with cached config)
            $accountSid = config('services.twilio.sid');
            $authToken = config('services.twilio.token');
            $verifyServiceSid = config('services.twilio.verify_service_sid');

            Log::info('Using Twilio config from services.php', [
                'has_config' => ! empty($accountSid),
            ]);
        }

        if (! $accountSid || ! $authToken) {
            Log::error('Twilio configuration missing', [
                'has_global_config' => $globalConfig !== null,
                'has_sid' => ! empty($accountSid),
                'has_token' => ! empty($authToken),
            ]);
            throw new Exception('Twilio is not configured. Please configure Twilio credentials in Settings → Twilio or set TWILIO_ACCOUNT_SID and TWILIO_AUTH_TOKEN in .env');
        }

        return [
            'account_sid' => $accountSid,
            'auth_token' => $authToken,
            'verify_service_sid' => $verifyServiceSid,
        ];
    }

    /**
     * Same account resolution, no throw — a missing credential is
     * reported to the customer by the provider, and only on the branch
     * where it actually matters (checking a code against a locally stored
     * one needs no account at all).
     *
     * @return array{account_sid: ?string, auth_token: ?string, verify_service_sid: ?string}
     */
    private static function resolveTwilioCheckingCredentials(): array
    {
        $globalConfig = TwilioGlobalConfig::active();

        if ($globalConfig) {
            $accountSid = $globalConfig->account_sid;
            $authToken = $globalConfig->getDecryptedAuthToken();
        } else {
            $accountSid = config('services.twilio.sid');
            $authToken = config('services.twilio.token');
        }

        return [
            'account_sid' => $accountSid,
            'auth_token' => $authToken,
            'verify_service_sid' => config('services.twilio.verify_service_sid'),
        ];
    }

    /**
     * This installation's one Plivo account.
     *
     * $required is the difference between the two halves: sending needs
     * the account and says so, in words that name no provider, because
     * the message is flashed onto a customer's screen. Checking a code
     * does not touch the account at all — the comparison is local — so it
     * must not start failing because an admin rotated credentials between
     * the code going out and the customer typing it in.
     *
     * @return array{auth_id: string, auth_token: string}
     */
    private static function resolvePlivoCredentials(bool $required): array
    {
        $config = TelephonyProviderConfig::where('name', 'plivo')->first();
        $authId = $config?->credential('auth_id');
        $authToken = $config?->credential('auth_token');

        if ($required && (! $authId || ! $authToken)) {
            throw new Exception('Phone verification is not configured on this platform. Please contact support.');
        }

        return [
            'auth_id' => (string) $authId,
            'auth_token' => (string) $authToken,
        ];
    }
}
