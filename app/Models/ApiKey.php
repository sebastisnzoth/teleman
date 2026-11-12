<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'key',
        'name',
        'type', // 'live' or 'test'
        'is_active',
        'allowed_domains',
        'permissions',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'allowed_domains' => 'array',
        'permissions' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'key',
    ];

    /**
     * Get the user that owns the API key
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a new API key
     */
    public static function generate(int $userId, string $name, string $type = 'live'): self
    {
        $prefix = $type === 'live' ? 'pk_live_' : 'pk_test_';
        $randomString = Str::random(32);
        
        return self::create([
            'user_id' => $userId,
            'key' => $prefix . $randomString,
            'name' => $name,
            'type' => $type,
            'is_active' => true,
            'permissions' => ['calls', 'sms'],
        ]);
    }

    /**
     * Check if the API key is valid
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Mark the API key as used
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Check if domain is allowed
     */
    public function isDomainAllowed(string $domain): bool
    {
        // If no domains specified, allow all
        if (empty($this->allowed_domains)) {
            return true;
        }

        return in_array($domain, $this->allowed_domains);
    }

    /**
     * Scope to only active keys
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }
}
