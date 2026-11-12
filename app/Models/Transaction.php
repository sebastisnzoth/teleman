<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaction extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'currency',
        'balance_before',
        'balance_after',
        'description',
        'reference_type',
        'reference_id',
        'payment_gateway',
        'payment_id',
        'status',
        'metadata',
        'admin_id',
        'actual_cost',
        'profit_amount',
        'profit_percentage',
        'service_type',
        'pricing_tier',
        'cost_status',
        'original_amount',
        'original_currency',
        'exchange_rate',
        'base_currency',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'actual_cost' => 'decimal:4',
        'profit_amount' => 'decimal:4',
        'profit_percentage' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the transaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the admin who performed manual adjustment.
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Get the reference model (Call, Campaign, SMS, etc).
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to only include credit transactions.
     */
    public function scopeCredits($query)
    {
        return $query->where('type', 'credit');
    }

    /**
     * Scope a query to only include debit transactions.
     */
    public function scopeDebits($query)
    {
        return $query->where('type', 'debit');
    }

    /**
     * Scope a query to only include pending transactions.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include completed transactions.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to filter by transaction type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to filter by payment gateway.
     */
    public function scopeByGateway($query, string $gateway)
    {
        return $query->where('payment_gateway', $gateway);
    }

    /**
     * Scope a query to filter by reference type.
     */
    public function scopeByReferenceType($query, string $referenceType)
    {
        return $query->where('reference_type', $referenceType);
    }

    /**
     * Scope a query to filter by user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to filter by service type.
     */
    public function scopeByServiceType($query, string $serviceType)
    {
        return $query->where('service_type', $serviceType);
    }

    /**
     * Scope a query to only include transactions with profit.
     */
    public function scopeWithProfit($query)
    {
        return $query->whereNotNull('profit_amount');
    }

    /**
     * Scope a query to only include confirmed cost transactions.
     */
    public function scopeCostConfirmed($query)
    {
        return $query->where('cost_status', 'confirmed');
    }

    /**
     * Get formatted amount with currency symbol.
     */
    public function getFormattedAmountAttribute(): string
    {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'INR' => '₹',
            'AUD' => 'A$',
            'CAD' => 'C$',
            'JPY' => '¥',
        ];

        $symbol = $symbols[$this->currency] ?? $this->currency . ' ';
        return $symbol . number_format($this->amount, 2);
    }

    /**
     * Get status badge HTML class.
     */
    public function getStatusBadgeAttribute(): string
    {
        $classes = [
            'pending' => 'badge-warning',
            'completed' => 'badge-success',
            'failed' => 'badge-error',
            'refunded' => 'badge-info',
        ];

        return $classes[$this->status] ?? 'badge-neutral';
    }

    /**
     * Get status badge color for Tailwind.
     */
    public function getStatusColorAttribute(): string
    {
        $colors = [
            'pending' => 'yellow',
            'completed' => 'green',
            'failed' => 'red',
            'refunded' => 'blue',
        ];

        return $colors[$this->status] ?? 'gray';
    }

    /**
     * Check if transaction is a credit.
     */
    public function isCredit(): bool
    {
        return $this->type === 'credit';
    }

    /**
     * Check if transaction is a debit.
     */
    public function isDebit(): bool
    {
        return $this->type === 'debit';
    }

    /**
     * Check if transaction is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if transaction is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if transaction is failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if transaction is refunded.
     */
    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    /**
     * Get transaction type icon.
     */
    public function getTypeIconAttribute(): string
    {
        return $this->type === 'credit' ? '↑' : '↓';
    }

    /**
     * Get transaction amount with sign.
     */
    public function getSignedAmountAttribute(): string
    {
        $prefix = $this->type === 'credit' ? '+' : '-';
        return $prefix . $this->formatted_amount;
    }
}
