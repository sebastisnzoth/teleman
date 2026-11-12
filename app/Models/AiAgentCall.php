<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiAgentCall extends Model
{
    use HasFactory;

    protected $fillable = [
        'ai_agent_id',
        'call_id',
        'contact_id',
        'campaign_id',
        'campaign_contact_id',
        'call_sid',
        'legacy_account_sid',
        'direction',
        'from_number',
        'to_number',
        'status',
        'duration',
        'started_at',
        'answered_at',
        'ended_at',
        'end_reason',
        'recording_url',
        'turn_count',
        'transferred',
        'transferred_to',
        'cost_estimate',
        'twilio_cost',
        'openai_cost',
        'total_tokens',
        'input_tokens',
        'output_tokens',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'answered_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration' => 'integer',
        'turn_count' => 'integer',
        'transferred' => 'boolean',
        'cost_estimate' => 'decimal:4',
        'twilio_cost' => 'decimal:4',
        'openai_cost' => 'decimal:4',
        'total_tokens' => 'integer',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Get the AI agent for this call
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_id');
    }

    /**
     * Get the call record
     */
    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    /**
     * Get the contact
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the campaign
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Get all conversation turns
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(AiAgentConversation::class)->orderBy('turn_number');
    }

    /**
     * Get the full transcript
     */
    public function getTranscript(): string
    {
        return $this->conversations
            ->map(fn($conv) => "{$conv->role}: {$conv->content}")
            ->implode("\n\n");
    }

    /**
     * Mark call as answered
     */
    public function markAnswered(): void
    {
        $this->update([
            'status' => 'in-progress',
            'answered_at' => now(),
        ]);
    }

    /**
     * Mark call as completed
     */
    public function markCompleted(string $reason = 'completed'): void
    {
        $this->update([
            'status' => 'completed',
            'ended_at' => now(),
            'end_reason' => $reason,
            'duration' => $this->answered_at ? now()->diffInSeconds($this->answered_at) : 0,
        ]);
    }

    /**
     * Calculate and update cost estimate
     */
    public function updateCostEstimate(): void
    {
        $config = config('ai-agent.cost_tracking') ?? [];

        if (!($config['enabled'] ?? false) || !$this->duration) {
            return;
        }

        $durationMinutes = $this->duration / 60;

        // Calculate costs (TTS is free — Twilio <Say> with Polly/Google voices)
        $twilioCost = $durationMinutes * ($config['twilio_cost_per_min'] ?? 0.0085);
        $deepgramCost = $durationMinutes * ($config['deepgram_cost_per_min'] ?? 0.0043);

        $totalCost = $twilioCost + $deepgramCost;
        
        $this->update(['cost_estimate' => round($totalCost, 4)]);
    }

    /**
     * Get total cost (Twilio + LLM)
     */
    public function getTotalCost(): float
    {
        return (float) ($this->twilio_cost + $this->openai_cost);
    }

    /**
     * Update cost breakdown from actual API data
     */
    public function updateCostBreakdown(float $twilioCost = null, float $openaiCost = null, int $tokens = null): void
    {
        $updates = [];
        
        if ($twilioCost !== null) {
            $updates['twilio_cost'] = round($twilioCost, 4);
        }
        
        if ($openaiCost !== null) {
            $updates['openai_cost'] = round($openaiCost, 4);
        }
        
        if ($tokens !== null) {
            $updates['total_tokens'] = $tokens;
        }
        
        if (!empty($updates)) {
            // Update cost_estimate with total
            if (isset($updates['twilio_cost']) || isset($updates['openai_cost'])) {
                $twilio = $updates['twilio_cost'] ?? $this->twilio_cost ?? 0;
                $openai = $updates['openai_cost'] ?? $this->openai_cost ?? 0;
                $updates['cost_estimate'] = round($twilio + $openai, 4);
            }
            
            $this->update($updates);
        }
    }
}
