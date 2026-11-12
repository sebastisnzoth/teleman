<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Call;
use App\Models\TwilioCredential;
use App\Models\TwilioGlobalConfig;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;
use Twilio\Jwt\AccessToken;
use Twilio\Jwt\Grants\VoiceGrant;
use Twilio\Rest\Client;
use Twilio\Security\RequestValidator;

class TwilioService
{
    private ?Client $client = null;
    private ?TwilioCredential $credential = null;

    /**
     * Initialize Twilio client with the user's credentials.
     *
     * Teleman's model is a single admin-configured Twilio account shared
     * by every customer (TwilioGlobalConfig) — customers never configure
     * telephony themselves. So: try the user's own credential row first
     * (kept for any installation with a hand-inserted one), then fall
     * back to the admin's global configuration. This mirrors
     * TwilioSMSProvider::initializeForUser(), which already resolves SMS
     * credentials this way.
     */
    public function initializeForUser(User $user): self
    {
        $credential = $user->getActiveTwilioCredential();

        if ($credential) {
            $this->credential = $credential;
            $this->client = new Client(
                $credential->account_sid,
                $credential->auth_token  // Use auth token directly (no encryption)
            );

            return $this;
        }

        $globalConfig = TwilioGlobalConfig::active();

        if (!$globalConfig) {
            throw new Exception('Twilio is not configured for this platform. Ask an administrator to configure it in Settings → Twilio.');
        }

        Log::info('Voice: Using global Twilio config for user', ['user_id' => $user->id]);

        // A transient (unsaved) TwilioCredential DTO, not a persisted row —
        // the same pattern AiAgentCallController and TwilioVoiceProvider
        // already use to hand global-config credentials to this class.
        $this->credential = new TwilioCredential([
            'account_sid' => $globalConfig->account_sid,
            'auth_token' => $globalConfig->getDecryptedAuthToken(),
        ]);
        $this->client = new Client(
            $this->credential->account_sid,
            $this->credential->auth_token
        );

        return $this;
    }

    /**
     * Initialize with specific credentials
     */
    public function initialize(TwilioCredential $credential): self
    {
        $this->credential = $credential;
        $this->client = new Client(
            $credential->account_sid,
            $credential->auth_token  // Use auth token directly (no encryption)
        );

        return $this;
    }

    /**
     * Verify Twilio credentials by making a test API call
     */
    public function verifyCredentials(string $accountSid, string $authToken): array
    {
        try {
            $client = new Client($accountSid, $authToken);
            
            // Fetch account details to verify credentials
            $account = $client->api->v2010->accounts($accountSid)->fetch();
            
            // Fetch available phone numbers
            $phoneNumbers = $client->incomingPhoneNumbers->read([], 1);
            
            return [
                'valid' => true,
                'account_name' => $account->friendlyName,
                'account_sid' => $account->sid,
                'status' => $account->status,
                'phone_number' => isset($phoneNumbers[0]) ? $phoneNumbers[0]->phoneNumber : null,
            ];
        } catch (Exception $e) {
            Log::error('Twilio credential verification failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'valid' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get available phone numbers for the account
     */
    public function getPhoneNumbers(): array
    {
        $this->ensureInitialized();

        try {
            $phoneNumbers = $this->client->incomingPhoneNumbers->read();
            
            return array_map(function ($number) {
                return [
                    'sid' => $number->sid,
                    'phone_number' => $number->phoneNumber,
                    'friendly_name' => $number->friendlyName,
                    'capabilities' => $number->capabilities,
                ];
            }, $phoneNumbers);
        } catch (Exception $e) {
            Log::error('Failed to fetch phone numbers', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate access token for Twilio Client SDK (WebRTC)
     */
    public function generateAccessToken(User $user, string $identity = null): string
    {
        $this->ensureInitialized();

        $identity = $identity ?? 'user_' . $user->id;

        // Create or get TwiML Application for outgoing calls
        $twimlAppSid = $this->getOrCreateTwimlApp();

        // Get or create API Key for token generation
        $apiKey = $this->getOrCreateApiKey();

        // Create access token using API Key credentials
        $token = new AccessToken(
            $this->credential->account_sid,              // Account SID
            $apiKey['sid'],                              // API Key SID
            $apiKey['secret'],                           // API Key Secret
            3600,                                        // Token valid for 1 hour
            $identity                                    // Unique identity for this user
        );

        // Create Voice grant for calling
        $voiceGrant = new VoiceGrant();
        $voiceGrant->setOutgoingApplicationSid($twimlAppSid);
        $voiceGrant->setIncomingAllow(true);

        // Add grant to token
        $token->addGrant($voiceGrant);

        return $token->toJWT();
    }

    /**
     * Get or create API Key for token generation
     */
    private function getOrCreateApiKey(): array
    {
        $this->ensureInitialized();

        try {
            // Check if we have stored API Key
            if ($this->credential->api_key_sid && $this->credential->api_key_secret) {
                return [
                    'sid' => $this->credential->api_key_sid,
                    'secret' => decrypt($this->credential->api_key_secret),
                ];
            }

            // Create a new API Key
            $apiKey = $this->client->newKeys->create([
                'friendlyName' => 'Teleman AI - User ' . $this->credential->user_id,
            ]);

            // Store the API Key credentials
            $this->credential->update([
                'api_key_sid' => $apiKey->sid,
                'api_key_secret' => encrypt($apiKey->secret),
            ]);

            return [
                'sid' => $apiKey->sid,
                'secret' => $apiKey->secret,
            ];
        } catch (\Exception $e) {
            logger()->error('Failed to create API Key: ' . $e->getMessage());
            throw new \Exception('Failed to initialize API key for calling: ' . $e->getMessage());
        }
    }

    /**
     * Get or create a TwiML Application for browser calls
     */
    private function getOrCreateTwimlApp(): string
    {
        $this->ensureInitialized();

        try {
            $webhookUrl = env('TWILIO_WEBHOOK_URL', config('app.url'));
            $voiceUrl = $webhookUrl . '/twiml/manual-call';

            // Check if we have a stored TwiML app SID
            if ($this->credential->twiml_app_sid) {
                // Always update the existing app to ensure correct configuration
                try {
                    logger()->info('Updating TwiML app configuration', [
                        'app_sid' => $this->credential->twiml_app_sid,
                        'voice_url' => $voiceUrl,
                        'voice_method' => 'POST',
                    ]);
                    
                    $this->client->applications($this->credential->twiml_app_sid)->update([
                        'voiceUrl' => $voiceUrl,
                        'voiceMethod' => 'POST',
                    ]);
                    
                    logger()->info('TwiML app updated successfully');
                } catch (\Exception $e) {
                    logger()->error('Failed to update TwiML app, creating new one', [
                        'error' => $e->getMessage()
                    ]);
                    // If update fails, create a new app
                    $this->credential->update(['twiml_app_sid' => null]);
                    return $this->getOrCreateTwimlApp();
                }
                return $this->credential->twiml_app_sid;
            }

            // Create a new TwiML application
            $appName = 'Teleman AI - ' . config('app.name');

            logger()->info('Creating new TwiML app', [
                'name' => $appName,
                'voice_url' => $voiceUrl,
                'voice_method' => 'POST',
            ]);

            $application = $this->client->applications->create([
                'friendlyName' => $appName,
                'voiceUrl' => $voiceUrl,
                'voiceMethod' => 'POST',
            ]);

            // Store the app SID
            $this->credential->update(['twiml_app_sid' => $application->sid]);

            logger()->info('TwiML app created successfully', [
                'app_sid' => $application->sid
            ]);

            return $application->sid;
        } catch (\Exception $e) {
            logger()->error('Failed to create TwiML app: ' . $e->getMessage());
            throw new \Exception('Failed to initialize browser calling: ' . $e->getMessage());
        }
    }

    /**
     * Initiate an outbound call
     */
    public function initiateCall(
        string $to,
        string $from,
        string $twimlUrl,
        array $options = []
    ): array {
        $this->ensureInitialized();

        try {
            $callOptions = [
                'url' => $twimlUrl,
                'method' => 'POST',
                'statusCallback' => $options['statusCallback'] ?? null,
                'statusCallbackMethod' => 'POST',
                'statusCallbackEvent' => ['initiated', 'ringing', 'answered', 'completed'],
                'recordingStatusCallback' => $options['recordingCallback'] ?? null,
                'timeout' => $options['timeout'] ?? 60,
                'machineDetection' => $options['machineDetection'] ?? 'Enable',
            ];

            // An explicit null means "do not send this at all", as opposed
            // to an absent key, which keeps the default above — the same
            // opt-out convention TwilioCallXmlGenerator already uses for
            // attributes whose absence and whose default are different
            // things. Every caller that existed before this existed passes
            // neither key and is unaffected.
            //
            // Null is all it reads: leaving a parameter out and choosing
            // a different value for it are separate powers, and only the
            // first one is wanted here. statusCallbackEvent's own list is
            // therefore still fixed above rather than caller-supplied.
            //
            // It is here for calls where the defaults are actively wrong:
            // answering-machine detection makes Twilio hold the answer
            // document back until detection finishes, so a call placed to
            // connect two people already on the line — the call queue's
            // agent leg — would answer into silence.
            foreach (['statusCallbackEvent', 'machineDetection'] as $optional) {
                if (array_key_exists($optional, $options) && $options[$optional] === null) {
                    unset($callOptions[$optional]);
                }
            }
            
            // Only add record parameter if it's provided and is a string value
            if (isset($options['record']) && is_string($options['record'])) {
                $callOptions['record'] = $options['record'];
            }

            $call = $this->client->calls->create(
                $to,
                $from,
                $callOptions
            );

            return [
                'success' => true,
                'call_sid' => $call->sid,
                'status' => $call->status,
                'to' => $call->to,
                'from' => $call->from,
            ];
        } catch (Exception $e) {
            Log::error('Failed to initiate call', [
                'to' => $to,
                'from' => $from,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get call details from Twilio
     */
    public function getCallDetails(string $callSid): ?object
    {
        $this->ensureInitialized();

        try {
            return $this->client->calls($callSid)->fetch();
        } catch (Exception $e) {
            Log::error('Failed to fetch call details', [
                'call_sid' => $callSid,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Update call status (hangup, cancel, etc.)
     */
    public function updateCall(string $callSid, string $status): bool
    {
        $this->ensureInitialized();

        try {
            $this->client->calls($callSid)->update([
                'status' => $status,
            ]);
            return true;
        } catch (Exception $e) {
            Log::error('Failed to update call', [
                'call_sid' => $callSid,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get recording details
     */
    public function getRecording(string $recordingSid): ?object
    {
        $this->ensureInitialized();

        try {
            return $this->client->recordings($recordingSid)->fetch();
        } catch (Exception $e) {
            Log::error('Failed to fetch recording', [
                'recording_sid' => $recordingSid,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Delete recording
     */
    public function deleteRecording(string $recordingSid): bool
    {
        $this->ensureInitialized();

        try {
            $this->client->recordings($recordingSid)->delete();
            return true;
        } catch (Exception $e) {
            Log::error('Failed to delete recording', [
                'recording_sid' => $recordingSid,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Validate Twilio webhook signature
     */
    public function validateWebhookSignature(
        string $signature,
        string $url,
        array $params
    ): bool {
        $this->ensureInitialized();

        $validator = new RequestValidator($this->credential->auth_token);
        
        return $validator->validate($signature, $url, $params);
    }

    /**
     * Generate TwiML for text-to-speech
     */
    public function generateTtsUrl(
        string $text,
        array $options = []
    ): string {
        $voice = $options['voice'] ?? 'Polly.Joanna';
        $language = $options['language'] ?? 'en-US';
        
        return route('twilio.twiml.tts', [
            'text' => base64_encode($text),
            'voice' => $voice,
            'language' => $language,
        ]);
    }

    /**
     * Generate TwiML for voice recording playback
     */
    public function generateVoiceUrl(string $audioUrl): string
    {
        return route('twilio.twiml.voice', [
            'audio' => base64_encode($audioUrl),
        ]);
    }

    /**
     * Calculate estimated call cost
     */
    public function estimateCallCost(string $to, int $durationSeconds): float
    {
        // Simplified cost calculation (US rates)
        // In production, use Twilio Pricing API
        $costPerMinute = 0.013; // $0.013 per minute for US
        $minutes = ceil($durationSeconds / 60);
        
        return round($minutes * $costPerMinute, 4);
    }

    /**
     * Check if client is initialized
     */
    private function ensureInitialized(): void
    {
        if (!$this->client || !$this->credential) {
            throw new Exception('Twilio service not initialized. Call initialize() or initializeForUser() first.');
        }
    }

    /**
     * Get the Twilio client instance
     */
    public function getClient(): Client
    {
        $this->ensureInitialized();
        return $this->client;
    }

    /**
     * Get the current credential
     */
    public function getCredential(): TwilioCredential
    {
        $this->ensureInitialized();
        return $this->credential;
    }

    /**
     * Fetch latest Error Logs/alerts from Monitor API
     */
    public function fetchErrorLogs(int $limit = 10): array
    {
        $this->ensureInitialized();

        try {
            $alerts = $this->client->monitor->v1->alerts
                ->read(['limit' => $limit]);

            $errorLogs = [];

            foreach ($alerts as $alert) {
                $errorLogs[] = [
                    'sid' => $alert->sid,
                    'error_code' => $alert->errorCode,
                    'log_level' => $alert->logLevel,
                    'alert_text' => $alert->alertText,
                    'request_method' => $alert->requestMethod ?? null,
                    'request_url' => $alert->requestUrl ?? null,
                    'request_variables' => $alert->requestVariables ?? null,
                    'response_body' => $alert->responseBody ?? null,
                    'response_headers' => $alert->responseHeaders ?? null,
                    'service_sid' => $alert->serviceSid ?? null,
                    'resource_sid' => $alert->resourceSid ?? null,
                    'date_created' => $alert->dateCreated ? $alert->dateCreated->format('Y-m-d H:i:s') : null,
                    'date_generated' => $alert->dateGenerated ? $alert->dateGenerated->format('Y-m-d H:i:s') : null,
                    'date_updated' => $alert->dateUpdated ? $alert->dateUpdated->format('Y-m-d H:i:s') : null,
                ];
            }

            return [
                'success' => true,
                'errors' => $errorLogs,
                'count' => count($errorLogs),
            ];
        } catch (Exception $e) {
            Log::error('Failed to fetch Error Logs', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'errors' => [],
                'count' => 0,
            ];
        }
    }

    /**
     * Fetch real-time call details from Twilio API
     */
    public function fetchCallDetails(string $callSid): ?array
    {
        $this->ensureInitialized();

        try {
            $call = $this->client->calls($callSid)->fetch();

            // Fetch recordings for this call
            $recordings = [];
            try {
                $recordingList = $this->client->recordings->read(['callSid' => $callSid]);
                foreach ($recordingList as $recording) {
                    $recordings[] = [
                        'sid' => $recording->sid,
                        'url' => 'https://api.twilio.com' . $recording->uri,
                        'duration' => $recording->duration,
                        'status' => $recording->status,
                    ];
                }
            } catch (Exception $e) {
                Log::warning('Failed to fetch recordings for call', [
                    'call_sid' => $callSid,
                    'error' => $e->getMessage(),
                ]);
            }

            return [
                'sid' => $call->sid,
                'status' => $call->status,
                'duration' => $call->duration,
                'from' => $call->from,
                'to' => $call->to,
                'direction' => $call->direction,
                'price' => $call->price ? abs((float) $call->price) : 0,
                'price_unit' => $call->priceUnit ?? 'USD',
                'start_time' => $call->startTime ? $call->startTime->format('Y-m-d H:i:s') : null,
                'end_time' => $call->endTime ? $call->endTime->format('Y-m-d H:i:s') : null,
                'answered_by' => $call->answeredBy,
                'recordings' => $recordings,
            ];
        } catch (Exception $e) {
            Log::error('Failed to fetch call details from Twilio', [
                'call_sid' => $callSid,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
