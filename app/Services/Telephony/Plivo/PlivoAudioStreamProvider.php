<?php

declare(strict_types=1);

namespace App\Services\Telephony\Plivo;

use App\Contracts\Telephony\AudioStreamProviderInterface;

/**
 * Plivo Audio Streaming WebSocket protocol handler.
 *
 * Plivo sends: { event: 'start'|'media'|'stop'|'dtmf', ... }
 * Plivo expects: { event: 'playAudio', media: { contentType, sampleRate, payload } }
 * Clear: { event: 'clearAudio', streamId }
 *
 * The codec here (audio/x-mulaw, 8000 Hz) must match
 * PlivoCallXmlGenerator::stream()'s default `contentType` — Plivo's own
 * documentation is explicit that a mismatch between what the stream was
 * opened with and what playAudio sends back is a documented cause of the
 * caller hearing nothing. buildAudioMessage() derives its contentType and
 * sampleRate from getAudioFormat() rather than hardcoding a second copy,
 * so the two can no longer state different codecs by accident; a caller
 * that knows the actually-negotiated format (read from the `start`
 * event's own mediaFormat, per the same rule) can still pass it
 * explicitly to override the default.
 */
class PlivoAudioStreamProvider implements AudioStreamProviderInterface
{
    public function parseMessage(string $rawMessage): array
    {
        $data = json_decode($rawMessage, true);

        if (! $data || ! isset($data['event'])) {
            return [
                'event' => 'unknown',
                'stream_id' => null,
                'call_id' => null,
                'payload' => null,
                'metadata' => [],
            ];
        }

        return match ($data['event']) {
            // Plivo nests the start event's own identifiers under `start`,
            // including streamId itself — unlike media/dtmf/stop, which
            // carry streamId at the top level. Fall back to `start.streamId`
            // the same way callId already does one line below: correct
            // whichever event actually carries it top-level or nested.
            'start' => [
                'event' => 'start',
                'stream_id' => $data['streamId'] ?? ($data['start']['streamId'] ?? null),
                'call_id' => $data['callId'] ?? ($data['start']['callId'] ?? null),
                'payload' => null,
                'metadata' => [
                    'from' => $data['start']['from'] ?? null,
                    'to' => $data['start']['to'] ?? null,
                    'call_id' => $data['start']['callId'] ?? null,
                    // Read the negotiated format from here, not from the
                    // XML the caller thinks it returned — a proxy or a
                    // template can make the two disagree.
                    'media_format' => $data['start']['mediaFormat'] ?? null,
                ],
            ],
            'media' => [
                'event' => 'media',
                'stream_id' => $data['streamId'] ?? null,
                'call_id' => null,
                'payload' => $data['media']['payload'] ?? null,
                'metadata' => [
                    'track' => $data['media']['track'] ?? 'inbound',
                    'chunk' => $data['media']['chunk'] ?? null,
                    'timestamp' => $data['media']['timestamp'] ?? null,
                    'content_type' => $data['media']['contentType'] ?? 'audio/x-mulaw',
                    'sample_rate' => $data['media']['sampleRate'] ?? 8000,
                ],
            ],
            'stop' => [
                'event' => 'stop',
                'stream_id' => $data['streamId'] ?? null,
                'call_id' => null,
                'payload' => null,
                'metadata' => $data['stop'] ?? [],
            ],
            'dtmf' => [
                'event' => 'dtmf',
                'stream_id' => $data['streamId'] ?? null,
                'call_id' => null,
                'payload' => $data['dtmf']['digit'] ?? null,
                'metadata' => [],
            ],
            default => [
                'event' => 'unknown',
                'stream_id' => $data['streamId'] ?? null,
                'call_id' => null,
                'payload' => null,
                'metadata' => $data,
            ],
        };
    }

    /**
     * @param  array{encoding: string, sample_rate: int, channels?: int}|null  $format  Defaults to getAudioFormat() — pass the
     *                                                                                  negotiated format explicitly (from a parsed `start` event's `mediaFormat`) when it may differ.
     */
    public function buildAudioMessage(string $audioPayload, string $streamId, ?array $format = null): string
    {
        $format ??= $this->getAudioFormat();

        return json_encode([
            'event' => 'playAudio',
            'media' => [
                'contentType' => $format['encoding'],
                'sampleRate' => (string) $format['sample_rate'],
                'payload' => $audioPayload,
            ],
        ]);
    }

    public function buildClearMessage(string $streamId): string
    {
        return json_encode([
            'event' => 'clearAudio',
            'streamId' => $streamId,
        ]);
    }

    public function getAudioFormat(): array
    {
        return [
            'encoding' => 'audio/x-mulaw',
            'sample_rate' => 8000,
            'channels' => 1,
        ];
    }

    public function getName(): string
    {
        return 'plivo';
    }
}
