<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Twilio\Rest\Client as TwilioClient;

class ByocTrunk extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'trunk_sid',
        'trunk_friendly_name',
        'connection_policy_sid',
        'connection_policy_name',
        'voice_url',
        'voice_method',
        'cnam_lookup_enabled',
        'from_domain_sid',
        'status',
        'setup_progress',
        'setup_step',
        'setup_error',
        'is_setup_complete',
        'total_calls',
        'total_minutes',
        'total_cost',
        'last_call_at',
        'health_status',
        'last_health_check_at',
    ];

    protected $casts = [
        'cnam_lookup_enabled' => 'boolean',
        'is_setup_complete' => 'boolean',
        'setup_progress' => 'integer',
        'total_calls' => 'integer',
        'total_minutes' => 'integer',
        'total_cost' => 'decimal:4',
        'last_call_at' => 'datetime',
        'last_health_check_at' => 'datetime',
    ];

    protected $appends = [
        'is_operational',
        'average_cost_per_minute',
    ];

    /**
     * User who owns this BYOC trunk
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Connection policy that defines routing
     */
    public function connectionPolicy(): HasOne
    {
        return $this->hasOne(ConnectionPolicy::class);
    }

    /**
     * Health check logs
     */
    public function healthLogs(): HasMany
    {
        return $this->hasMany(ByocHealthLog::class);
    }

    /**
     * Check if trunk is operational
     */
    public function getIsOperationalAttribute(): bool
    {
        return $this->status === 'active' && $this->is_setup_complete;
    }

    /**
     * Get average cost per minute
     */
    public function getAverageCostPerMinuteAttribute(): float
    {
        if ($this->total_minutes === 0) {
            return 0.0;
        }

        return round($this->total_cost / $this->total_minutes, 6);
    }

    /**
     * Get Twilio client for this user's account
     */
    public function getTwilioClient(): TwilioClient
    {
        $accountSid = $this->user->twilio_account_sid;
        $authToken = $this->user->twilio_auth_token;

        if (!$accountSid || !$authToken) {
            throw new \Exception('Twilio credentials not configured for this user');
        }

        return new TwilioClient($accountSid, $authToken);
    }

    /**
     * Mark trunk as failed with error message
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'setup_error' => $error,
            'health_status' => 'down',
        ]);
    }

    /**
     * Update setup progress
     */
    public function updateProgress(int $progress, string $step): void
    {
        $this->update([
            'setup_progress' => $progress,
            'setup_step' => $step,
        ]);
    }

    /**
     * Mark setup as complete
     */
    public function markSetupComplete(): void
    {
        $this->update([
            'status' => 'active',
            'is_setup_complete' => true,
            'setup_progress' => 100,
            'setup_step' => 'Setup complete',
            'setup_error' => null,
            'health_status' => 'healthy',
            'last_health_check_at' => now(),
        ]);
    }

    /**
     * Record call statistics
     */
    public function recordCall(int $durationSeconds, float $cost): void
    {
        $this->increment('total_calls');
        $this->increment('total_minutes', ceil($durationSeconds / 60));
        $this->increment('total_cost', $cost);
        $this->update(['last_call_at' => now()]);
    }

    /**
     * Get recent health logs
     */
    public function getRecentHealthLogs(int $limit = 10)
    {
        return $this->healthLogs()
            ->latest('checked_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Check if trunk needs health check
     */
    public function needsHealthCheck(): bool
    {
        if (!$this->last_health_check_at) {
            return true;
        }

        // Check every 5 minutes
        return $this->last_health_check_at->lt(now()->subMinutes(5));
    }
}
