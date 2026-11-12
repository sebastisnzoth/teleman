<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrunkPhoneNumber extends Model
{
    protected $fillable = [
        'trunk_id',
        'phone_number_sid',
        'phone_number',
        'friendly_name',
        'country_code',
        'capabilities',
        'assigned_to',
        'assigned_id',
        'user_id',
        'status',
    ];

    protected $casts = [
        'capabilities' => 'array',
    ];

    /**
     * Get the trunk this number belongs to
     */
    public function trunk(): BelongsTo
    {
        return $this->belongsTo(UserSipTrunk::class, 'trunk_id');
    }

    /**
     * Get the user this number is assigned to
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Check if number has voice capability
     */
    public function hasVoice(): bool
    {
        return $this->capabilities['voice'] ?? false;
    }

    /**
     * Check if number has SMS capability
     */
    public function hasSms(): bool
    {
        return $this->capabilities['sms'] ?? false;
    }

    /**
     * Check if number has MMS capability
     */
    public function hasMms(): bool
    {
        return $this->capabilities['mms'] ?? false;
    }

    /**
     * Assign number to a resource
     */
    public function assignTo(string $type, ?int $id = null): void
    {
        $this->update([
            'assigned_to' => $type,
            'assigned_id' => $id,
        ]);
    }

    /**
     * Assign number to softphone for specific user
     */
    public function assignToUser(int $userId): void
    {
        $this->update([
            'assigned_to' => 'softphone',
            'assigned_id' => null,
            'user_id' => $userId,
        ]);
    }

    /**
     * Unassign number
     */
    public function unassign(): void
    {
        $this->update([
            'assigned_to' => 'unassigned',
            'assigned_id' => null,
            'user_id' => null,
        ]);
    }

    /**
     * Check if number is available for assignment
     */
    public function isAvailable(): bool
    {
        return $this->status === 'active' && $this->assigned_to === 'unassigned';
    }

    /**
     * Get formatted phone number
     */
    public function getFormattedAttribute(): string
    {
        $number = $this->phone_number;
        
        // Format US/CA numbers as (XXX) XXX-XXXX
        if (preg_match('/^\+1(\d{3})(\d{3})(\d{4})$/', $number, $matches)) {
            return sprintf('(%s) %s-%s', $matches[1], $matches[2], $matches[3]);
        }
        
        return $number;
    }

    /**
     * Scope: Active numbers only
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: Available for assignment
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'active')
                    ->where('assigned_to', 'unassigned');
    }

    /**
     * Scope: Assigned to specific type
     */
    public function scopeAssignedTo($query, string $type)
    {
        return $query->where('assigned_to', $type);
    }

    /**
     * Get the main phone number record (in phone_numbers table)
     */
    public function phoneNumber()
    {
        return $this->hasOne(PhoneNumber::class, 'trunk_phone_number_id');
    }
}

