<?php

declare(strict_types=1);

namespace App\Services\Telephony\Twilio;

use App\Contracts\Telephony\WebhookNormalizerInterface;

/**
 * Normalizes Twilio webhook payloads into the standard format.
 *
 * Twilio parameters: CallSid, From, To, CallStatus, Direction, etc.
 */
class TwilioWebhookNormalizer implements WebhookNormalizerInterface
{
    public function normalizeCallEvent(array $payload): array
    {
        return [
            'call_id' => $payload['CallSid'] ?? '',
            'from' => $payload['From'] ?? '',
            'to' => $payload['To'] ?? '',
            'direction' => $payload['Direction'] ?? 'outbound-api',
            'status' => $payload['CallStatus'] ?? 'initiated',
            'provider' => 'twilio',
            'raw' => $payload,
        ];
    }

    public function normalizeStatusCallback(array $payload): array
    {
        return [
            'call_id' => $payload['CallSid'] ?? '',
            'status' => $this->mapCallStatus($payload['CallStatus'] ?? ''),
            'duration' => isset($payload['CallDuration']) ? (int) $payload['CallDuration'] : null,
            'price' => isset($payload['Price']) ? abs((float) $payload['Price']) : null,
            'answered_by' => $payload['AnsweredBy'] ?? null,
            'provider' => 'twilio',
            'raw' => $payload,
        ];
    }

    public function normalizeRecordingCallback(array $payload): array
    {
        return [
            'call_id' => $payload['CallSid'] ?? '',
            'recording_id' => $payload['RecordingSid'] ?? '',
            'recording_url' => $payload['RecordingUrl'] ?? null,
            'duration' => isset($payload['RecordingDuration']) ? (int) $payload['RecordingDuration'] : null,
            'status' => $payload['RecordingStatus'] ?? 'completed',
            'provider' => 'twilio',
            'raw' => $payload,
        ];
    }

    public function normalizeIncomingSms(array $payload): array
    {
        $mediaUrls = [];
        $numMedia = (int) ($payload['NumMedia'] ?? 0);
        for ($i = 0; $i < $numMedia; $i++) {
            if (isset($payload["MediaUrl{$i}"])) {
                $mediaUrls[] = $payload["MediaUrl{$i}"];
            }
        }

        return [
            'message_id' => $payload['MessageSid'] ?? '',
            'from' => $payload['From'] ?? '',
            'to' => $payload['To'] ?? '',
            'body' => $payload['Body'] ?? '',
            'media_urls' => $mediaUrls,
            'provider' => 'twilio',
            'raw' => $payload,
        ];
    }

    public function extractCallId(array $payload): ?string
    {
        return $payload['CallSid'] ?? null;
    }

    public function isInboundCall(array $payload): bool
    {
        $direction = $payload['Direction'] ?? '';

        return str_contains($direction, 'inbound');
    }

    public function getProviderName(): string
    {
        return 'twilio';
    }

    /**
     * Map Twilio's call status to the normalized status.
     *
     * Twilio's own statuses are already the normalized vocabulary this
     * app uses, so this is an explicit identity map (plus a safe passthrough
     * default) rather than a translation table.
     */
    private function mapCallStatus(string $twilioStatus): string
    {
        return match ($twilioStatus) {
            'queued' => 'queued',
            'initiated' => 'initiated',
            'ringing' => 'ringing',
            'in-progress' => 'in-progress',
            'completed' => 'completed',
            'busy' => 'busy',
            'no-answer' => 'no-answer',
            'canceled' => 'canceled',
            'failed' => 'failed',
            default => $twilioStatus,
        };
    }
}
