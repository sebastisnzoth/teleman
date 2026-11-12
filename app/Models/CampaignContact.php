<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'contact_id', // Link to global contact
        'phone_number',
        'first_name',
        'last_name',
        'email',
        'company',
        'variables',
        'status',
        'call_attempts',
        'last_call_at',
        'call_id',
        'dtmf_response',
        'opted_out',
        'scheduled_call_time',
        'timezone',
    ];

    protected $casts = [
        'variables' => 'array',
        'call_attempts' => 'integer',
        'last_call_at' => 'datetime',
        'opted_out' => 'boolean',
    ];

    // Relationships
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    /**
     * All calls associated with this campaign contact
     */
    public function calls(): HasMany
    {
        return $this->hasMany(Call::class, 'campaign_contact_id');
    }

    /**
     * Campaign contact may link to a global contact
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCalling($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeCanRetry($query, int $maxAttempts)
    {
        return $query->where('status', 'failed')
            ->where('call_attempts', '<', $maxAttempts);
    }

    // Helper Methods
    public function markAsCalling(): void
    {
        $this->update([
            'status' => 'calling',
            'last_call_at' => now(),
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update(['status' => 'completed']);
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => 'failed']);
    }

    public function incrementAttempts(): void
    {
        $this->increment('call_attempts');
    }

    public function canRetry(int $maxAttempts): bool
    {
        return $this->status === 'failed' && $this->call_attempts < $maxAttempts;
    }
}
