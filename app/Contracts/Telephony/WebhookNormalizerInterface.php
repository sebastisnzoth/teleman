<?php

declare(strict_types=1);

namespace App\Contracts\Telephony;

/**
 * Normalizes incoming webhook payloads from different telephony providers
 * into a standard format that the application can process uniformly.
 */
interface WebhookNormalizerInterface
{
    /**
     * Normalize an incoming call webhook payload.
     *
     * @return array{call_id: string, from: string, to: string, direction: string, status: string, provider: string, raw: array}
     */
    public function normalizeCallEvent(array $payload): array;

    /**
     * Normalize a call status callback payload.
     *
     * @return array{call_id: string, status: string, duration: ?int, price: ?float, answered_by: ?string, provider: string, raw: array}
     */
    public function normalizeStatusCallback(array $payload): array;

    /**
     * Normalize a recording status callback payload.
     *
     * @return array{call_id: string, recording_id: string, recording_url: ?string, duration: ?int, status: string, provider: string, raw: array}
     */
    public function normalizeRecordingCallback(array $payload): array;

    /**
     * Normalize an incoming SMS webhook payload.
     *
     * @return array{message_id: string, from: string, to: string, body: string, media_urls: array, provider: string, raw: array}
     */
    public function normalizeIncomingSms(array $payload): array;

    /**
     * Extract the call ID from a webhook payload.
     */
    public function extractCallId(array $payload): ?string;

    /**
     * Check if the webhook is for an inbound call.
     */
    public function isInboundCall(array $payload): bool;

    /**
     * Get the provider name.
     */
    public function getProviderName(): string;
}
