<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Contact extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'phone_number',
        'first_name',
        'last_name',
        'email',
        'company',
        'website',
        'facebook_url',
        'instagram_url',
        'linkedin_url',
        'twitter_url',
        'job_title',
        'notes',
        'timezone',
        'language',
        'custom_fields',
        'status',
        'opted_out',
        'opted_out_at',
        'source',
        'total_campaigns',
        'total_calls',
        'successful_calls',
        'last_contacted_at',
        'engagement_score',
        'data_quality_score',
        'quality_issues',
        'ai_suggestions',
        'manually_verified',
        'quality_checked_at',
        'optimal_call_time',
        'optimal_call_confidence',
        'best_call_hours',
    ];

    protected $casts = [
        'custom_fields' => 'array',
        'opted_out' => 'boolean',
        'opted_out_at' => 'datetime',
        'last_contacted_at' => 'datetime',
        'total_campaigns' => 'integer',
        'total_calls' => 'integer',
        'successful_calls' => 'integer',
        'engagement_score' => 'float',
        'data_quality_score' => 'integer',
        'quality_issues' => 'array',
        'ai_suggestions' => 'array',
        'manually_verified' => 'boolean',
        'quality_checked_at' => 'datetime',
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Contact belongs to a user (owner)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Contact can be in many campaigns (through campaign_contacts)
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(CampaignContact::class);
    }

    /**
     * Contact can have many calls
     */
    public function calls(): HasMany
    {
        return $this->hasMany(Call::class, 'to_number', 'phone_number');
    }

    /**
     * Contact can be in many contact lists
     */
    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(ContactList::class, 'contact_list_members')
            ->withTimestamps();
    }

    /**
     * Contact can have many tags
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ContactTag::class, 'contact_tag_assignments')
            ->withTimestamps();
    }

    // ==================== SCOPES ====================

    /**
     * Scope to get only active contacts
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get only inactive contacts
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', 'inactive');
    }

    /**
     * Scope to get only blocked contacts
     */
    public function scopeBlocked(Builder $query): Builder
    {
        return $query->where('status', 'blocked');
    }

    /**
     * Scope to get opted out contacts
     */
    public function scopeOptedOut(Builder $query): Builder
    {
        return $query->where('opted_out', true);
    }

    /**
     * Scope to filter by company
     */
    public function scopeByCompany(Builder $query, string $company): Builder
    {
        return $query->where('company', 'LIKE', "%{$company}%");
    }

    /**
     * Scope to get recently contacted contacts
     */
    public function scopeRecentlyContacted(Builder $query, int $days = 30): Builder
    {
        return $query->where('last_contacted_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to search contacts by name, email, phone, or company
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('first_name', 'LIKE', "%{$search}%")
                ->orWhere('last_name', 'LIKE', "%{$search}%")
                ->orWhere('email', 'LIKE', "%{$search}%")
                ->orWhere('phone_number', 'LIKE', "%{$search}%")
                ->orWhere('company', 'LIKE', "%{$search}%");
        });
    }

    /**
     * Scope to filter by engagement score range
     */
    public function scopeEngagementRange(Builder $query, float $min, float $max): Builder
    {
        return $query->whereBetween('engagement_score', [$min, $max]);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Get contact's full name
     */
    public function getFullNameAttribute(): string
    {
        $parts = array_filter([$this->first_name, $this->last_name]);
        return implode(' ', $parts) ?: 'Unknown';
    }

    /**
     * Calculate and update engagement score
     */
    public function calculateEngagementScore(): float
    {
        if ($this->total_calls === 0) {
            return 0.00;
        }

        // Base score from success rate (60% weight)
        $successRate = ($this->successful_calls / $this->total_calls) * 60;

        // Recency bonus (20% weight)
        $recencyScore = 0;
        if ($this->last_contacted_at) {
            $daysSinceContact = $this->last_contacted_at->diffInDays(now());
            if ($daysSinceContact <= 7) {
                $recencyScore = 20;
            } elseif ($daysSinceContact <= 30) {
                $recencyScore = 15;
            } elseif ($daysSinceContact <= 90) {
                $recencyScore = 10;
            } else {
                $recencyScore = 5;
            }
        }

        // Activity bonus (20% weight)
        $activityScore = min(($this->total_campaigns / 5) * 20, 20);

        $score = $successRate + $recencyScore + $activityScore;

        // Update the score
        $this->update(['engagement_score' => round($score, 2)]);

        return round($score, 2);
    }

    /**
     * Mark contact as opted out
     */
    public function optOut(): void
    {
        $this->update([
            'opted_out' => true,
            'opted_out_at' => now(),
            'status' => 'inactive',
        ]);
    }

    /**
     * Opt back in
     */
    public function optIn(): void
    {
        $this->update([
            'opted_out' => false,
            'opted_out_at' => null,
            'status' => 'active',
        ]);
    }

    /**
     * Block contact
     */
    public function block(): void
    {
        $this->update(['status' => 'blocked']);
    }

    /**
     * Unblock contact
     */
    public function unblock(): void
    {
        $this->update(['status' => 'active']);
    }

    /**
     * Increment campaign counter
     */
    public function incrementCampaigns(): void
    {
        $this->increment('total_campaigns');
    }

    /**
     * Increment call counters
     */
    public function incrementCalls(bool $successful = false): void
    {
        $this->increment('total_calls');
        if ($successful) {
            $this->increment('successful_calls');
        }
        $this->update(['last_contacted_at' => now()]);
    }

    /**
     * Add contact to a list
     */
    public function addToList(int $listId): void
    {
        if (!$this->lists()->where('contact_list_id', $listId)->exists()) {
            $this->lists()->attach($listId);
        }
    }

    /**
     * Remove contact from a list
     */
    public function removeFromList(int $listId): void
    {
        $this->lists()->detach($listId);
    }

    /**
     * Add tag to contact
     */
    public function addTag(int $tagId): void
    {
        if (!$this->tags()->where('contact_tag_id', $tagId)->exists()) {
            $this->tags()->attach($tagId);
        }
    }

    /**
     * Remove tag from contact
     */
    public function removeTag(int $tagId): void
    {
        $this->tags()->detach($tagId);
    }

    /**
     * Check if contact can be contacted (not opted out, not blocked)
     */
    public function canBeContacted(): bool
    {
        return !$this->opted_out && $this->status !== 'blocked';
    }
}
