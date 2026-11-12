<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NumberRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone_number_id',
        'customer_id',
        'status',
        'admin_id',
        'processed_at',
        'customer_notes',
        'admin_notes',
        'requested_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'requested_at' => 'datetime',
    ];

    /**
     * Get the phone number being requested
     */
    public function phoneNumber()
    {
        return $this->belongsTo(PhoneNumber::class);
    }

    /**
     * Get the customer who made the request
     */
    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * Get the admin who processed the request
     */
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Scope: Get only pending requests
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Get only approved requests
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope: Get only rejected requests
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope: Get only cancelled requests
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope: Get requests for a specific customer
     */
    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Approve the request and assign number to customer
     * Also syncs with trunk_phone_numbers if it's a trunk number
     */
    public function approve($adminId, $notes = null)
    {
        \DB::transaction(function () use ($adminId, $notes) {
            $this->update([
                'status' => 'approved',
                'admin_id' => $adminId,
                'admin_notes' => $notes,
                'processed_at' => now(),
            ]);

            // Auto-assign number to customer
            $this->phoneNumber->assignToCustomer($this->customer_id, $adminId);

            // If this is a SIP trunk number, update trunk_phone_numbers table
            if ($this->phoneNumber->isTrunkNumber() && $this->phoneNumber->trunkPhoneNumber) {
                $this->phoneNumber->trunkPhoneNumber->update([
                    'assigned_to' => 'customer',
                    'assigned_id' => $this->customer_id,
                ]);
            }
        });

        return $this;
    }

    /**
     * Reject the request and release number back to available
     */
    public function reject($adminId, $notes)
    {
        $this->update([
            'status' => 'rejected',
            'admin_id' => $adminId,
            'admin_notes' => $notes,
            'processed_at' => now(),
        ]);

        // Release number back to available pool
        $this->phoneNumber->release();

        return $this;
    }

    /**
     * Cancel the request (by customer)
     */
    public function cancel()
    {
        $this->update([
            'status' => 'cancelled',
            'processed_at' => now(),
        ]);

        // Release number back to available pool
        $this->phoneNumber->release();

        return $this;
    }

    /**
     * Check if request is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if request is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if request is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if request can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if request can be processed
     */
    public function canBeProcessed(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Get formatted request status for display
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending' => 'Pending Review',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'yellow',
            'approved' => 'green',
            'rejected' => 'red',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }
}
