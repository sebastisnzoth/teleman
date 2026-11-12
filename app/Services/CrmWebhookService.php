<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CrmIntegration;
use App\Models\CrmWebhookLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class CrmWebhookService
{
    /**
     * Dispatch webhook to all active integrations subscribed to this event
     */
    public function dispatch(string $eventType, array $data, ?int $userId = null): void
    {
        // Get all active integrations subscribed to this event
        $query = CrmIntegration::active()->subscribedTo($eventType);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $integrations = $query->get();

        if ($integrations->isEmpty()) {
            Log::debug("No CRM integrations found for event: {$eventType}");
            return;
        }

        // Dispatch to each integration
        foreach ($integrations as $integration) {
            $this->sendWebhook($integration, $eventType, $data);
        }
    }

    /**
     * Send webhook to a specific integration
     */
    public function sendWebhook(CrmIntegration $integration, string $eventType, array $data): bool
    {
        $payload = array_merge($data, [
            'event' => $eventType,
            'integration_id' => $integration->id,
        ]);

        try {
            // Prepare HTTP request
            $request = Http::timeout(30)
                ->withHeaders($this->getAuthHeaders($integration));

            // Add HMAC signature if configured
            if ($integration->auth_type === 'hmac' && isset($integration->auth_credentials['secret'])) {
                $signature = $this->signPayload($payload, $integration->auth_credentials['secret']);
                $request->withHeaders([
                    'X-Teleman-Signature' => $signature,
                    'X-Teleman-Timestamp' => time(),
                ]);
            }

            // Send the webhook
            $response = $request->post($integration->webhook_url, $payload);

            // Log the attempt
            $this->logWebhook(
                $integration,
                $eventType,
                $payload,
                $response->status(),
                $response->body()
            );

            // Update integration stats
            $integration->incrementTriggers();

            if ($response->successful()) {
                $integration->clearError();
                Log::info("CRM webhook sent successfully", [
                    'integration_id' => $integration->id,
                    'event' => $eventType,
                    'status' => $response->status(),
                ]);
                return true;
            } else {
                $error = "HTTP {$response->status()}: {$response->body()}";
                $integration->recordError($error);
                Log::warning("CRM webhook failed", [
                    'integration_id' => $integration->id,
                    'event' => $eventType,
                    'error' => $error,
                ]);
                return false;
            }
        } catch (\Exception $e) {
            // Log the error
            $this->logWebhook(
                $integration,
                $eventType,
                $payload,
                null,
                $e->getMessage()
            );

            $integration->recordError($e->getMessage());

            Log::error("CRM webhook exception", [
                'integration_id' => $integration->id,
                'event' => $eventType,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get authentication headers based on auth type
     */
    private function getAuthHeaders(CrmIntegration $integration): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'Teleman-CRM-Webhook/1.0',
        ];

        switch ($integration->auth_type) {
            case 'bearer':
                if (isset($integration->auth_credentials['token'])) {
                    $headers['Authorization'] = 'Bearer ' . $integration->auth_credentials['token'];
                }
                break;

            case 'api_key':
                if (isset($integration->auth_credentials['key']) && isset($integration->auth_credentials['value'])) {
                    $headers[$integration->auth_credentials['key']] = $integration->auth_credentials['value'];
                }
                break;
        }

        return $headers;
    }

    /**
     * Sign payload with HMAC-SHA256
     */
    private function signPayload(array $payload, string $secret): string
    {
        $jsonPayload = json_encode($payload);
        return hash_hmac('sha256', $jsonPayload, $secret);
    }

    /**
     * Log webhook attempt
     */
    private function logWebhook(
        CrmIntegration $integration,
        string $eventType,
        array $payload,
        ?int $status,
        ?string $responseBody
    ): void {
        CrmWebhookLog::create([
            'user_id' => $integration->user_id,
            'crm_integration_id' => $integration->id,
            'event_type' => $eventType,
            'payload' => $payload,
            'response_status' => $status,
            'response_body' => $responseBody ? substr($responseBody, 0, 5000) : null, // Limit response body size
            'triggered_at' => now(),
        ]);
    }

    /**
     * Test webhook with sample payload
     */
    public function testWebhook(CrmIntegration $integration): array
    {
        $samplePayload = [
            'event' => 'test_webhook',
            'timestamp' => now()->toIso8601String(),
            'user_id' => $integration->user_id,
            'data' => [
                'message' => 'This is a test webhook from Teleman',
                'integration_name' => $integration->name,
                'test_time' => now()->toDateTimeString(),
            ],
        ];

        try {
            $request = Http::timeout(10)
                ->withHeaders($this->getAuthHeaders($integration));

            if ($integration->auth_type === 'hmac' && isset($integration->auth_credentials['secret'])) {
                $signature = $this->signPayload($samplePayload, $integration->auth_credentials['secret']);
                $request->withHeaders([
                    'X-Teleman-Signature' => $signature,
                    'X-Teleman-Timestamp' => time(),
                ]);
            }

            $response = $request->post($integration->webhook_url, $samplePayload);

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'response' => $response->body(),
                'message' => $response->successful() 
                    ? 'Webhook test successful!' 
                    : 'Webhook test failed: HTTP ' . $response->status(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'status' => null,
                'response' => null,
                'message' => 'Webhook test failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Retry a failed webhook
     */
    public function retryWebhook(CrmWebhookLog $log): bool
    {
        if (!$log->crmIntegration) {
            Log::warning("Cannot retry webhook: integration not found", [
                'log_id' => $log->id,
            ]);
            return false;
        }

        return $this->sendWebhook(
            $log->crmIntegration,
            $log->event_type,
            $log->payload
        );
    }
}
