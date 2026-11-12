<?php

namespace App\Services\SMS;

use App\Contracts\Telephony\SmsProviderInterface as TelephonySmsProviderInterface;
use App\Enums\TelephonyProvider;
use App\Models\TelephonyProviderConfig;
use App\Models\TwilioGlobalConfig;
use App\Models\User;
use App\Services\Telephony\TelephonyManager;
use Exception;

/**
 * Resolves a ready-to-use, credential-initialized SMS sender for a user,
 * for whichever provider this installation is currently locked to.
 *
 * Routed through TelephonyManager::sms() so the actual send()/sendBatch()/
 * getStatus() call is the same abstraction seam voice calls and phone
 * number provisioning already go through (MakeCampaignCallJob,
 * PhoneNumberService) — this class's only remaining job is resolving the
 * right credentials for the locked provider: Plivo via this
 * installation's one configured account (TelephonyProviderConfig,
 * read-only, mirroring MakeCampaignCallJob/PhoneNumberService's own
 * Plivo resolver).
 *
 * Twilio's resolution below is a deliberate, verbatim copy of
 * TwilioSMSProvider::initializeForUser()'s own body (the user's own
 * credential first, falling back to the admin's global configuration)
 * rather than a call to TwilioService — the other existing
 * implementation of the identical fallback order — because TwilioService
 * throws different exception text ("Twilio is not configured for this
 * platform...") than TwilioSMSProvider does ("No Twilio configuration
 * found...") for the one failure case (no credential at all) that reaches
 * a user as an error message. This class replaces the code path that used
 * to construct TwilioSMSProvider directly, so matching its exact
 * behaviour — including that message — is what "zero behaviour change for
 * a Twilio installation" means for this file.
 *
 * Imported under an alias: App\Contracts\Telephony\SmsProviderInterface
 * differs from this namespace's own SMSProviderInterface only by case, and
 * PHP resolves an unqualified name against a `use` import first,
 * case-insensitively — without the alias, make()'s pre-existing
 * `: SMSProviderInterface` return type below would silently start
 * resolving to this new import instead, and every call would throw a
 * TypeError the moment it returned a legacy TwilioSMSProvider. Same
 * reasoning as TwilioSmsProvider's own alias of the legacy
 * TwilioSMSProvider class.
 */
class SmsProviderFactory
{
    /**
     * Create an initialized SMS provider instance for a user, for
     * whichever provider this installation is locked to.
     */
    public static function makeForUser(User $user, TelephonyManager $telephony): TelephonySmsProviderInterface
    {
        $credentials = $telephony->provider() === TelephonyProvider::PLIVO
            ? self::resolvePlivoCredentials()
            : self::resolveTwilioCredentials($user);

        return $telephony->sms()->initialize($credentials);
    }

    /**
     * Create SMS provider instance with credentials
     */
    public static function make(string $provider, $credential): SMSProviderInterface
    {
        return match ($provider) {
            'twilio' => (new TwilioSMSProvider)->initialize($credential),
            default => throw new Exception("Unsupported SMS provider: {$provider}"),
        };
    }

    /**
     * Get list of supported providers
     */
    public static function getSupportedProviders(): array
    {
        return [
            'twilio' => [
                'name' => 'Twilio',
                'supports_mms' => true,
                'description' => 'Twilio SMS & MMS Provider',
            ],
            'plivo' => [
                'name' => 'Plivo',
                'supports_mms' => true,
                'description' => 'Plivo SMS & MMS Provider',
            ],
        ];
    }

    /**
     * @return array{account_sid: string, auth_token: string}
     */
    private static function resolveTwilioCredentials(User $user): array
    {
        $credential = $user->getActiveTwilioCredential();

        if ($credential) {
            return [
                'account_sid' => $credential->account_sid,
                'auth_token' => $credential->auth_token,
            ];
        }

        $globalConfig = TwilioGlobalConfig::active();

        if (! $globalConfig) {
            throw new Exception('No Twilio configuration found. Please configure Twilio in settings.');
        }

        return [
            'account_sid' => $globalConfig->account_sid,
            'auth_token' => $globalConfig->getDecryptedAuthToken(),
        ];
    }

    /**
     * @return array{auth_id: string, auth_token: string}
     */
    private static function resolvePlivoCredentials(): array
    {
        $config = TelephonyProviderConfig::where('name', 'plivo')->first();
        $authId = $config?->credential('auth_id');
        $authToken = $config?->credential('auth_token');

        if (! $authId || ! $authToken) {
            throw new Exception('Plivo is not configured for this platform. Ask an administrator to configure it in Settings → Telephony.');
        }

        return ['auth_id' => $authId, 'auth_token' => $authToken];
    }
}
