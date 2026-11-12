<?php

declare(strict_types=1);

namespace App\Services\Telephony\Twilio;

use App\Contracts\Telephony\AudioStreamProviderInterface;

/**
 * Twilio Media Streams WebSocket protocol handler.
 *
 * Twilio sends: { event: 'start'|'media'|'stop'|'dtmf', streamSid, start: {callSid, ...}, media: {payload, ...} }
 * Twilio expects: { event: 'media', streamSid, media: { payload } } for outgoing audio.
 */
class TwilioAudioStreamProvider implements AudioStreamProviderInterface
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
            'start' => [
                'event' => 'start',
                'stream_id' => $data['streamSid'] ?? null,
                'call_id' => $data['start']['callSid'] ?? null,
                'payload' => null,
                'metadata' => [
                    'account_sid' => $data['start']['accountSid'] ?? null,
                    'tracks' => $data['start']['tracks'] ?? [],
                    'media_format' => $data['start']['mediaFormat'] ?? [],
                    'custom_parameters' => $data['start']['customParameters'] ?? [],
                ],
            ],
            'media' => [
                'event' => 'media',
                'stream_id' => $data['streamSid'] ?? null,
                'call_id' => null,
                'payload' => $data['media']['payload'] ?? null,
                'metadata' => [
                    'track' => $data['media']['track'] ?? 'inbound',
                    'chunk' => $data['media']['chunk'] ?? null,
                    'timestamp' => $data['media']['timestamp'] ?? null,
                ],
            ],
            'stop' => [
                'event' => 'stop',
                'stream_id' => $data['streamSid'] ?? null,
                'call_id' => null,
                'payload' => null,
                'metadata' => [
                    'account_sid' => $data['stop']['accountSid'] ?? null,
                    'call_sid' => $data['stop']['callSid'] ?? null,
                ],
            ],
            'dtmf' => [
                'event' => 'dtmf',
                'stream_id' => $data['streamSid'] ?? null,
                'call_id' => null,
                'payload' => $data['dtmf']['digit'] ?? null,
                'metadata' => [],
            ],
            default => [
                'event' => 'unknown',
                'stream_id' => $data['streamSid'] ?? null,
                'call_id' => null,
                'payload' => null,
                'metadata' => $data,
            ],
        };
    }

    public function buildAudioMessage(string $audioPayload, string $streamId): string
    {
        return json_encode([
            'event' => 'media',
            'streamSid' => $streamId,
            'media' => [
                'payload' => $audioPayload,
            ],
        ]);
    }

    public function buildClearMessage(string $streamId): string
    {
        return json_encode([
            'event' => 'clear',
            'streamSid' => $streamId,
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
        return 'twilio';
    }
}
