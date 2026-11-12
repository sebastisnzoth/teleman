<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\HasRolesAndPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable, HasRolesAndPermissions, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'timezone',
        'twilio_configured',
        'parent_user_id',
        'status',
        'last_login_at',
        'credit_balance',
        'preferred_currency',
        'locale',
        'low_balance_alert_enabled',
        'low_balance_threshold',
        'last_low_balance_alert_at',
        'webhook_token',
        'queue_enabled',
        'max_queue_size',
        'max_wait_seconds',
        'queue_greeting_message',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'twilio_configured' => 'boolean',
            'last_login_at' => 'datetime',
            'credit_balance' => 'decimal:2',
            'low_balance_threshold' => 'decimal:2',
            'low_balance_alert_enabled' => 'boolean',
            'last_low_balance_alert_at' => 'datetime',
            'queue_enabled' => 'boolean',
            'max_queue_size' => 'integer',
            'max_wait_seconds' => 'integer',
        ];
    }

    // Relationships
    public function twilioCredential(): HasOne
    {
        return $this->hasOne(TwilioCredential::class);
    }

    public function twilioCredentials(): HasMany
    {
        return $this->hasMany(TwilioCredential::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class);
    }

    public function callQueueEntries(): HasMany
    {
        return $this->hasMany(CallQueueEntry::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function audioFiles(): HasMany
    {
        return $this->hasMany(AudioFile::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(PhoneNumber::class, 'user_id');
    }

    public function aiAgents(): HasMany
    {
        return $this->hasMany(AiAgent::class);
    }

    public function smsTemplates(): HasMany
    {
        return $this->hasMany(SmsTemplate::class);
    }

    public function kycVerification(): HasOne
    {
        return $this->hasOne(UserKycVerification::class);
    }

    public function sipTrunks(): HasMany
    {
        return $this->hasMany(UserSipTrunk::class);
    }

    public function sipTrunk(): HasOne
    {
        return $this->hasOne(UserSipTrunk::class)->latestOfMany();
    }

    public function byocTrunks(): HasMany
    {
        return $this->hasMany(ByocTrunk::class);
    }

    public function byocTrunk(): HasOne
    {
        return $this->hasOne(ByocTrunk::class)->latestOfMany();
    }

    // Helper Methods
    public function hasTwilioConfigured(): bool
    {
        return $this->twilio_configured && $this->twilioCredential()->active()->verified()->exists();
    }

    public function hasSipTrunk(): bool
    {
        return $this->sipTrunks()->whereIn('status', ['active', 'setting_up'])->exists();
    }

    public function hasActiveByocTrunk(): bool
    {
        return $this->byocTrunk()->where('status', 'active')->exists();
    }

    public function getActiveSipTrunk(): ?UserSipTrunk
    {
        return $this->sipTrunk()->where('status', 'active')->first();
    }

    public function getActiveByocTrunk(): ?ByocTrunk
    {
        return $this->byocTrunk()->where('status', 'active')->first();
    }

    public function getActiveTwilioCredential(): ?TwilioCredential
    {
        return $this->twilioCredential()->active()->verified()->first();
    }

    public function markTwilioAsConfigured(): void
    {
        // Use direct DB update to avoid issues with loaded relationships
        DB::table('users')
            ->where('id', $this->id)
            ->update([
                'twilio_configured' => true,
                'updated_at' => now(),
            ]);
        
        // Refresh the model to get the updated value
        $this->twilio_configured = true;
        $this->updated_at = now();
    }

    public function markTwilioAsNotConfigured(): void
    {
        // Use direct DB update to avoid issues with loaded relationships
        DB::table('users')
            ->where('id', $this->id)
            ->update([
                'twilio_configured' => false,
                'updated_at' => now(),
            ]);
        
        // Refresh the model to get the updated value
        $this->twilio_configured = false;
        $this->updated_at = now();
    }

    // KYC Helper Methods
    public function hasKycVerification(): bool
    {
        return $this->kycVerification()->exists();
    }

    public function getKycTier(): string
    {
        // Admins and agents don't need KYC - treat as business tier
        if ($this->hasRole(['admin', 'agent'])) {
            return 'business';
        }
        
        return $this->kycVerification?->kyc_tier ?? 'unverified';
    }

    public function isKycUnverified(): bool
    {
        // If KYC is disabled globally, no users are unverified
        if (!KycSetting::get('kyc_enabled', true)) {
            return false;
        }
        
        // If user has approved KYC, they are not unverified
        if ($this->kycVerification?->status === 'approved') {
            return false;
        }
        
        return $this->getKycTier() === 'unverified';
    }

    public function isKycBasic(): bool
    {
        return $this->getKycTier() === 'basic' && $this->kycVerification?->isApproved();
    }

    public function isKycBusiness(): bool
    {
        return $this->getKycTier() === 'business' && $this->kycVerification?->isApproved();
    }

    public function canAccessFeature(string $feature): bool
    {
        // Admins and agents have full access regardless of KYC
        if ($this->hasRole(['admin', 'agent'])) {
            return true;
        }
        
        $tier = $this->getKycTier();
        
        // Feature access based on KYC tier (for customers only)
        $tierAccess = [
            'unverified' => [],
            'basic' => ['phone_numbers', 'campaigns', 'calls', 'sms', 'contacts'],
            'business' => ['phone_numbers', 'campaigns', 'calls', 'sms', 'contacts', 'ai_agents', 'advanced_features'],
        ];

        return in_array($feature, $tierAccess[$tier] ?? []);
    }

    public function getKycLimits(): array
    {
        // Admins and agents have unlimited access
        if ($this->hasRole(['admin', 'agent'])) {
            return [
                'max_phone_numbers' => null,
                'max_calls_per_day' => null,
                'max_deposit' => null,
            ];
        }
        
        $tier = $this->getKycTier();
        
        return match ($tier) {
            'unverified' => [
                'max_phone_numbers' => 0,
                'max_calls_per_day' => 0,
                'max_deposit' => 0,
            ],
            'basic' => [
                'max_phone_numbers' => 3,
                'max_calls_per_day' => 100,
                'max_deposit' => 500,
            ],
            'business' => [
                'max_phone_numbers' => null, // unlimited
                'max_calls_per_day' => null, // unlimited
                'max_deposit' => null, // unlimited
            ],
            default => [
                'max_phone_numbers' => 0,
                'max_calls_per_day' => 0,
                'max_deposit' => 0,
            ],
        };
    }
}
