<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class TwilioCredential extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'account_sid',
        'auth_token',
        'phone_number',
        'twiml_app_sid',
        'api_key_sid',
        'api_key_secret',
        'is_active',
        'verified_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'verified_at' => 'datetime',
    ];

    protected $hidden = [
        'auth_token',
        'api_key_secret',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVerified($query)
    {
        return $query->whereNotNull('verified_at');
    }

    // Helper Methods
    public function markAsVerified(): void
    {
        $this->update(['verified_at' => now()]);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}
