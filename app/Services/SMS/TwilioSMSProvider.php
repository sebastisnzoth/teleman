<?php

namespace App\Services\SMS;

use App\Models\User;
use App\Models\TwilioCredential;
use App\Models\TwilioGlobalConfig;
use Twilio\Rest\Client;
use Twilio\Security\RequestValidator;
use Exception;
use Illuminate\Support\Facades\Log;

class TwilioSMSProvider implements SMSProviderInterface
{
    private ?Client $client = null;
    private ?TwilioCredential $credential = null;
    private ?string $authToken = null;

    public function initializeForUser(User $user): self
    {
        // Try user-specific credentials first
        $credential = $user->getActiveTwilioCredential();

        if ($credential) {
            $this->credential = $credential;
            $this->authToken = $credential->auth_token;
            $this->client = new Client(
                $credential->account_sid,
                $credential->auth_token
            );
            return $this;
        }

        // Fall back to global Twilio configuration
        $globalConfig = TwilioGlobalConfig::active();

        if (!$globalConfig) {
            throw new Exception('No Twilio configuration found. Please configure Twilio in settings.');
        }

        Log::info('SMS: Using global Twilio config for user', ['user_id' => $user->id]);
        $this->authToken = $globalConfig->getDecryptedAuthToken();
        $this->client = new Client(
            $globalConfig->account_sid,
            $this->authToken
        );

        return $this;
    }

    public function initialize(TwilioCredential $credential): self
    {
        $this->credential = $credential;
        $this->authToken = $credential->auth_token;
        $this->client = new Client(
            $credential->account_sid,
            $credential->auth_token
        );

        return $this;
    }

    public function send(string $from, string $to, string $message, array $options = []): array
    {
        try {
            if (!$this->client) {
                throw new Exception('Twilio client not initialized.');
            }

            $params = [
                'from' => $from,
                'body' => $message,
                // An SMS is charged when its delivery receipt arrives, and
                // Twilio only sends one to a message that asks for it.
                'statusCallback' => route('sms.webhook.status', ['provider' => 'twilio']),
            ];

            if (!empty($options['media_urls'])) {
                $params['mediaUrl'] = $options['media_urls'];
            }

            $twilioMessage = $this->client->messages->create($to, $params);

            return [
                'success' => true,
                'message_id' => $twilioMessage->sid,
                'status' => $twilioMessage->status,
                'cost' => $twilioMessage->price ? abs((float) $twilioMessage->price) : null,
                'num_segments' => $twilioMessage->numSegments ?? 1,
                'error' => null,
            ];
        } catch (Exception $e) {
            Log::error('Twilio SMS send failed', [
                'from' => $from,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message_id' => null,
                'status' => 'failed',
                'cost' => null,
                'num_segments' => 1,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function sendBatch(string $from, array $recipients, array $options = []): array
    {
        $results = [];
        $successCount = 0;
        $failedCount = 0;

        foreach ($recipients as $recipient) {
            $to = $recipient['to'];
            $message = $recipient['message'];
            
            $result = $this->send($from, $to, $message, $options);
            
            if ($result['success']) {
                $successCount++;
            } else {
                $failedCount++;
            }
            
            $results[] = [
                'to' => $to,
                'result' => $result,
            ];
        }

        return [
            'success' => $successCount,
            'failed' => $failedCount,
            'total' => count($recipients),
            'results' => $results,
        ];
    }

    public function getStatus(string $messageId): array
    {
        try {
            if (!$this->client) {
                throw new Exception('Twilio client not initialized.');
            }

            $message = $this->client->messages($messageId)->fetch();

            return [
                'status' => $message->status,
                'delivered_at' => $message->dateSent ? $message->dateSent->format('Y-m-d H:i:s') : null,
                'error' => $message->errorMessage,
                'error_code' => $message->errorCode,
            ];
        } catch (Exception $e) {
            Log::error('Twilio SMS status check failed', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'unknown',
                'delivered_at' => null,
                'error' => $e->getMessage(),
                'error_code' => null,
            ];
        }
    }

    public function validateWebhook(array $payload, string $signature): bool
    {
        try {
            if (!$this->authToken) {
                return false;
            }

            $validator = new RequestValidator($this->authToken);
            $url = request()->fullUrl();

            return $validator->validate($signature, $url, $payload);
        } catch (Exception $e) {
            Log::error('Twilio webhook validation failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function parseIncoming(array $payload): array
    {
        return [
            'from' => $payload['From'] ?? '',
            'to' => $payload['To'] ?? '',
            'body' => $payload['Body'] ?? '',
            'message_id' => $payload['MessageSid'] ?? '',
            'timestamp' => now()->toDateTimeString(),
            'num_segments' => $payload['NumSegments'] ?? 1,
            'media_urls' => $this->extractMediaUrls($payload),
        ];
    }

    private function extractMediaUrls(array $payload): array
    {
        $mediaUrls = [];
        $numMedia = (int) ($payload['NumMedia'] ?? 0);

        for ($i = 0; $i < $numMedia; $i++) {
            if (isset($payload["MediaUrl{$i}"])) {
                $mediaUrls[] = $payload["MediaUrl{$i}"];
            }
        }

        return $mediaUrls;
    }

    public function getName(): string
    {
        return 'twilio';
    }

    public function supportsMMS(): bool
    {
        return true;
    }
}
