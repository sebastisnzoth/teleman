<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'direction',
        'message_body',
        'sender_phone',
        'receiver_phone',
        'status',
        'ai_generated',
        'provider_message_id',
        'provider',
        'error_message',
        'num_segments',
        'cost',
        'credits_deducted',
        'transaction_id',
        'sent_at',
        'delivered_at',
        'metadata',
    ];

    protected $casts = [
        'ai_generated' => 'boolean',
        'credits_deducted' => 'boolean',
        'cost' => 'decimal:4',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'metadata' => 'array',
    ];

    // Relationships
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SmsConversation::class, 'conversation_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    // Scopes
    public function scopeInbound(Builder $query): Builder
    {
        return $query->where('direction', 'inbound');
    }

    public function scopeOutbound(Builder $query): Builder
    {
        return $query->where('direction', 'outbound');
    }

    public function scopeAiGenerated(Builder $query): Builder
    {
        return $query->where('ai_generated', true);
    }

    public function scopeDelivered(Builder $query): Builder
    {
        return $query->where('status', 'delivered');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    // Accessors
    public function getIsInboundAttribute(): bool
    {
        return $this->direction === 'inbound';
    }

    public function getIsOutboundAttribute(): bool
    {
        return $this->direction === 'outbound';
    }

    public function getIsDeliveredAttribute(): bool
    {
        return $this->status === 'delivered';
    }

    public function getIsFailedAttribute(): bool
    {
        return $this->status === 'failed';
    }
}
