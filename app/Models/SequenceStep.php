<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SequenceStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'sequence_id',
        'step_number',
        'step_name',
        'delay_amount',
        'delay_unit',
        'action_type',
        'action_config',
        'conditions',
        'stop_if',
        'total_executed',
        'total_successful',
        'success_rate',
    ];

    protected $casts = [
        'step_number' => 'integer',
        'delay_amount' => 'integer',
        'action_config' => 'array',
        'conditions' => 'array',
        'stop_if' => 'array',
        'total_executed' => 'integer',
        'total_successful' => 'integer',
        // Float, not decimal:2 — the decimal cast serialises as a string, and
        // the sequence pages do arithmetic on this (toFixed, a progress bar).
        'success_rate' => 'float',
    ];

    // Relationships
    public function sequence(): BelongsTo
    {
        return $this->belongsTo(Sequence::class, 'sequence_id');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(SequenceStepExecution::class, 'step_id');
    }

    // Scopes
    public function scopeForSequence($query, $sequenceId)
    {
        return $query->where('sequence_id', $sequenceId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('step_number');
    }

    // Helper Methods
    public function getDelayInMinutes(): int
    {
        return match ($this->delay_unit) {
            'minutes' => $this->delay_amount,
            'hours' => $this->delay_amount * 60,
            'days' => $this->delay_amount * 60 * 24,
            default => 0,
        };
    }

    public function getDelayFormatted(): string
    {
        if ($this->delay_amount === 0) {
            return 'Immediate';
        }

        $unit = $this->delay_amount === 1 
            ? rtrim($this->delay_unit, 's') 
            : $this->delay_unit;

        return "{$this->delay_amount} {$unit}";
    }

    public function canExecute(SequenceEnrollment $enrollment): bool
    {
        // Check if there are conditions
        if (empty($this->conditions)) {
            return true;
        }

        // Evaluate conditions based on enrollment metadata
        foreach ($this->conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? null;
            $value = $condition['value'] ?? null;

            if (!$field || !$operator) {
                continue;
            }

            $enrollmentValue = data_get($enrollment->metadata, $field);

            $passes = match ($operator) {
                'equals' => $enrollmentValue == $value,
                'not_equals' => $enrollmentValue != $value,
                'greater_than' => $enrollmentValue > $value,
                'less_than' => $enrollmentValue < $value,
                'contains' => str_contains($enrollmentValue, $value),
                'not_contains' => !str_contains($enrollmentValue, $value),
                default => true,
            };

            if (!$passes) {
                return false;
            }
        }

        return true;
    }

    public function shouldStop(SequenceEnrollment $enrollment, $executionResult = null): bool
    {
        if (empty($this->stop_if)) {
            return false;
        }

        foreach ($this->stop_if as $stopCondition) {
            $condition = $stopCondition['condition'] ?? null;
            $value = $stopCondition['value'] ?? null;

            $shouldStop = match ($condition) {
                'result_equals' => $executionResult === $value,
                'call_connected' => $executionResult === 'completed',
                'contact_responded' => !empty($enrollment->metadata['responded']),
                'max_attempts_reached' => $enrollment->steps_completed >= ($value ?? 5),
                'contact_converted' => $enrollment->status === 'converted',
                default => false,
            };

            if ($shouldStop) {
                return true;
            }
        }

        return false;
    }

    public function updateStatistics(): void
    {
        $this->total_executed = $this->executions()->count();
        $this->total_successful = $this->executions()->where('status', 'completed')->count();
        
        if ($this->total_executed > 0) {
            $this->success_rate = ($this->total_successful / $this->total_executed) * 100;
        }

        $this->save();
    }

    public function getActionDescription(): string
    {
        $delay = $this->getDelayFormatted();
        
        return match ($this->action_type) {
            'call' => "Wait {$delay}, then make a phone call",
            'sms' => "Wait {$delay}, then send an SMS",
            'email' => "Wait {$delay}, then send an email",
            'wait' => "Wait {$delay}",
            'webhook' => "Wait {$delay}, then trigger webhook",
            default => "Unknown action",
        };
    }

    public function isFirstStep(): bool
    {
        return $this->step_number === 1;
    }

    public function isLastStep(): bool
    {
        return $this->step_number === $this->sequence->steps()->count();
    }

    public function getNextStep(): ?self
    {
        return $this->sequence->steps()
            ->where('step_number', '>', $this->step_number)
            ->orderBy('step_number')
            ->first();
    }
}
