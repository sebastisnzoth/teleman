<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConnectionPolicyTarget extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'connection_policy_id',
        'target_sid',
        'friendly_name',
        'provider_type',
        'sip_uri',
        'sip_username',
        'sip_password',
        'priority',
        'weight',
        'enabled',
        'total_calls',
        'successful_calls',
        'failed_calls',
        'last_call_at',
        'cost_per_minute',
        'currency',
        'metadata',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'priority' => 'integer',
        'weight' => 'integer',
        'total_calls' => 'integer',
        'successful_calls' => 'integer',
        'failed_calls' => 'integer',
        'last_call_at' => 'datetime',
        'cost_per_minute' => 'decimal:6',
        'metadata' => 'array',
    ];

    protected $appends = [
        'success_rate',
        'failure_rate',
        'provider_display_name',
    ];

    protected $hidden = [
        'sip_password',
    ];

    /**
     * Connection policy this target belongs to
     */
    public function connectionPolicy(): BelongsTo
    {
        return $this->belongsTo(ConnectionPolicy::class);
    }

    /**
     * Health logs for this target
     */
    public function healthLogs(): HasMany
    {
        return $this->hasMany(ByocHealthLog::class);
    }

    /**
     * Get success rate percentage
     */
    public function getSuccessRateAttribute(): float
    {
        if ($this->total_calls === 0) {
            return 0.0;
        }

        return round(($this->successful_calls / $this->total_calls) * 100, 2);
    }

    /**
     * Get failure rate percentage
     */
    public function getFailureRateAttribute(): float
    {
        if ($this->total_calls === 0) {
            return 0.0;
        }

        return round(($this->failed_calls / $this->total_calls) * 100, 2);
    }

    /**
     * Get human-readable provider name
     */
    public function getProviderDisplayNameAttribute(): string
    {
        return match($this->provider_type) {
            'zadarma' => 'Zadarma',
            'voipms' => 'VoIP.ms',
            'custom' => 'Custom SIP Provider',
            default => 'Unknown Provider',
        };
    }

    /**
     * Get encrypted password
     */
    public function getSipPasswordAttribute($value): ?string
    {
        return $value ? decrypt($value) : null;
    }

    /**
     * Set encrypted password
     */
    public function setSipPasswordAttribute($value): void
    {
        $this->attributes['sip_password'] = $value ? encrypt($value) : null;
    }

    /**
     * Build full SIP URI with credentials
     */
    public function getFullSipUri(): string
    {
        if ($this->sip_username && $this->sip_password) {
            // Parse existing URI to inject credentials
            $uri = parse_url($this->sip_uri);
            $host = $uri['host'] ?? $this->sip_uri;
            $port = isset($uri['port']) ? ':' . $uri['port'] : '';
            
            return "sip:{$this->sip_username}:{$this->sip_password}@{$host}{$port}";
        }

        return $this->sip_uri;
    }

    /**
     * Record successful call
     */
    public function recordSuccess(): void
    {
        $this->increment('total_calls');
        $this->increment('successful_calls');
        $this->update(['last_call_at' => now()]);
    }

    /**
     * Record failed call
     */
    public function recordFailure(): void
    {
        $this->increment('total_calls');
        $this->increment('failed_calls');
        $this->update(['last_call_at' => now()]);
    }

    /**
     * Get provider-specific configuration hints
     */
    public function getProviderHints(): array
    {
        return match($this->provider_type) {
            'zadarma' => [
                'sip_uri_format' => 'sip:username@sip.zadarma.com',
                'documentation' => 'https://zadarma.com/en/support/instructions/sip/',
                'notes' => 'Use your Zadarma SIP credentials. Usually format: accountnumber@sip.zadarma.com',
            ],
            'voipms' => [
                'sip_uri_format' => 'sip:username@server.voip.ms',
                'documentation' => 'https://wiki.voip.ms/article/SIP',
                'notes' => 'Replace "server" with your assigned server (e.g., atlanta.voip.ms)',
            ],
            'custom' => [
                'sip_uri_format' => 'sip:username@your-provider.com',
                'documentation' => null,
                'notes' => 'Contact your SIP provider for the correct SIP URI format',
            ],
            default => [],
        };
    }

    /**
     * Estimate monthly cost based on usage
     */
    public function estimateMonthlyCost(int $monthlyMinutes): float
    {
        if (!$this->cost_per_minute) {
            return 0.0;
        }

        return round($monthlyMinutes * $this->cost_per_minute, 2);
    }
}
