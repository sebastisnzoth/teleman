<?php

declare(strict_types=1);

namespace App\Actions\Calls;

use App\Enums\TelephonyProvider;
use App\Models\Call;
use App\Models\CampaignContact;
use App\Models\PhoneNumber;
use App\Models\TelephonyProviderConfig;
use App\Models\TrunkPhoneNumber;
use App\Models\TwilioGlobalConfig;
use App\Models\User;
use App\Services\Telephony\TelephonyManager;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class InitiateCallAction
{
    public function __construct(
        private readonly TelephonyManager $telephony,
    ) {}

    /**
     * Initiate a manual outbound call through the installation's locked
     * telephony provider.
     *
     * @param  User  $user  The user initiating the call
     * @param  array  $data  Call data including:
     *                       - to_number: Required - destination phone number
     *                       - from_number: Optional - caller ID (defaults to env TWILIO_PHONE_NUMBER)
     *                       - enable_recording: Optional - whether to record the call
     *                       - use_device_sdk: Optional - true for browser calls, false for API calls (default: true)
     */
    public function execute(User $user, array $data): Call
    {
        return DB::transaction(function () use ($user, $data) {
            $provider = $this->telephony->provider();

            // Resolved up front, exactly like Twilio's own check below —
            // fail before Call::create() runs so a misconfigured
            // installation never leaves an orphan row behind.
            $plivoCredentials = $provider === TelephonyProvider::PLIVO
                ? $this->resolvePlivoCredentials()
                : null;

            // Get global Twilio configuration. Skipped entirely when
            // locked to Plivo: this used to run unconditionally, which
            // meant even the Device SDK branch below — which never
            // touches Twilio at all — 500'd on a Plivo-locked
            // installation with no TwilioGlobalConfig row.
            $config = $provider === TelephonyProvider::TWILIO ? TwilioGlobalConfig::active() : null;

            if ($provider === TelephonyProvider::TWILIO && ! $config) {
                throw new Exception('No active Twilio configuration found. Please run: php artisan twilio:configure');
            }

            // Determine caller ID (from number) - use configured phone number
            $fromNumber = $data['from_number'] ?? env('TWILIO_PHONE_NUMBER');

            if (! $fromNumber) {
                throw new Exception('No phone number available. Please assign a phone number first.');
            }

            // Detect number source: check if from_number belongs to SIP trunk or Twilio direct
            $numberSource = $data['number_source'] ?? null;

            if (! $numberSource) {
                // Auto-detect source by checking database
                $isSipTrunk = TrunkPhoneNumber::where('phone_number', $fromNumber)
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->where('assigned_to', 'softphone')
                    ->exists();

                $numberSource = $isSipTrunk ? 'sip_trunk' : 'twilio_direct';
            }

            // Validate the from_number belongs to this user
            if ($numberSource === 'sip_trunk') {
                $valid = TrunkPhoneNumber::where('phone_number', $fromNumber)
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->where('assigned_to', 'softphone')
                    ->exists();
            } else {
                $valid = PhoneNumber::where('number', $fromNumber)
                    ->where('user_id', $user->id)
                    ->where('status', 'assigned')
                    ->exists();
            }

            if (! $valid) {
                throw new Exception('Phone number is not assigned to your account.');
            }

            // Mark campaign contact as calling before creating the call record
            $campaignContact = null;
            if (! empty($data['campaign_contact_id'])) {
                $campaignContact = CampaignContact::find($data['campaign_contact_id']);
                if ($campaignContact) {
                    $campaignContact->markAsCalling();
                    $campaignContact->incrementAttempts();
                }
            }

            // Create call record for tracking
            $call = Call::create([
                'user_id' => $user->id,
                'contact_id' => $data['contact_id'] ?? null,
                'campaign_contact_id' => $campaignContact?->id,
                'call_type' => 'manual',
                'direction' => 'outbound',
                'from_number' => $fromNumber,
                'to_number' => $data['to_number'],
                'status' => 'initiated',
                'enable_recording' => $data['enable_recording'] ?? false,
                'started_at' => now(),
                'provider' => $provider->value,
            ]);

            // Check if this is a browser Device SDK call or API call
            $useDeviceSdk = $data['use_device_sdk'] ?? true;

            if ($useDeviceSdk) {
                // For browser calls: Just return the call record
                // The browser Device SDK will handle the actual provider connection
                // The answer document will receive the call and use the call_id to look up details
                Log::info('Call record created for Device SDK', [
                    'call_id' => $call->id,
                    'provider' => $provider->value,
                    'to' => $data['to_number'],
                    'from' => $fromNumber,
                    'source' => $numberSource,
                ]);

                return $call;
            }

            if ($provider === TelephonyProvider::PLIVO) {
                return $this->placeViaPlivo($call, $data, $fromNumber, $plivoCredentials);
            }

            // For API calls (campaigns, etc.): Create the Twilio call via API.
            // Deliberately NOT routed through TelephonyManager::voice() —
            // TwilioVoiceProvider::makeCall() delegates to
            // TwilioService::initiateCall(), whose parameter shape differs
            // from this hand-rolled request (it always sends
            // machineDetection, and never sets
            // recordingStatusCallbackMethod), so wiring this call through
            // it would change the live Twilio request this method sends
            // today. Left exactly as it was rather than risk that.
            $client = new Client(
                $config->account_sid,
                $config->getDecryptedAuthToken()
            );

            // Prepare TwiML URL for Softphone
            $twimlUrl = $config->webhook_url.'/twiml/manual-call?call_id='.$call->id;

            // Prepare webhook URLs
            $statusCallback = $config->webhook_url.'/webhooks/twilio/calls/'.$call->id.'/status';
            $recordingCallback = ($data['enable_recording'] ?? false)
                ? $config->webhook_url.'/webhooks/twilio/calls/'.$call->id.'/recording'
                : null;

            try {
                // Initiate call via Twilio API
                $twilioCall = $client->calls->create(
                    $data['to_number'], // To
                    $fromNumber,        // From
                    [
                        'url' => $twimlUrl,
                        'method' => 'POST',
                        'statusCallback' => $statusCallback,
                        'statusCallbackMethod' => 'POST',
                        'statusCallbackEvent' => ['initiated', 'ringing', 'answered', 'completed'],
                        'recordingStatusCallback' => $recordingCallback,
                        'recordingStatusCallbackMethod' => 'POST',
                        'timeout' => 60,
                    ]
                );

                // Update call with Twilio SID. Mirrored into
                // provider_call_sid too, the provider-neutral column new
                // code should read going forward — see EndCallAction.
                $call->update([
                    'twilio_call_sid' => $twilioCall->sid,
                    'provider_call_sid' => $twilioCall->sid,
                    'status' => 'initiated',
                ]);

                Log::info('API call initiated', [
                    'call_id' => $call->id,
                    'twilio_call_sid' => $twilioCall->sid,
                    'to' => $data['to_number'],
                    'from' => $fromNumber,
                ]);

                return $call->fresh();
            } catch (Exception $e) {
                $call->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'ended_at' => now(),
                ]);

                Log::error('Call initiation failed', [
                    'call_id' => $call->id,
                    'error' => $e->getMessage(),
                    'to' => $data['to_number'],
                ]);

                throw new Exception('Failed to initiate call: '.$e->getMessage());
            }
        });
    }

    /**
     * Place the call on Plivo through the abstraction and write the row.
     *
     * makeCall() only ever hands back a RequestUUID here (Plivo's
     * create-call API is asynchronous — see PlivoVoiceProvider::makeCall()'s
     * docblock), not the CallUUID every other operation needs. It is
     * written into both SID columns anyway — the same "write what you
     * have and nothing more" convention MakeCampaignCallJob and
     * CampaignController follow — so the row is findable by call_id (the
     * webhook route parameter, never the SID) and hangupable via
     * PlivoVoiceProvider::hangupCall()'s own dual-endpoint fallback,
     * before the first status webhook (UpdateCallStatusAction) replaces
     * it with the real CallUUID.
     *
     * @param  array<string, string>  $credentials
     */
    private function placeViaPlivo(Call $call, array $data, string $fromNumber, array $credentials): Call
    {
        $answerUrl = route('plivo-xml.manual.call').'?call_id='.$call->id;
        $statusCallback = route('webhooks.plivo.call.status', ['call_id' => $call->id]);

        try {
            $result = $this->telephony->voice()->initialize($credentials)->makeCall(
                $data['to_number'],
                $fromNumber,
                $answerUrl,
                [
                    'statusCallback' => $statusCallback,
                    'timeout' => 60,
                ]
            );

            if (! $result['success']) {
                throw new Exception($result['error'] ?? 'Unknown error');
            }

            $call->update([
                'twilio_call_sid' => $result['call_id'],
                'provider_call_sid' => $result['call_id'],
                // Permanent record of the RequestUUID makeCall() returned,
                // so Call::shouldAdoptProviderCallId() can tell "still the
                // placeholder" from "confirmed" without looking at status.
                'provider_request_id' => $result['request_uuid'],
                'status' => 'initiated',
            ]);

            Log::info('API call initiated', [
                'call_id' => $call->id,
                'provider' => 'plivo',
                'provider_call_sid' => $result['call_id'],
                'to' => $data['to_number'],
                'from' => $fromNumber,
            ]);

            return $call->fresh();
        } catch (Exception $e) {
            $call->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'ended_at' => now(),
            ]);

            Log::error('Call initiation failed', [
                'call_id' => $call->id,
                'provider' => 'plivo',
                'error' => $e->getMessage(),
                'to' => $data['to_number'],
            ]);

            throw new Exception('Failed to initiate call: '.$e->getMessage());
        }
    }

    /**
     * This installation's one configured Plivo account
     * (TelephonyProviderConfig, name 'plivo'), read-only — never
     * forProvider()'s firstOrCreate(), which must not run from a path
     * that can be reached before any admin has configured anything.
     * Mirrors TwilioGlobalConfig::active()'s own null-check above: both
     * fail before Call::create() runs, so a misconfigured installation
     * never leaves an orphan row behind.
     *
     * @return array<string, string>
     */
    private function resolvePlivoCredentials(): array
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
