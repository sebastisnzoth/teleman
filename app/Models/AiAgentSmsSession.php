<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class AiAgentSmsSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'ai_agent_id',
        'conversation_id',
        'message_count',
        'avg_response_time_ms',
        'sentiment_score',
        'outcomes',
        'total_tokens_used',
        'total_cost',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'sentiment_score' => 'decimal:2',
        'outcomes' => 'array',
        'total_cost' => 'decimal:4',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    // Relationships
    public function aiAgent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SmsConversation::class, 'conversation_id');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereNotNull('ended_at');
    }

    public function scopeByAgent(Builder $query, int $agentId): Builder
    {
        return $query->where('ai_agent_id', $agentId);
    }

    // Methods
    public function incrementMessageCount(): void
    {
        $this->increment('message_count');
    }

    public function updateResponseTime(int $milliseconds): void
    {
        if ($this->message_count > 0) {
            $currentAvg = $this->avg_response_time_ms ?? 0;
            $newAvg = (($currentAvg * $this->message_count) + $milliseconds) / ($this->message_count + 1);
            $this->update(['avg_response_time_ms' => round($newAvg)]);
        } else {
            $this->update(['avg_response_time_ms' => $milliseconds]);
        }
    }

    public function addCost(float $cost, int $tokens): void
    {
        $this->increment('total_cost', $cost);
        $this->increment('total_tokens_used', $tokens);
    }

    public function endSession(): void
    {
        $this->update(['ended_at' => now()]);
    }

    // Accessors
    public function getIsActiveAttribute(): bool
    {
        return is_null($this->ended_at);
    }

    public function getDurationMinutesAttribute(): ?float
    {
        if (!$this->ended_at) {
            return null;
        }
        return $this->started_at->diffInMinutes($this->ended_at);
    }
}