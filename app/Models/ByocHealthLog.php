<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ByocHealthLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'byoc_trunk_id',
        'connection_policy_target_id',
        'status',
        'check_type',
        'message',
        'response_time_ms',
        'status_code',
        'error_message',
        'error_details',
        'checked_at',
    ];

    protected $casts = [
        'response_time_ms' => 'integer',
        'status_code' => 'integer',
        'error_details' => 'array',
        'checked_at' => 'datetime',
    ];

    /**
     * BYOC trunk this log belongs to
     */
    public function byocTrunk(): BelongsTo
    {
        return $this->belongsTo(ByocTrunk::class);
    }

    /**
     * Target this log is for (if applicable)
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(ConnectionPolicyTarget::class, 'connection_policy_target_id');
    }

    /**
     * Check if this is a successful check
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if this is a failed check
     */
    public function isFailed(): bool
    {
        return $this->status === 'failure';
    }

    /**
     * Get status badge color
     */
    public function getStatusColor(): string
    {
        return match($this->status) {
            'success' => 'green',
            'degraded' => 'yellow',
            'failure' => 'red',
            default => 'gray',
        };
    }
}
