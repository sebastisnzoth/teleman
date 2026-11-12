<?php

namespace App\Services\SMS;

interface SMSProviderInterface
{
    /**
     * Send a single SMS message
     *
     * @param string $from From phone number
     * @param string $to To phone number
     * @param string $message Message body
     * @param array $options Additional options (media URLs, etc.)
     * @return array ['success' => bool, 'message_id' => string, 'error' => string|null, 'cost' => float|null]
     */
    public function send(string $from, string $to, string $message, array $options = []): array;

    /**
     * Send batch SMS messages
     *
     * @param string $from From phone number
     * @param array $recipients Array of ['to' => phone, 'message' => text]
     * @param array $options Additional options
     * @return array ['success' => int, 'failed' => int, 'results' => array]
     */
    public function sendBatch(string $from, array $recipients, array $options = []): array;

    /**
     * Get message status
     *
     * @param string $messageId Provider's message ID
     * @return array ['status' => string, 'delivered_at' => string|null, 'error' => string|null]
     */
    public function getStatus(string $messageId): array;

    /**
     * Validate webhook signature
     *
     * @param array $payload Webhook payload
     * @param string $signature Webhook signature
     * @return bool
     */
    public function validateWebhook(array $payload, string $signature): bool;

    /**
     * Parse incoming webhook data
     *
     * @param array $payload Raw webhook payload
     * @return array Normalized data ['from' => string, 'to' => string, 'body' => string, 'message_id' => string, 'timestamp' => string]
     */
    public function parseIncoming(array $payload): array;

    /**
     * Get provider name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Check if provider supports MMS
     *
     * @return bool
     */
    public function supportsMMS(): bool;
}
