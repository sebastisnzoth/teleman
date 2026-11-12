<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SequenceEnrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'sequence_id',
        'contact_id',
        'campaign_contact_id',
        'campaign_id',
        'status',
        'current_step',
        'steps_completed',
        'next_step_at',
        'enrolled_at',
        'last_step_at',
        'completed_at',
        'stopped_reason',
        'metadata',
        'execution_history',
    ];

    protected $casts = [
        'current_step' => 'integer',
        'steps_completed' => 'integer',
        'next_step_at' => 'datetime',
        'enrolled_at' => 'datetime',
        'last_step_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
        'execution_history' => 'array',
    ];

    // Relationships
    public function sequence(): BelongsTo
    {
        return $this->belongsTo(Sequence::class, 'sequence_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function campaignContact(): BelongsTo
    {
        return $this->belongsTo(CampaignContact::class, 'campaign_contact_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(SequenceStepExecution::class, 'enrollment_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePaused($query)
    {
        return $query->where('status', 'paused');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeConverted($query)
    {
        return $query->where('status', 'converted');
    }

    public function scopeDueForExecution($query)
    {
        return $query->where('status', 'active')
            ->where('next_step_at', '<=', now());
    }

    public function scopeForContact($query, $contactId)
    {
        return $query->where('contact_id', $contactId);
    }

    public function scopeForSequence($query, $sequenceId)
    {
        return $query->where('sequence_id', $sequenceId);
    }

    public function scopeForCampaign($query, $campaignId)
    {
        return $query->where('campaign_id', $campaignId);
    }

    // Helper Methods
    public function getCurrentStep(): ?SequenceStep
    {
        if ($this->current_step === 0) {
            return null;
        }

        return $this->sequence->steps()
            ->where('step_number', $this->current_step)
            ->first();
    }

    public function getNextStep(): ?SequenceStep
    {
        return $this->sequence->steps()
            ->where('step_number', '>', $this->current_step)
            ->orderBy('step_number')
            ->first();
    }

    public function hasMoreSteps(): bool
    {
        return $this->getNextStep() !== null;
    }

    public function moveToNextStep(int $delayMinutes = null): void
    {
        $nextStep = $this->getNextStep();

        if (!$nextStep) {
            $this->complete();
            return;
        }

        $this->current_step = $nextStep->step_number;
        $this->steps_completed++;
        $this->last_step_at = now();

        // Calculate next execution time
        if ($delayMinutes !== null) {
            $this->next_step_at = now()->addMinutes($delayMinutes);
        } else {
            $this->next_step_at = now()->addMinutes($nextStep->getDelayInMinutes());
        }

        $this->save();
    }

    public function complete(bool $converted = false): void
    {
        $this->status = $converted ? 'converted' : 'completed';
        $this->completed_at = now();
        $this->next_step_at = null;
        $this->save();

        // Update sequence statistics
        $this->sequence->updateStatistics();
    }

    public function stop(string $reason): void
    {
        $this->status = 'stopped';
        $this->stopped_reason = $reason;
        $this->next_step_at = null;
        $this->save();
    }

    public function pause(): void
    {
        $this->status = 'paused';
        $this->save();
    }

    public function resume(): void
    {
        if ($this->status !== 'paused') {
            return;
        }

        $this->status = 'active';
        
        // If next_step_at is in the past, reschedule for now
        if ($this->next_step_at && $this->next_step_at->isPast()) {
            $this->next_step_at = now();
        }

        $this->save();
    }

    public function recordExecution(SequenceStep $step, string $status, ?string $result = null, ?string $error = null): SequenceStepExecution
    {
        $execution = SequenceStepExecution::create([
            'enrollment_id' => $this->id,
            'step_id' => $step->id,
            'status' => $status,
            'scheduled_at' => $this->next_step_at,
            'executed_at' => now(),
            'result' => $result,
            'error_message' => $error,
        ]);

        // Add to execution history
        $history = $this->execution_history ?? [];
        $history[] = [
            'step_number' => $step->step_number,
            'step_name' => $step->step_name,
            'action_type' => $step->action_type,
            'status' => $status,
            'executed_at' => now()->toIso8601String(),
            'result' => $result,
        ];
        $this->execution_history = $history;
        $this->save();

        return $execution;
    }

    public function updateMetadata(array $data): void
    {
        $metadata = $this->metadata ?? [];
        $this->metadata = array_merge($metadata, $data);
        $this->save();
    }

    public function getProgressPercentage(): float
    {
        $totalSteps = $this->sequence->steps()->count();
        
        if ($totalSteps === 0) {
            return 0;
        }

        return ($this->steps_completed / $totalSteps) * 100;
    }

    public function getDuration(): ?int
    {
        if (!$this->completed_at) {
            return null;
        }

        return $this->enrolled_at->diffInSeconds($this->completed_at);
    }

    public function getDurationFormatted(): string
    {
        $duration = $this->getDuration();
        
        if (!$duration) {
            return 'In Progress';
        }

        $days = floor($duration / 86400);
        $hours = floor(($duration % 86400) / 3600);
        $minutes = floor(($duration % 3600) / 60);

        $parts = [];
        if ($days > 0) $parts[] = "{$days}d";
        if ($hours > 0) $parts[] = "{$hours}h";
        if ($minutes > 0) $parts[] = "{$minutes}m";

        return implode(' ', $parts) ?: '< 1m';
    }

    public function isOverdue(): bool
    {
        return $this->status === 'active' 
            && $this->next_step_at 
            && $this->next_step_at->isPast();
    }
}
