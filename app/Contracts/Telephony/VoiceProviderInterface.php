<?php

declare(strict_types=1);

namespace App\Contracts\Telephony;

/**
 * Provider-agnostic interface for voice call operations.
 *
 * All telephony providers (Twilio, Plivo, etc.) must implement this
 * to enable multi-provider voice calling from a single codebase.
 */
interface VoiceProviderInterface
{
    /**
     * Initialize provider with credentials array.
     *
     * @param  array  $credentials  Provider-specific credentials
     *                              Twilio: ['account_sid' => ..., 'auth_token' => ...]
     *                              Plivo:  ['auth_id' => ..., 'auth_token' => ...]
     */
    public function initialize(array $credentials): static;

    /**
     * Initiate an outbound call.
     *
     * `request_uuid` is Plivo-specific: Plivo's create-call API is
     * asynchronous and only ever hands back a RequestUUID here, not the
     * CallUUID every other method on this interface needs — see
     * PlivoVoiceProvider::makeCall() for where the real CallUUID comes
     * from (the call's first webhook). Twilio has no such split and
     * always returns this key as null.
     *
     * @return array{success: bool, call_id: ?string, request_uuid: ?string, status: ?string, error: ?string}
     */
    public function makeCall(string $to, string $from, string $answerUrl, array $options = []): array;

    /**
     * Fetch details for a call.
     *
     * @return array{call_id: string, status: string, duration: ?int, from: string, to: string, direction: string, price: ?float, price_unit: string, start_time: ?string, end_time: ?string, answered_by: ?string, recordings: array}|null
     */
    public function getCallDetails(string $callId): ?array;

    /**
     * Update a live call (hangup, redirect, etc.).
     */
    public function updateCall(string $callId, array $params): bool;

    /**
     * End/hangup a call.
     */
    public function hangupCall(string $callId): bool;

    /**
     * Get recording details.
     *
     * @return array{id: string, url: string, duration: ?int, status: string}|null
     */
    public function getRecording(string $recordingId): ?array;

    /**
     * Delete a recording.
     */
    public function deleteRecording(string $recordingId): bool;

    /**
     * Verify provider credentials with a test API call.
     *
     * @return array{valid: bool, account_name: ?string, error: ?string}
     */
    public function verifyCredentials(array $credentials): array;

    /**
     * Validate an incoming webhook request from the provider.
     */
    public function validateWebhook(array $payload, string $signature, string $url): bool;

    /**
     * Estimate call cost.
     */
    public function estimateCallCost(string $to, int $durationSeconds): float;

    /**
     * Get the canonical provider name.
     */
    public function getName(): string;
}
