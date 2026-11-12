<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessedWebhook extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'provider',
        'event_id',
        'event_type',
        'status',
        'metadata',
        'processed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'processed_at' => 'datetime',
    ];

    /**
     * Check if a webhook event has already been processed.
     */
    public static function isProcessed(string $provider, string $eventId, string $eventType): bool
    {
        return static::where('provider', $provider)
            ->where('event_id', $eventId)
            ->where('event_type', $eventType)
            ->exists();
    }

    /**
     * Mark a webhook event as processed. Returns false if already processed (duplicate).
     */
    public static function markProcessed(string $provider, string $eventId, string $eventType, array $metadata = []): bool
    {
        try {
            static::create([
                'provider' => $provider,
                'event_id' => $eventId,
                'event_type' => $eventType,
                'metadata' => $metadata ?: null,
                'processed_at' => now(),
            ]);

            return true;
        } catch (\Illuminate\Database\QueryException $e) {
            // Duplicate entry — webhook was already processed
            if ($e->getCode() === '23000') {
                return false;
            }
            throw $e;
        }
    }
}
