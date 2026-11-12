<?php

declare(strict_types=1);

namespace App\Contracts\Telephony;

/**
 * Provider-agnostic interface for bidirectional audio streaming.
 *
 * Used by AI agent calling — normalizes Twilio Media Streams and
 * Plivo Audio Streaming WebSocket protocols.
 */
interface AudioStreamProviderInterface
{
    /**
     * Parse an incoming WebSocket message from the provider.
     *
     * Normalizes provider-specific events into a standard format:
     * - 'start'  → stream started, contains metadata
     * - 'media'  → audio data chunk
     * - 'stop'   → stream ended
     * - 'dtmf'   → DTMF digit received
     * - 'unknown' → unrecognized event
     *
     * @return array{event: string, stream_id: ?string, call_id: ?string, payload: ?string, metadata: array}
     */
    public function parseMessage(string $rawMessage): array;

    /**
     * Build a WebSocket message to send audio back to the caller.
     *
     * @param  string  $audioPayload  Base64-encoded audio data
     * @param  string  $streamId  The stream ID from the 'start' event
     * @return string JSON message to send over WebSocket
     */
    public function buildAudioMessage(string $audioPayload, string $streamId): string;

    /**
     * Build a message to clear/flush queued audio on the provider side.
     *
     * @param  string  $streamId  The stream ID
     * @return string JSON message to send over WebSocket
     */
    public function buildClearMessage(string $streamId): string;

    /**
     * Get the expected audio format for this provider's stream.
     *
     * @return array{encoding: string, sample_rate: int, channels: int}
     */
    public function getAudioFormat(): array;

    /**
     * Get the provider name.
     */
    public function getName(): string;
}
