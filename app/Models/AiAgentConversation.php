<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAgentConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'ai_agent_call_id',
        'turn_number',
        'role',
        'content',
        'audio_url',
        'duration_ms',
        'confidence',
        'metadata',
    ];

    protected $casts = [
        'turn_number' => 'integer',
        'duration_ms' => 'integer',
        'confidence' => 'decimal:4',
        'metadata' => 'array',
    ];

    /**
     * Get the call for this conversation turn
     */
    public function call(): BelongsTo
    {
        return $this->belongsTo(AiAgentCall::class, 'ai_agent_call_id');
    }

    /**
     * Check if this is a user message
     */
    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    /**
     * Check if this is an assistant message
     */
    public function isAssistant(): bool
    {
        return $this->role === 'assistant';
    }

    /**
     * Check if this is a system message
     */
    public function isSystem(): bool
    {
        return $this->role === 'system';
    }
}
