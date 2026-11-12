<?php

declare(strict_types=1);

namespace App\Contracts\Telephony;

/**
 * Provider-agnostic SMS/MMS sending interface.
 *
 * Lives alongside VoiceProviderInterface, CallXmlInterface, etc.
 * Implementations: TwilioSMSProvider, PlivoSMSProvider.
 */
interface SmsProviderInterface
{
    /**
     * Initialize the provider with credentials array.
     *
     * @param  array  $credentials  Provider-specific credentials
     */
    public function initialize(array $credentials): static;

    /**
     * Send a single SMS message.
     *
     * @param  string  $from  From phone number (E.164)
     * @param  string  $to  To phone number (E.164)
     * @param  string  $message  Message body
     * @param  array  $options  Additional options (media_urls for MMS, etc.)
     * @return array{success: bool, message_id: ?string, status: string, cost: ?float, num_segments: int, error: ?string}
     */
    public function send(string $from, string $to, string $message, array $options = []): array;

    /**
     * Send batch SMS messages.
     *
     * @param  string  $from  From phone number (E.164)
     * @param  array  $recipients  Array of ['to' => phone, 'message' => text]
     * @param  array  $options  Additional options
     * @return array{success: int, failed: int, total: int, results: array}
     */
    public function sendBatch(string $from, array $recipients, array $options = []): array;

    /**
     * Get message delivery status.
     *
     * @param  string  $messageId  Provider's message ID
     * @return array{status: string, delivered_at: ?string, error: ?string}
     */
    public function getStatus(string $messageId): array;

    /**
     * Validate incoming webhook signature.
     *
     * @param  array  $payload  Webhook payload
     * @param  string  $signature  Webhook signature header
     */
    public function validateWebhook(array $payload, string $signature): bool;

    /**
     * Parse incoming SMS webhook data into normalized format.
     *
     * @param  array  $payload  Raw webhook payload
     * @return array{from: string, to: string, body: string, message_id: string, timestamp: string, num_segments: int, media_urls: array}
     */
    public function parseIncoming(array $payload): array;

    /**
     * Get the provider name identifier.
     */
    public function getName(): string;

    /**
     * Whether this provider supports MMS (media messages).
     */
    public function supportsMMS(): bool;
}
