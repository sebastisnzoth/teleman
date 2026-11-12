<?php

declare(strict_types=1);

namespace App\Services\Telephony\Twilio;

use App\Contracts\Telephony\VoiceProviderInterface;
use App\Models\TwilioCredential;
use App\Services\TwilioService;
use Exception;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;
use Twilio\Security\RequestValidator;

/**
 * Twilio implementation of the voice provider interface.
 *
 * makeCall() delegates to the existing App\Services\TwilioService::initiateCall()
 * instead of calling the Twilio REST API directly, so the statusCallback
 * wiring, parameter shaping and error handling stay the literal same code
 * that already ships — see TwilioService for the actual call-creation logic.
 * Every other method here talks to the Twilio client directly, matching
 * what TwilioService itself does for the same operations.
 */
class TwilioVoiceProvider implements VoiceProviderInterface
{
    private ?Client $client = null;

    private array $credentials = [];

    public function initialize(array $credentials): static
    {
        $this->credentials = $credentials;
        $this->client = new Client(
            $credentials['account_sid'],
            $credentials['auth_token']
        );

        return $this;
    }

    public function makeCall(string $to, string $from, string $answerUrl, array $options = []): array
    {
        try {
            $this->ensureInitialized();

            // Resolved through the container, not `new TwilioService` —
            // TwilioService::class is bound as a singleton
            // (AppServiceProvider) specifically so tests can override it
            // with $this->app->instance(TwilioService::class, $fake) (see
            // GlobalCredentialResolutionTest). A raw `new` bypasses the
            // container entirely and would silently reach the real Twilio
            // API instead of a test's fake.
            $twilioService = app(TwilioService::class)->initialize(new TwilioCredential([
                'account_sid' => $this->credentials['account_sid'] ?? '',
                'auth_token' => $this->credentials['auth_token'] ?? '',
            ]));

            $result = $twilioService->initiateCall($to, $from, $answerUrl, $options);

            return [
                'success' => $result['success'],
                'call_id' => $result['call_sid'] ?? null,
                // Twilio's create-call response is synchronous — the SID
                // returned above already is the real call identifier, so
                // there is no separate request identifier to report. Kept
                // null (not omitted) to match VoiceProviderInterface's
                // declared shape; see PlivoVoiceProvider::makeCall() for
                // the provider that actually populates this.
                'request_uuid' => null,
                'status' => $result['status'] ?? null,
                'error' => $result['error'] ?? null,
            ];
        } catch (Exception $e) {
            Log::error('Twilio: Failed to make call', [
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

    public function getCallDetails(string $callId): ?array
    {
        try {
            $this->ensureInitialized();

            $call = $this->client->calls($callId)->fetch();

            $recordings = [];
            try {
                $recordingList = $this->client->recordings->read(['callSid' => $callId]);
                foreach ($recordingList as $recording) {
                    $recordings[] = [
                        'id' => $recording->sid,
                        'url' => 'https://api.twilio.com'.$recording->uri,
                        'duration' => (int) $recording->duration,
                        'status' => $recording->status,
                    ];
                }
            } catch (Exception $e) {
                Log::warning('Twilio: Failed to fetch recordings', [
                    'call_id' => $callId,
                    'error' => $e->getMessage(),
                ]);
            }

            return [
                'call_id' => $call->sid,
                'status' => $call->status,
                'duration' => $call->duration ? (int) $call->duration : null,
                'from' => $call->from,
                'to' => $call->to,
                'direction' => $call->direction,
                'price' => $call->price ? abs((float) $call->price) : null,
                'price_unit' => $call->priceUnit ?? 'USD',
                'start_time' => $call->startTime?->format('Y-m-d H:i:s'),
                'end_time' => $call->endTime?->format('Y-m-d H:i:s'),
                'answered_by' => $call->answeredBy,
                'recordings' => $recordings,
            ];
        } catch (Exception $e) {
            Log::error('Twilio: Failed to fetch call details', [
                'call_id' => $callId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function updateCall(string $callId, array $params): bool
    {
        try {
            $this->ensureInitialized();
            $this->client->calls($callId)->update($params);

            return true;
        } catch (Exception $e) {
            Log::error('Twilio: Failed to update call', [
                'call_id' => $callId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function hangupCall(string $callId): bool
    {
        return $this->updateCall($callId, ['status' => 'completed']);
    }

    public function getRecording(string $recordingId): ?array
    {
        try {
            $this->ensureInitialized();
            $recording = $this->client->recordings($recordingId)->fetch();

            return [
                'id' => $recording->sid,
                'url' => 'https://api.twilio.com'.$recording->uri,
                'duration' => (int) $recording->duration,
                'status' => $recording->status,
            ];
        } catch (Exception $e) {
            Log::error('Twilio: Failed to fetch recording', [
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
            $this->client->recordings($recordingId)->delete();

            return true;
        } catch (Exception $e) {
            Log::error('Twilio: Failed to delete recording', [
                'recording_id' => $recordingId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function verifyCredentials(array $credentials): array
    {
        try {
            $client = new Client($credentials['account_sid'], $credentials['auth_token']);
            $account = $client->api->v2010->accounts($credentials['account_sid'])->fetch();

            return [
                'valid' => true,
                'account_name' => $account->friendlyName,
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

    public function validateWebhook(array $payload, string $signature, string $url): bool
    {
        try {
            $validator = new RequestValidator($this->credentials['auth_token'] ?? '');

            return $validator->validate($signature, $url, $payload);
        } catch (Exception $e) {
            Log::error('Twilio: Webhook validation failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function estimateCallCost(string $to, int $durationSeconds): float
    {
        $costPerMinute = config('twilio.pricing.api_cost_per_minute', 0.013);
        $minutes = ceil($durationSeconds / 60);

        return round($minutes * $costPerMinute, 4);
    }

    public function getName(): string
    {
        return 'twilio';
    }

    private function ensureInitialized(): void
    {
        if (! $this->client) {
            throw new Exception('Twilio voice provider not initialized. Call initialize() first.');
        }
    }
}
