<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class SmsConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone_number_id',
        'contact_phone',
        'contact_name',
        'contact_id',
        'ai_agent_id',
        'status',
        'last_message_at',
        'unread_count',
        'metadata',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'metadata' => 'array',
    ];

    // Relationships
    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(PhoneNumber::class);
    }

    // Alias for backwards compatibility
    public function smsPhoneNumber(): BelongsTo
    {
        return $this->phoneNumber();
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function aiAgent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SmsMessage::class, 'conversation_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(AiAgentSmsSession::class, 'conversation_id');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', 'archived');
    }

    public function scopeWithUnread(Builder $query): Builder
    {
        return $query->where('unread_count', '>', 0);
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderBy('last_message_at', 'desc');
    }

    // Methods
    public function incrementUnread(): void
    {
        $this->increment('unread_count');
    }

    public function markAsRead(): void
    {
        $this->update(['unread_count' => 0]);
    }

    public function updateLastMessageTime(): void
    {
        $this->update(['last_message_at' => now()]);
    }

    // Accessors
    public function getLatestMessageAttribute()
    {
        return $this->messages()->latest()->first();
    }
}