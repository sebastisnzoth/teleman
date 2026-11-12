<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'call_id',
        'event_type',
        'event_data',
        'event_timestamp',
    ];

    protected $casts = [
        'event_data' => 'array',
        'event_timestamp' => 'datetime',
    ];

    // Relationships
    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    // Scopes
    public function scopeForCall($query, int $callId)
    {
        return $query->where('call_id', $callId)
            ->orderBy('event_timestamp', 'asc');
    }

    public function scopeByEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }
}
