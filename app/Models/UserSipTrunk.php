<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Twilio\Rest\Client as TwilioClient;

class UserSipTrunk extends Model
{
    protected $fillable = [
        'user_id',
        'twilio_account_sid',
        'twilio_auth_token',
        'trunk_sid',
        'trunk_friendly_name',
        'trunk_domain_name',
        'origination_sip_url',
        'origination_url_sid',
        'termination_method',
        'ip_acl_sid',
        'credential_list_sid',
        'termination_username',
        'termination_password',
        'concurrent_calls_limit',
        'disaster_recovery_enabled',
        'recording_enabled',
        'cnam_lookup_enabled',
        'secure_trunking',
        'setup_step',
        'setup_progress',
        'status',
        'health_status',
        'last_health_check_at',
        'last_error',
        'total_calls_count',
        'total_minutes_used',
        'last_call_at',
        // Local PBX routing fields
        'routes_to_local_pbx',
        'local_pbx_sip_uri',
        'local_pbx_ip',
        'local_pbx_port',
        'local_pbx_transport',
        'local_pbx_username',
        'local_pbx_password',
        'twilio_termination_uri',
        'twilio_termination_sid',
    ];

    protected $hidden = [
        'twilio_auth_token',
        'termination_password',
        'local_pbx_password',
    ];

    protected $casts = [
        'disaster_recovery_enabled' => 'boolean',
        'recording_enabled' => 'boolean',
        'cnam_lookup_enabled' => 'boolean',
        'secure_trunking' => 'boolean',
        'setup_progress' => 'integer',
        'concurrent_calls_limit' => 'integer',
        'total_calls_count' => 'integer',
        'total_minutes_used' => 'decimal:2',
        'last_health_check_at' => 'datetime',
        'last_call_at' => 'datetime',
        'routes_to_local_pbx' => 'boolean',
        'local_pbx_port' => 'integer',
    ];

    /**
     * Get the user that owns this trunk
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get phone numbers assigned to this trunk
     */
    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(TrunkPhoneNumber::class, 'trunk_id');
    }

    /**
     * Get health check logs for this trunk
     */
    public function healthLogs(): HasMany
    {
        return $this->hasMany(TrunkHealthLog::class, 'trunk_id');
    }

    /**
     * Get Twilio client instance for this trunk
     */
    public function getTwilioClient(): TwilioClient
    {
        return new TwilioClient(
            $this->twilio_account_sid,
            decrypt($this->twilio_auth_token)
        );
    }

    /**
     * Check if trunk is operational
     */
    public function isOperational(): bool
    {
        return $this->status === 'active' && $this->health_status === 'healthy';
    }

    /**
     * Check if trunk setup is complete
     */
    public function isSetupComplete(): bool
    {
        return $this->setup_step === 'completed' && $this->setup_progress === 100;
    }

    /**
     * Get active phone numbers
     */
    public function getActiveNumbers()
    {
        return $this->phoneNumbers()->where('status', 'active')->get();
    }

    /**
     * Get unassigned phone numbers
     */
    public function getUnassignedNumbers()
    {
        return $this->phoneNumbers()
            ->where('status', 'active')
            ->where('assigned_to', 'unassigned')
            ->get();
    }

    /**
     * Increment call statistics
     */
    public function recordCall(float $duration): void
    {
        $this->increment('total_calls_count');
        $this->increment('total_minutes_used', round($duration / 60, 2));
        $this->update(['last_call_at' => now()]);
    }

    /**
     * Update health status
     */
    public function updateHealthStatus(string $status, ?string $error = null): void
    {
        $this->update([
            'health_status' => $status,
            'last_health_check_at' => now(),
            'last_error' => $error,
        ]);
    }

    /**
     * Scope: Active trunks only
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: Healthy trunks only
     */
    public function scopeHealthy($query)
    {
        return $query->where('health_status', 'healthy');
    }

    /**
     * Check if trunk routes to local PBX
     */
    public function routesToLocalPbx(): bool
    {
        return $this->routes_to_local_pbx && !empty($this->local_pbx_sip_uri);
    }

    /**
     * Get local PBX SIP URI (built from components or use raw value)
     */
    public function getLocalPbxUri(): ?string
    {
        if ($this->local_pbx_sip_uri) {
            return $this->local_pbx_sip_uri;
        }

        if ($this->local_pbx_ip && $this->local_pbx_port) {
            $transport = $this->local_pbx_transport ?? 'udp';
            return "sip:{$this->local_pbx_ip}:{$this->local_pbx_port};transport={$transport}";
        }

        return null;
    }

    /**
     * Get decrypted local PBX password
     */
    public function getLocalPbxPassword(): ?string
    {
        return $this->local_pbx_password ? decrypt($this->local_pbx_password) : null;
    }
}
