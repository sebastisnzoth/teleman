<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MessageVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'variant_label',
        'variant_name',
        'message_text',
        'tone_description',
        'sent_count',
        'answered_count',
        'completed_count',
        'positive_response_count',
        'answer_rate',
        'completion_rate',
        'effectiveness_score',
        'is_active',
        'is_winner',
        'last_used_at',
    ];

    protected $casts = [
        'sent_count' => 'integer',
        'answered_count' => 'integer',
        'completed_count' => 'integer',
        'positive_response_count' => 'integer',
        // Floats, not decimal casts: these three are percentages the
        // campaign edit page prints with .toFixed(1), and a decimal cast
        // serialises them as quoted strings, which have no such method.
        // The columns are decimal(5,2), so the database still rounds what
        // is stored; every other reader here does arithmetic on them.
        'answer_rate' => 'float',
        'completion_rate' => 'float',
        'effectiveness_score' => 'float',
        'is_active' => 'boolean',
        'is_winner' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    // Relationships
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    // Helper Methods
    public function recordUsage(): void
    {
        $this->increment('sent_count');
        $this->update(['last_used_at' => now()]);
    }

    public function recordAnswer(): void
    {
        $this->increment('answered_count');
        $this->updateRates();
    }

    public function recordCompletion(): void
    {
        $this->increment('completed_count');
        $this->updateRates();
    }

    public function recordPositiveResponse(): void
    {
        $this->increment('positive_response_count');
        $this->updateEffectivenessScore();
    }

    protected function updateRates(): void
    {
        if ($this->sent_count > 0) {
            $this->answer_rate = ($this->answered_count / $this->sent_count) * 100;
            $this->completion_rate = ($this->completed_count / $this->sent_count) * 100;
        }
        
        $this->updateEffectivenessScore();
        $this->saveQuietly();
    }

    protected function updateEffectivenessScore(): void
    {
        // Composite score: answer rate (40%) + completion rate (40%) + positive responses (20%)
        if ($this->sent_count > 0) {
            $positiveRate = ($this->positive_response_count / $this->sent_count) * 100;
            $this->effectiveness_score = 
                ($this->answer_rate * 0.4) + 
                ($this->completion_rate * 0.4) + 
                ($positiveRate * 0.2);
        }
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCampaign($query, int $campaignId)
    {
        return $query->where('campaign_id', $campaignId);
    }

    public function scopeWinner($query)
    {
        return $query->where('is_winner', true);
    }

    public function scopeOrderByPerformance($query)
    {
        return $query->orderByDesc('effectiveness_score');
    }
}
