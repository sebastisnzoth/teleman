<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrunkHealthLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'trunk_id',
        'check_type',
        'status',
        'response_time_ms',
        'error_message',
        'details',
        'checked_at',
    ];

    protected $casts = [
        'details' => 'array',
        'checked_at' => 'datetime',
        'response_time_ms' => 'integer',
    ];

    /**
     * Get the trunk this log belongs to
     */
    public function trunk(): BelongsTo
    {
        return $this->belongsTo(UserSipTrunk::class, 'trunk_id');
    }

    /**
     * Check if health check was successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Scope: Recent logs
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('checked_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope: Failed checks only
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope: Successful checks only
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }
}
