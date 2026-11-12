<?php

namespace App\Services;

use App\Models\PricingRule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PricingService
{
    /**
     * Get pricing rule for a service and country.
     *
     * @param string $serviceType
     * @param string $countryCode
     * @param string $tier
     * @return PricingRule|null
     */
    public function getPricingRule(string $serviceType, string $countryCode = 'US', string $tier = 'standard'): ?PricingRule
    {
        $cacheKey = "pricing_rule_{$serviceType}_{$countryCode}_{$tier}";

        return Cache::remember($cacheKey, 3600, function () use ($serviceType, $countryCode, $tier) {
            // Try exact match first
            $rule = PricingRule::active()
                ->forService($serviceType)
                ->forCountry($countryCode)
                ->forTier($tier)
                ->first();

            // Fall back to standard tier if premium/enterprise not found
            if (!$rule && $tier !== 'standard') {
                $rule = PricingRule::active()
                    ->forService($serviceType)
                    ->forCountry($countryCode)
                    ->forTier('standard')
                    ->first();
            }

            // Fall back to US pricing if country not found
            if (!$rule && $countryCode !== 'US') {
                $rule = PricingRule::active()
                    ->forService($serviceType)
                    ->forCountry('US')
                    ->forTier($tier)
                    ->first();
            }

            return $rule;
        });
    }

    /**
     * Calculate call cost with profit margin.
     *
     * @param int $durationSeconds
     * @param string $countryCode
     * @param string $tier
     * @return array ['base_cost', 'charge', 'profit', 'margin', 'rule_id']
     */
    public function calculateCallCost(int $durationSeconds, string $countryCode = 'US', string $tier = 'standard'): array
    {
        $rule = $this->getPricingRule('call', $countryCode, $tier);

        if (!$rule) {
            // Fallback to default pricing
            Log::warning("No pricing rule found for call in {$countryCode}, using default", [
                'country_code' => $countryCode,
                'tier' => $tier,
            ]);

            return $this->getDefaultCallCost($durationSeconds, $countryCode);
        }

        // Convert to minutes (round up)
        $durationMinutes = ceil($durationSeconds / 60);

        // Calculate base cost (what we pay Twilio)
        $baseCost = $durationMinutes * $rule->base_cost;

        // Calculate what we charge customer
        $profitData = $rule->calculateProfit($baseCost);

        return [
            'base_cost' => $baseCost,
            'charge' => $profitData['charge'],
            'profit' => $profitData['profit'],
            'margin' => $profitData['margin'],
            'rule_id' => $rule->id,
            'tier' => $tier,
            'duration_minutes' => $durationMinutes,
        ];
    }

    /**
     * Calculate SMS cost with profit margin.
     *
     * @param string $countryCode
     * @param int $segments
     * @param string $tier
     * @return array
     */
    public function calculateSmsCost(string $countryCode = 'US', int $segments = 1, string $tier = 'standard'): array
    {
        $rule = $this->getPricingRule('sms', $countryCode, $tier);

        if (!$rule) {
            Log::warning("No pricing rule found for SMS in {$countryCode}, using default", [
                'country_code' => $countryCode,
                'tier' => $tier,
            ]);

            return $this->getDefaultSmsCost($countryCode, $segments);
        }

        // Calculate base cost
        $baseCost = $segments * $rule->base_cost;

        // Calculate what we charge customer
        $profitData = $rule->calculateProfit($baseCost);

        return [
            'base_cost' => $baseCost,
            'charge' => $profitData['charge'],
            'profit' => $profitData['profit'],
            'margin' => $profitData['margin'],
            'rule_id' => $rule->id,
            'tier' => $tier,
            'segments' => $segments,
        ];
    }

    /**
     * Calculate phone number monthly cost with profit margin.
     *
     * @param string $countryCode
     * @param string $tier
     * @return array
     */
    public function calculatePhoneNumberCost(string $countryCode = 'US', string $tier = 'standard'): array
    {
        $rule = $this->getPricingRule('phone_number', $countryCode, $tier);

        if (!$rule) {
            Log::warning("No pricing rule found for phone number in {$countryCode}, using default", [
                'country_code' => $countryCode,
                'tier' => $tier,
            ]);

            return $this->getDefaultPhoneNumberCost($countryCode);
        }

        // Base cost is per month
        $baseCost = $rule->base_cost;

        // Calculate what we charge customer
        $profitData = $rule->calculateProfit($baseCost);

        return [
            'base_cost' => $baseCost,
            'charge' => $profitData['charge'],
            'profit' => $profitData['profit'],
            'margin' => $profitData['margin'],
            'rule_id' => $rule->id,
            'tier' => $tier,
        ];
    }

    /**
     * Calculate international call pricing with full breakdown.
     * Returns inbound (phone rental), outbound (calling), and SIP prices.
     *
     * @param string $fromCountry Origin country (phone number location)
     * @param string $toCountry Destination country (where calling to)
     * @param float $minutes Call duration in minutes
     * @param string $tier User's pricing tier
     * @return array Complete pricing breakdown
     */
    public function calculateInternationalCall(
        string $fromCountry,
        string $toCountry,
        float $minutes = 1.0,
        string $tier = 'standard'
    ): array {
        // Round minutes up (partial minutes count as full)
        $roundedMinutes = ceil($minutes);

        // Get country names
        $fromCountryName = $this->getCountryName($fromCountry);
        $toCountryName = $this->getCountryName($toCountry);

        // Calculate INBOUND price (phone number rental converted to per-minute)
        $inboundData = $this->calculateInboundPrice($fromCountry, $roundedMinutes, $tier);

        // Calculate OUTBOUND price (voice calling to destination)
        $outboundData = $this->calculateOutboundPrice($toCountry, $roundedMinutes, $tier);

        // Calculate SIP/VoIP price (same as outbound for now)
        $sipData = $this->calculateSipPrice($toCountry, $roundedMinutes, $tier);

        // Total cost (typically inbound is free, so mainly outbound + sip)
        // For estimation, we usually show just outbound cost as that's the variable cost
        $totalCost = $outboundData['customer_charge'];

        return [
            'from_country' => [
                'code' => $fromCountry,
                'name' => $fromCountryName,
            ],
            'to_country' => [
                'code' => $toCountry,
                'name' => $toCountryName,
            ],
            'minutes' => $minutes,
            'rounded_minutes' => $roundedMinutes,
            'tier' => $tier,
            'inbound' => $inboundData,
            'outbound' => $outboundData,
            'sip' => $sipData,
            'total_cost' => round($totalCost, 4),
        ];
    }

    /**
     * Calculate inbound call price (receiving calls).
     * Based on phone number monthly rental cost.
     */
    protected function calculateInboundPrice(string $countryCode, int $minutes, string $tier): array
    {
        $rule = $this->getPricingRule('phone_number', $countryCode, $tier);

        if (!$rule || $rule->base_cost == 0) {
            return [
                'base_cost' => 0.0000,
                'customer_charge' => 0.0000,
                'per_minute' => 0.0000,
                'profit' => 0.0000,
                'margin_percent' => 0.00,
                'available' => true,
                'note' => 'FREE - Included with phone number rental',
            ];
        }

        // Convert monthly cost to per-minute (assuming 30 days * 24 hours * 60 minutes = 43200 minutes/month)
        $monthlyCost = $rule->base_cost;
        $profitData = $rule->calculateProfit($monthlyCost);
        $perMinuteRate = $profitData['charge'] / 43200;

        // Cost for specified duration
        $totalCustomerCharge = $perMinuteRate * $minutes;
        $totalBaseCost = ($monthlyCost / 43200) * $minutes;
        $totalProfit = $totalCustomerCharge - $totalBaseCost;
        $marginPercent = $totalBaseCost > 0 ? ($totalProfit / $totalBaseCost) * 100 : 0;

        return [
            'base_cost' => round($totalBaseCost, 4),
            'customer_charge' => round($totalCustomerCharge, 4),
            'per_minute' => round($perMinuteRate, 4),
            'profit' => round($totalProfit, 4),
            'margin_percent' => round($marginPercent, 2),
            'available' => true,
            'rule_id' => $rule->id,
        ];
    }

    /**
     * Calculate outbound call price (making calls).
     */
    protected function calculateOutboundPrice(string $countryCode, int $minutes, string $tier): array
    {
        $rule = $this->getPricingRule('call', $countryCode, $tier);

        if (!$rule) {
            return [
                'base_cost' => 0.0000,
                'customer_charge' => 0.0000,
                'per_minute' => 0.0000,
                'profit' => 0.0000,
                'margin_percent' => 0.00,
                'available' => false,
                'note' => 'Pricing not available for this country',
            ];
        }

        // Calculate cost for duration
        $baseCost = $rule->base_cost * $minutes;
        $profitData = $rule->calculateProfit($baseCost);

        return [
            'base_cost' => round($baseCost, 4),
            'customer_charge' => round($profitData['charge'], 4),
            'per_minute' => round($rule->base_cost > 0 ? $profitData['charge'] / $minutes : 0, 4),
            'profit' => round($profitData['profit'], 4),
            'margin_percent' => round($profitData['margin'], 2),
            'available' => true,
            'rule_id' => $rule->id,
            'markup_type' => $rule->markup_type,
            'markup_value' => $rule->markup_value,
        ];
    }

    /**
     * Calculate SIP/VoIP call price (Browser/App SDK calling).
     * Currently same as outbound calling.
     */
    protected function calculateSipPrice(string $countryCode, int $minutes, string $tier): array
    {
        // For now, SIP pricing is same as outbound voice calling
        // This can be changed in future if SIP has different pricing
        $outboundData = $this->calculateOutboundPrice($countryCode, $minutes, $tier);
        
        // Add note to clarify this is for Browser/App calling
        $outboundData['note'] = 'Browser/App SDK calling rate';
        
        return $outboundData;
    }

    /**
     * Get country name from country code.
     */
    protected function getCountryName(string $countryCode): string
    {
        $cacheKey = "country_name_{$countryCode}";

        return Cache::remember($cacheKey, 86400, function () use ($countryCode) {
            $pricing = PricingRule::where('country_code', $countryCode)
                ->first();

            return $pricing ? $pricing->country_name : $countryCode;
        });
    }

    /**
     * Default call pricing (fallback when no rule exists).
     */
    protected function getDefaultCallCost(int $durationSeconds, string $countryCode): array
    {
        $durationMinutes = ceil($durationSeconds / 60);
        
        $defaultRates = [
            'US' => 0.0085,
            'CA' => 0.0085,
            'GB' => 0.0120,
            'AU' => 0.0200,
            'IN' => 0.0050,
        ];

        $baseCost = $durationMinutes * ($defaultRates[$countryCode] ?? 0.0200);
        $charge = $baseCost * 1.25; // 25% default markup
        $profit = $charge - $baseCost;
        $margin = ($profit / $baseCost) * 100;

        return [
            'base_cost' => round($baseCost, 4),
            'charge' => round($charge, 4),
            'profit' => round($profit, 4),
            'margin' => round($margin, 2),
            'rule_id' => null,
            'tier' => 'standard',
            'duration_minutes' => $durationMinutes,
        ];
    }

    /**
     * Default SMS pricing (fallback).
     */
    protected function getDefaultSmsCost(string $countryCode, int $segments): array
    {
        $defaultRates = [
            'US' => 0.0079,
            'CA' => 0.0079,
            'GB' => 0.0400,
            'AU' => 0.0500,
            'IN' => 0.0350,
        ];

        $baseCost = $segments * ($defaultRates[$countryCode] ?? 0.0500);
        $charge = $baseCost * 1.30; // 30% default markup
        $profit = $charge - $baseCost;
        $margin = ($profit / $baseCost) * 100;

        return [
            'base_cost' => round($baseCost, 4),
            'charge' => round($charge, 4),
            'profit' => round($profit, 4),
            'margin' => round($margin, 2),
            'rule_id' => null,
            'tier' => 'standard',
            'segments' => $segments,
        ];
    }

    /**
     * Default phone number pricing (fallback).
     */
    protected function getDefaultPhoneNumberCost(string $countryCode): array
    {
        $defaultCosts = [
            'US' => 1.15,
            'CA' => 1.00,
            'GB' => 1.50,
            'AU' => 2.00,
            'IN' => 3.00,
        ];

        $baseCost = $defaultCosts[$countryCode] ?? 2.00;
        $charge = $baseCost * 1.15; // 15% default markup
        $profit = $charge - $baseCost;
        $margin = ($profit / $baseCost) * 100;

        return [
            'base_cost' => round($baseCost, 4),
            'charge' => round($charge, 4),
            'profit' => round($profit, 4),
            'margin' => round($margin, 2),
            'rule_id' => null,
            'tier' => 'standard',
        ];
    }

    /**
     * Clear pricing cache.
     */
    public function clearCache(): void
    {
        Cache::flush(); // In production, use more specific cache tags
    }
}
