<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sequence extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'follow_up_sequences';

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'trigger_type',
        'trigger_conditions',
        'stop_conditions',
        'is_active',
        'use_smart_timing',
        'max_enrollments',
        'priority',
        'total_enrolled',
        'total_completed',
        'total_converted',
        'conversion_rate',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'use_smart_timing' => 'boolean',
        'priority' => 'integer',
        'trigger_conditions' => 'array',
        'stop_conditions' => 'array',
        'max_enrollments' => 'integer',
        'total_enrolled' => 'integer',
        'total_completed' => 'integer',
        'total_converted' => 'integer',
        // Float, not decimal:2 — the decimal cast serialises as a string, and
        // the sequence list calls toFixed() on this.
        'conversion_rate' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the user that owns the sequence.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the steps for the sequence.
     */
    public function steps(): HasMany
    {
        return $this->hasMany(SequenceStep::class)->orderBy('step_number');
    }

    /**
     * Get the enrollments for the sequence.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(SequenceEnrollment::class);
    }

    /**
     * Get active enrollments.
     */
    public function activeEnrollments(): HasMany
    {
        return $this->hasMany(SequenceEnrollment::class)->where('status', 'active');
    }

    /**
     * Get completed enrollments.
     */
    public function completedEnrollments(): HasMany
    {
        return $this->hasMany(SequenceEnrollment::class)->where('status', 'completed');
    }

    /**
     * Get converted enrollments.
     */
    public function convertedEnrollments(): HasMany
    {
        return $this->hasMany(SequenceEnrollment::class)->where('status', 'converted');
    }

    /**
     * Scope a query to only include active sequences.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include sequences for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include sequences for a specific trigger type.
     */
    public function scopeByTrigger($query, $triggerType)
    {
        return $query->where('trigger_type', $triggerType);
    }

    /**
     * Scope a query to order by priority.
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    /**
     * Enrol a contact, returning null when the sequence will not accept them.
     *
     * SequenceEngine::enrollContactInSequence() has always called this, but the
     * method did not exist — every enrolment threw "undefined method" and the
     * API answered 400 "Failed to enroll contact". Manual and automatic
     * enrolment were both dead.
     */
    public function enroll(Contact $contact, CampaignContact $campaignContact, array $metadata = []): ?SequenceEnrollment
    {
        if (! $this->is_active) {
            return null;
        }

        // One live enrolment per contact per sequence.
        $alreadyEnrolled = $this->enrollments()
            ->where('contact_id', $contact->id)
            ->whereIn('status', ['active', 'paused'])
            ->exists();

        if ($alreadyEnrolled) {
            return null;
        }

        if ($this->max_enrollments !== null && $this->enrollments()->count() >= $this->max_enrollments) {
            return null;
        }

        $enrollment = $this->enrollments()->create([
            'contact_id' => $contact->id,
            'campaign_contact_id' => $campaignContact->id,
            'campaign_id' => $campaignContact->campaign_id,
            'status' => 'active',
            'current_step' => 0,
            'steps_completed' => 0,
            'enrolled_at' => now(),
            'metadata' => $metadata,
        ]);

        $this->increment('total_enrolled');

        return $enrollment;
    }

    /**
     * Recalculate the cached counters from the enrolment rows.
     *
     * SequenceEnrollment::complete() calls this on every completion; like
     * enroll() above, it was never defined, so finishing an enrolment threw.
     */
    public function updateStatistics(): void
    {
        $this->total_enrolled = $this->enrollments()->count();
        $this->total_completed = $this->enrollments()->whereIn('status', ['completed', 'converted'])->count();
        $this->total_converted = $this->enrollments()->where('status', 'converted')->count();

        $this->conversion_rate = $this->total_enrolled > 0
            ? round(($this->total_converted / $this->total_enrolled) * 100, 2)
            : 0;

        $this->save();
    }

    /**
     * Share of enrollments that reached the end of the sequence, as a
     * percentage.
     *
     * SequenceEngine::getSequenceAnalytics() has always called this and
     * getAverageCompletionTime() below, but neither existed — the analytics
     * endpoint returned a 500 every time.
     */
    public function getSuccessRate(): float
    {
        $total = $this->enrollments()->count();

        if ($total === 0) {
            return 0.0;
        }

        $finished = $this->enrollments()
            ->whereIn('status', ['completed', 'converted'])
            ->count();

        return round(($finished / $total) * 100, 2);
    }

    /**
     * Average hours from enrolment to completion. Null when nothing has
     * completed yet.
     */
    public function getAverageCompletionTime(): ?float
    {
        $completed = $this->enrollments()
            ->whereNotNull('completed_at')
            ->whereNotNull('enrolled_at')
            ->get(['enrolled_at', 'completed_at']);

        if ($completed->isEmpty()) {
            return null;
        }

        $hours = $completed->map(
            fn ($enrollment) => $enrollment->enrolled_at->diffInHours($enrollment->completed_at)
        );

        return round($hours->avg(), 2);
    }
}
