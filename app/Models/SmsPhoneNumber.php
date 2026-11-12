<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class SmsPhoneNumber extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'phone_number',
        'friendly_name',
        'ai_agent_id',
        'is_active',
        'capabilities',
        'settings',
        'provider_sid',
        'account_sid',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capabilities' => 'array',
        'settings' => 'array',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aiAgent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(SmsConversation::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeWithAgent(Builder $query): Builder
    {
        return $query->whereNotNull('ai_agent_id');
    }

    public function scopeWithoutAgent(Builder $query): Builder
    {
        return $query->whereNull('ai_agent_id');
    }

    // Accessors
    public function getFormattedPhoneNumberAttribute(): string
    {
        // Format: +1 (555) 123-4567
        $number = preg_replace('/[^0-9]/', '', $this->phone_number);
        if (strlen($number) === 11 && $number[0] === '1') {
            return sprintf('+%s (%s) %s-%s',
                substr($number, 0, 1),
                substr($number, 1, 3),
                substr($number, 4, 3),
                substr($number, 7, 4)
            );
        }
        return $this->phone_number;
    }

    public function getHasAgentAttribute(): bool
    {
        return !is_null($this->ai_agent_id);
    }
}