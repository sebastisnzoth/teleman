<?php

declare(strict_types=1);

namespace App\Actions\Telephony;

use App\Actions\Twilio\GenerateTwilioTokenAction;
use App\Enums\TelephonyProvider;
use App\Exceptions\Telephony\SoftphoneUnavailableException;
use App\Models\TelephonyProviderConfig;
use App\Models\TwilioGlobalConfig;
use App\Models\User;
use App\Services\Telephony\Plivo\PlivoEndpointProvisioner;
use App\Services\Telephony\TelephonyManager;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Issues the login material a user's browser softphone registers with, for
 * whichever provider this installation is locked to.
 *
 * The provider is read from the lock (TelephonyManager::provider()) and
 * never accepted from the caller. This hands out live SIP/WebRTC login
 * material, so letting a client pick the provider would let it request
 * credentials the installation was never set up to issue.
 *
 * Twilio delegates to GenerateTwilioTokenAction — the exact code the
 * existing Twilio token endpoint has always run — rather than to the
 * provider abstraction's TwilioWebRTCTokenProvider. The two agree for a
 * correctly configured installation, but not for one missing its API key
 * fields: today's action signs a token regardless, the abstraction throws.
 * Every paying Twilio installation's softphone will move onto this
 * endpoint, so it must issue precisely what those installations receive
 * today.
 *
 * Plivo's Browser SDK registers as a real SIP Endpoint, so this first
 * ensures the user has one — created once, reused afterwards — and then asks
 * Plivo for a short-lived access token for it (PlivoWebRTCTokenProvider).
 * The Endpoint's password stays on the server: the browser only ever
 * receives a token that expires, the same model Twilio installations use.
 */
class IssueSoftphoneTokenAction
{
    /**
     * Matches the lifetime of the token a Twilio installation's softphone
     * has always received, so both providers' browsers refresh on the same
     * schedule.
     */
    private const TOKEN_TTL_SECONDS = 3600;

    public function __construct(
        private readonly TelephonyManager $telephony,
        private readonly GenerateTwilioTokenAction $twilioTokens,
        private readonly PlivoEndpointProvisioner $plivoEndpoints,
    ) {}

    /**
     * @return array{token: string, identity: string, provider: string, expires_in: int}
     *
     * @throws SoftphoneUnavailableException
     */
    public function execute(User $user): array
    {
        return match ($this->telephony->provider()) {
            TelephonyProvider::TWILIO => $this->forTwilio($user),
            TelephonyProvider::PLIVO => $this->forPlivo($user),
        };
    }

    /**
     * Whether the locked provider has the account configuration a softphone
     * needs. Mirrors what each provider's token path actually requires.
     */
    public function isConfigured(): bool
    {
        return match ($this->telephony->provider()) {
            TelephonyProvider::TWILIO => TwilioGlobalConfig::active() !== null,
            TelephonyProvider::PLIVO => $this->plivoCredentials() !== null,
        };
    }

    private function forTwilio(User $user): array
    {
        try {
            $token = $this->twilioTokens->execute($user);
        } catch (Throwable $e) {
            Log::error('Softphone: failed to issue a Twilio access token', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw new SoftphoneUnavailableException('The softphone is not available right now. Please contact your administrator.');
        }

        return [
            'token' => $token,
            'identity' => self::identity($user),
            'provider' => TelephonyProvider::TWILIO->value,
            'expires_in' => self::TOKEN_TTL_SECONDS,
        ];
    }

    private function forPlivo(User $user): array
    {
        $credentials = $this->plivoCredentials();

        if ($credentials === null) {
            throw new SoftphoneUnavailableException('The softphone is not available right now. Please contact your administrator.');
        }

        // The provider account only; app_id is this installation's Application,
        // not a credential, and is passed where it is actually used.
        $account = ['auth_id' => $credentials['auth_id'], 'auth_token' => $credentials['auth_token']];

        $endpoint = $this->plivoEndpoints->initialize($account)->ensureFor($user, $credentials['app_id']);

        if ($endpoint['success'] !== true) {
            // The provisioner has already logged the cause. Its message can
            // carry provider detail, so it goes no further than that log.
            throw new SoftphoneUnavailableException('The softphone could not be set up. Please try again, or contact your administrator.');
        }

        try {
            $issued = $this->telephony->webrtcToken()
                ->initialize($account)
                ->generateToken(self::identity($user), [
                    'endpoint_username' => $endpoint['username'],
                    'app_id' => $credentials['app_id'],
                    'ttl' => self::TOKEN_TTL_SECONDS,
                ]);
        } catch (Throwable $e) {
            Log::error('Softphone: failed to issue a Plivo access token', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw new SoftphoneUnavailableException('The softphone could not be set up. Please try again, or contact your administrator.');
        }

        // Only the fields the browser reads. The provider's `extra` block is
        // bookkeeping for callers on the server.
        return [
            'token' => $issued['token'],
            'identity' => $issued['identity'],
            'provider' => TelephonyProvider::PLIVO->value,
            'expires_in' => $issued['expires_in'],
        ];
    }

    /**
     * This installation's one Plivo account, read-only — never
     * TelephonyProviderConfig::forProvider(), whose firstOrCreate() must not
     * run from a path any signed-in user can reach.
     *
     * @return array{auth_id: string, auth_token: string}|null
     */
    private function plivoCredentials(): ?array
    {
        $config = TelephonyProviderConfig::where('name', TelephonyProvider::PLIVO->value)->first();
        $authId = $config?->credential('auth_id');
        $authToken = $config?->credential('auth_token');

        if (! $authId || ! $authToken) {
            return null;
        }

        return ['auth_id' => $authId, 'auth_token' => $authToken, 'app_id' => $config->app_id];
    }

    /**
     * The identity the existing Twilio token endpoint has always used, kept
     * for both providers so a user is the same user whichever one issued it.
     */
    private static function identity(User $user): string
    {
        return 'user_'.$user->id;
    }
}
