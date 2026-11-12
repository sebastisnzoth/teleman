<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DtmfResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'call_id',
        'digit',
        'pressed_at',
    ];

    protected $casts = [
        'pressed_at' => 'datetime',
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
            ->orderBy('pressed_at', 'asc');
    }

    // Helper Methods
    public static function getDigitsForCall(int $callId): string
    {
        return static::forCall($callId)
            ->pluck('digit')
            ->implode('');
    }
}
