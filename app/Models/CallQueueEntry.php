<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallQueueEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'caller_number',
        'call_sid',
        'queue_name',
        'status',
        'position',
        'dequeue_attempt',
        'wait_duration_seconds',
        'queued_at',
        'connected_at',
        'abandoned_at',
    ];

    protected $casts = [
        'position' => 'integer',
        'dequeue_attempt' => 'integer',
        'wait_duration_seconds' => 'integer',
        'queued_at' => 'datetime',
        'connected_at' => 'datetime',
        'abandoned_at' => 'datetime',
    ];

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes

    public function scopeWaiting($query)
    {
        return $query->where('status', 'waiting');
    }

    public function scopeForUser($query, User $user)
    {
        $ownerId = $user->isAgent() ? $user->parent_user_id : $user->id;
        return $query->where('user_id', $ownerId);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['waiting', 'connecting']);
    }
}
