<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Valid status transitions. Key = current status, value = allowed next statuses.
     */
    public const STATUS_TRANSITIONS = [
        'draft'     => ['scheduled', 'running'],
        'scheduled' => ['running', 'draft'],
        'running'   => ['paused', 'completed', 'failed'],
        'paused'    => ['running', 'completed'],
        'completed' => [], // terminal
        'failed'    => ['draft'], // can restart from draft
    ];

    /**
     * The REST API's campaign type vocabulary, as public value => stored value.
     *
     * The v1 API has always documented and validated tts|audio, while the
     * campaigns.type column is an enum of text_to_speech|voice_to_voice. The
     * two must be translated at the API boundary: MySQL rejects an undeclared
     * enum value outright.
     */
    public const API_TYPES = [
        'tts' => 'text_to_speech',
        'audio' => 'voice_to_voice',
    ];

    protected $fillable = [
        'user_id',
        'phone_number_id',
        'ai_agent_id',
        'assigned_agent_id',
        'name',
        'type',
        'status',
        'message',
        'script_text',
        'expected_variables',
        'campaign_variables',
        'voice',
        'voice_gender',
        'voice_language',
        'audio_file_id',
        'audio_file_path',
        'caller_id',
        'from_number',
        'enable_recording',
        'recording_mode',
        'recording_max_length',
        'enable_dtmf',
        'dtmf_num_digits',
        'dtmf_timeout',
        'dtmf_prompt',
        'dtmf_actions',
        'answer_delay_seconds',
        'enable_amd',
        'amd_action',
        'max_concurrent_calls',
        'retry_attempts',
        'retry_delay_minutes',
        'scheduled_at',
        'started_at',
        'completed_at',
        'paused_at',
        'total_contacts',
        'total_called',
        'total_answered',
        'total_failed',
        'best_call_hours',
        'best_call_days',
        'use_smart_scheduling',
    ];

    protected $casts = [
        'enable_recording' => 'boolean',
        'recording_max_length' => 'integer',
        'enable_dtmf' => 'boolean',
        'dtmf_num_digits' => 'integer',
        'dtmf_timeout' => 'integer',
        'dtmf_actions' => 'array',
        'answer_delay_seconds' => 'integer',
        'enable_amd' => 'boolean',
        'expected_variables' => 'array',
        'campaign_variables' => 'array',
        'max_concurrent_calls' => 'integer',
        'retry_attempts' => 'integer',
        'retry_delay_minutes' => 'integer',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'paused_at' => 'datetime',
        'total_contacts' => 'integer',
        'total_called' => 'integer',
        'total_answered' => 'integer',
        'total_failed' => 'integer',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(PhoneNumber::class);
    }

    public function audioFile(): BelongsTo
    {
        return $this->belongsTo(AudioFile::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CampaignContact::class);
    }

    public function campaignContacts(): HasMany
    {
        return $this->hasMany(CampaignContact::class);
    }

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class);
    }

    public function dtmfResponses(): HasMany
    {
        return $this->hasMany(CallDtmfResponse::class);
    }

    public function aiAgent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class);
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['scheduled', 'running']);
    }

    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // Helper Methods
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function canEdit(): bool
    {
        // Can only edit if draft or scheduled (and not started)
        return in_array($this->status, ['draft', 'scheduled']) && !$this->started_at;
    }

    public function canDelete(): bool
    {
        // Can only delete if not running
        return !in_array($this->status, ['running']);
    }

    public function canStart(): bool
    {
        return in_array($this->status, ['draft', 'scheduled', 'paused']);
    }

    public function canStop(): bool
    {
        return $this->status === 'running';
    }

    public function canPause(): bool
    {
        return $this->status === 'running';
    }

    public function canResume(): bool
    {
        return $this->status === 'paused';
    }

    public function getProgressPercentage(): float
    {
        if ($this->total_contacts === 0) {
            return 0;
        }

        return round(($this->total_called / $this->total_contacts) * 100, 2);
    }

    public function getAnswerRate(): float
    {
        if ($this->total_called === 0) {
            return 0;
        }

        return round(($this->total_answered / $this->total_called) * 100, 2);
    }

    public function incrementCalled(): void
    {
        $this->increment('total_called');
    }

    public function incrementAnswered(): void
    {
        $this->increment('total_answered');
    }

    public function incrementFailed(): void
    {
        $this->increment('total_failed');
    }

    /**
     * Translate a type the REST API accepted into the value the column stores.
     * Anything else is handed back untouched, for validation to reject.
     */
    public static function typeFromApi(string $type): string
    {
        return self::API_TYPES[$type] ?? $type;
    }

    /**
     * Translate a stored type into the value the REST API publishes.
     */
    public static function typeToApi(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }

        return array_flip(self::API_TYPES)[$type] ?? $type;
    }

    /**
     * Check if a status transition is valid.
     */
    public function canTransitionTo(string $newStatus): bool
    {
        $allowedTransitions = self::STATUS_TRANSITIONS[$this->status] ?? [];

        return in_array($newStatus, $allowedTransitions, true);
    }

    /**
     * Transition to a new status with validation.
     *
     * @throws \InvalidArgumentException if the transition is not valid
     */
    public function transitionTo(string $newStatus): void
    {
        if (!$this->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException(
                "Cannot transition campaign from '{$this->status}' to '{$newStatus}'."
            );
        }

        $this->update(['status' => $newStatus]);
    }
}
