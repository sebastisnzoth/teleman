<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmIntegration extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'webhook_url',
        'events',
        'auth_type',
        'auth_credentials',
        'is_active',
        'last_triggered_at',
        'total_triggers',
        'last_error',
    ];

    protected $casts = [
        'events' => 'array',
        'auth_credentials' => 'array',
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
        'total_triggers' => 'integer',
    ];

    protected $hidden = [
        'auth_credentials',
    ];

    /**
     * Get the user that owns this integration
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the webhook logs for this integration
     */
    public function webhookLogs(): HasMany
    {
        return $this->hasMany(CrmWebhookLog::class);
    }

    /**
     * Check if this integration is subscribed to an event
     */
    public function isSubscribedTo(string $event): bool
    {
        return in_array($event, $this->events ?? []);
    }

    /**
     * Increment the trigger counter
     */
    public function incrementTriggers(): void
    {
        $this->increment('total_triggers');
        $this->update(['last_triggered_at' => now()]);
    }

    /**
     * Record an error
     */
    public function recordError(string $error): void
    {
        $this->update(['last_error' => $error]);
    }

    /**
     * Clear the last error
     */
    public function clearError(): void
    {
        $this->update(['last_error' => null]);
    }

    /**
     * Get only active integrations
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get integrations subscribed to a specific event
     */
    public function scopeSubscribedTo($query, string $event)
    {
        return $query->whereJsonContains('events', $event);
    }
}
