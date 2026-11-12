<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingRule extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'service_type',
        'country_code',
        'country_name',
        'base_cost',
        'base_cost_unit',
        'markup_type',
        'markup_value',
        'markup_fixed',
        'customer_charge',
        'minimum_charge',
        'tier',
        'notes',
        'is_active',
        'is_pinned',
        'auto_update_base_cost',
        'last_cost_update',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'base_cost' => 'decimal:4',
        'markup_value' => 'decimal:4',
        'markup_fixed' => 'decimal:4',
        'customer_charge' => 'decimal:4',
        'minimum_charge' => 'decimal:4',
        'is_active' => 'boolean',
        'is_pinned' => 'boolean',
        'auto_update_base_cost' => 'boolean',
        'last_cost_update' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope a query to only include active rules.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query for specific service type.
     */
    public function scopeForService($query, string $serviceType)
    {
        return $query->where('service_type', $serviceType);
    }

    /**
     * Scope a query for specific country.
     */
    public function scopeForCountry($query, string $countryCode)
    {
        return $query->where('country_code', $countryCode);
    }

    /**
     * Scope a query for specific tier.
     */
    public function scopeForTier($query, string $tier)
    {
        return $query->where('tier', $tier);
    }

    /**
     * Calculate the customer charge based on base cost.
     *
     * @param float $baseCost The actual cost from provider
     * @return float The amount to charge customer
     */
    public function calculateCharge(float $baseCost): float
    {
        $charge = $baseCost;

        // Apply markup based on type
        switch ($this->markup_type) {
            case 'percentage':
                $charge = $baseCost * (1 + ($this->markup_value / 100));
                break;

            case 'fixed':
                $charge = $baseCost + $this->markup_value;
                break;

            case 'hybrid':
                $charge = $baseCost * (1 + ($this->markup_value / 100)) + ($this->markup_fixed ?? 0);
                break;
        }

        // Apply minimum charge
        return max($charge, $this->minimum_charge);
    }

    /**
     * Calculate profit for a given base cost.
     *
     * @param float $baseCost
     * @return array ['charge' => float, 'profit' => float, 'margin' => float]
     */
    public function calculateProfit(float $baseCost): array
    {
        $charge = $this->calculateCharge($baseCost);
        $profit = $charge - $baseCost;
        $margin = $baseCost > 0 ? ($profit / $baseCost) * 100 : 0;

        return [
            'charge' => round($charge, 4),
            'profit' => round($profit, 4),
            'margin' => round($margin, 2),
        ];
    }

    /**
     * Get formatted display name.
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->country_name} - " . ucfirst($this->service_type) . " ({$this->tier})";
    }
}
