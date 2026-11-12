<?php

declare(strict_types=1);

namespace App\Services\Telephony;

use App\Contracts\Telephony\PricingProviderInterface;
use App\Enums\TelephonyProvider;
use App\Models\TelephonyProviderConfig;
use App\Services\TwilioPricingService;
use Exception;

/**
 * Resolves a credential-initialized pricing provider for whichever
 * provider this installation is locked to — the job
 * VerificationProviderFactory does for phone verification, and for the
 * same reason: TelephonyManager hands back uninitialised, credential-free
 * instances, so somebody has to know where this installation's account
 * details live.
 *
 * There is no per-customer resolution here. Pricing is the provider's
 * price list for the admin's one account; customers never configure
 * telephony.
 *
 * The Twilio account is found by TwilioPricingService's own lookup, the
 * one it has always made, and that is not the lookup
 * VerificationProviderFactory makes: pricing reads the *first*
 * twilio_global_config row, active or not, and only when it has both an
 * account SID and a token. Using the active row instead would be tidier
 * and would change which account an existing installation's price sync
 * authenticates as, so it is not done here.
 */
final class PricingProviderFactory
{
    /**
     * @throws Exception when the locked provider has no account configured
     */
    public static function forInstallation(TelephonyManager $telephony): PricingProviderInterface
    {
        $credentials = $telephony->provider() === TelephonyProvider::PLIVO
            ? self::resolvePlivoCredentials()
            : self::resolveTwilioCredentials();

        return $telephony->pricing()->initialize($credentials);
    }

    /**
     * See TwilioPricingService::installationAccount(). A missing account is
     * not reported here: TwilioPricingProvider::initialize() reports it,
     * with the message the pricing command has always given.
     *
     * @return array{account_sid: mixed, auth_token: mixed}
     */
    private static function resolveTwilioCredentials(): array
    {
        [$accountSid, $authToken] = TwilioPricingService::installationAccount();

        return [
            'account_sid' => $accountSid,
            'auth_token' => $authToken,
        ];
    }

    /**
     * This installation's one Plivo account. The message is written for
     * the admin running the pricing command, which is the only place it
     * surfaces.
     *
     * @return array{auth_id: string, auth_token: string}
     */
    private static function resolvePlivoCredentials(): array
    {
        $config = TelephonyProviderConfig::where('name', 'plivo')->first();
        $authId = $config?->credential('auth_id');
        $authToken = $config?->credential('auth_token');

        if (! $authId || ! $authToken) {
            throw new Exception('Plivo credentials not configured. Please configure the Plivo account in Settings → Telephony.');
        }

        return [
            'auth_id' => $authId,
            'auth_token' => $authToken,
        ];
    }
}
