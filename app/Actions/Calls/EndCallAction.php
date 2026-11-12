<?php

declare(strict_types=1);

namespace App\Actions\Calls;

use App\Enums\TelephonyProvider;
use App\Models\Call;
use App\Models\TelephonyProviderConfig;
use App\Models\TwilioGlobalConfig;
use App\Services\Telephony\TelephonyManager;
use Exception;
use Illuminate\Support\Facades\Log;

class EndCallAction
{
    public function __construct(
        private readonly TelephonyManager $telephony,
    ) {}

    /**
     * End an active call through the installation's locked telephony
     * provider.
     *
     * Reads provider_call_sid first, falling back to twilio_call_sid only
     * for a row written before that column existed — every row this task
     * writes keeps the two mirrored, so in practice either holds the same
     * value. On Plivo this identifier may still be the RequestUUID
     * makeCall() returned at creation, not yet the real CallUUID (the
     * first status webhook hasn't arrived) — see hangupCall() below for
     * why that is not a special case here.
     */
    public function execute(Call $call): bool
    {
        if (! $call->isInProgress()) {
            throw new Exception('Call is not in progress and cannot be ended.');
        }

        $provider = $this->telephony->provider();
        $credentials = $this->resolveCredentials($provider);
        $identifier = (string) ($call->provider_call_sid ?: $call->twilio_call_sid);

        try {
            // hangupCall() is where the Twilio/Plivo difference this
            // method would otherwise need to know about is absorbed:
            // PlivoVoiceProvider::hangupCall() already tries the CallUUID
            // endpoint first and falls back to cancelling the queued
            // request (Account/{id}/Request/{uuid}/) when that fails —
            // exactly the fallback a RequestUUID-only identifier needs.
            // Nothing here has to know which kind of identifier it is
            // holding.
            $success = $this->telephony->voice()->initialize($credentials)->hangupCall($identifier);

            if (! $success) {
                Log::error('Failed to end call', [
                    'call_id' => $call->id,
                    'provider' => $provider->value,
                    'identifier' => $identifier,
                ]);

                return false;
            }

            $call->update([
                'status' => 'canceled',
                'ended_at' => now(),
            ]);

            Log::info('Call ended', [
                'call_id' => $call->id,
                'provider' => $provider->value,
                'identifier' => $identifier,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to end call', [
                'call_id' => $call->id,
                'provider' => $provider->value,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Twilio: unchanged from before this task — the admin's global
     * configuration only, no per-customer fallback (see
     * InitiateCallAction, which never had one either; that is a
     * pre-existing difference from MakeCampaignCallJob's resolution, not
     * something this task introduces).
     *
     * Plivo: this installation's one configured account
     * (TelephonyProviderConfig, name 'plivo'), read-only — never
     * forProvider()'s firstOrCreate(), which must not run from a path
     * that can be reached with no admin having configured anything yet.
     *
     * @return array<string, string>
     */
    private function resolveCredentials(TelephonyProvider $provider): array
    {
        if ($provider === TelephonyProvider::PLIVO) {
            $config = TelephonyProviderConfig::where('name', 'plivo')->first();
            $authId = $config?->credential('auth_id');
            $authToken = $config?->credential('auth_token');

            if (! $authId || ! $authToken) {
                throw new Exception('Plivo is not configured for this platform. Ask an administrator to configure it in Settings → Telephony.');
            }

            return ['auth_id' => $authId, 'auth_token' => $authToken];
        }

        $config = TwilioGlobalConfig::active();

        if (! $config) {
            throw new Exception('No active Twilio configuration found.');
        }

        return [
            'account_sid' => $config->account_sid,
            'auth_token' => $config->getDecryptedAuthToken(),
        ];
    }
}
