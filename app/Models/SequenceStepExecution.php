<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SequenceStepExecution extends Model
{
    use HasFactory;

    protected $fillable = [
        'enrollment_id',
        'step_id',
        'call_id',
        'status',
        'scheduled_at',
        'executed_at',
        'next_scheduled_at',
        'result',
        'error_message',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'executed_at' => 'datetime',
        'next_scheduled_at' => 'datetime',
    ];

    // Relationships
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(SequenceEnrollment::class, 'enrollment_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(SequenceStep::class, 'step_id');
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeExecuting($query)
    {
        return $query->where('status', 'executing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeForEnrollment($query, $enrollmentId)
    {
        return $query->where('enrollment_id', $enrollmentId);
    }

    public function scopeForStep($query, $stepId)
    {
        return $query->where('step_id', $stepId);
    }

    // Helper Methods
    public function markAsExecuting(): void
    {
        $this->status = 'executing';
        $this->executed_at = now();
        $this->save();
    }

    public function markAsCompleted(string $result = null): void
    {
        $this->status = 'completed';
        $this->result = $result;
        $this->save();

        // Update step statistics
        $this->step->updateStatistics();
    }

    public function markAsFailed(string $error): void
    {
        $this->status = 'failed';
        $this->error_message = $error;
        $this->save();

        // Update step statistics
        $this->step->updateStatistics();
    }

    public function markAsSkipped(string $reason): void
    {
        $this->status = 'skipped';
        $this->result = $reason;
        $this->save();
    }

    public function getExecutionTime(): ?int
    {
        if (!$this->executed_at || !$this->scheduled_at) {
            return null;
        }

        return $this->scheduled_at->diffInSeconds($this->executed_at);
    }

    public function wasOnTime(): bool
    {
        if (!$this->executed_at || !$this->scheduled_at) {
            return false;
        }

        // Consider "on time" if executed within 5 minutes of scheduled time
        return $this->executed_at->diffInMinutes($this->scheduled_at) <= 5;
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'completed';
    }
}
