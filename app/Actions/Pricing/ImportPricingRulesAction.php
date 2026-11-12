<?php

declare(strict_types=1);

namespace App\Actions\Pricing;

use App\Models\PricingRule;

/**
 * Writes one service type's provider prices into the standard-tier
 * pricing rules, with the admin's markup applied.
 *
 * This is the write the pricing command has always made, moved here
 * unchanged so it is the same for every provider: one rule per country,
 * updated in place when a standard-tier rule for that country and service
 * already exists, created otherwise. It writes only the pricing columns —
 * notes, pinning, auto_update_base_cost and last_cost_update are never
 * touched, and nor is any other tier. It does not clear PricingService's
 * rule cache, as the command never did.
 *
 * An unknown markup type fails with UnhandledMatchError on the first row,
 * before anything is written, as it always has.
 */
final class ImportPricingRulesAction
{
    /**
     * @param  array<int, array{country_code: string, country_name: ?string, base_cost: int|float, base_cost_unit: string}>  $allPricing
     * @return array{created: int, updated: int}
     */
    public function execute(string $type, array $allPricing, ?string $markupType, float $markupValue, float $markupFixed): array
    {
        $created = 0;
        $updated = 0;

        foreach ($allPricing as $pricing) {
            $customerCharge = match ($markupType) {
                'percentage' => $pricing['base_cost'] * (1 + ($markupValue / 100)),
                'fixed' => $pricing['base_cost'] + $markupValue,
                'hybrid' => ($pricing['base_cost'] * (1 + ($markupValue / 100))) + $markupFixed,
            };

            $data = [
                'country_code' => $pricing['country_code'],
                'country_name' => $pricing['country_name'],
                'service_type' => $type,
                'base_cost' => $pricing['base_cost'],
                'base_cost_unit' => $pricing['base_cost_unit'],
                'markup_type' => $markupType,
                'markup_value' => $markupValue,
                'markup_fixed' => $markupType === 'hybrid' ? $markupFixed : null,
                'customer_charge' => $customerCharge,
                'minimum_charge' => $customerCharge,
                'tier' => 'standard',
                'is_active' => true,
            ];

            $existing = PricingRule::where('country_code', $pricing['country_code'])
                ->where('service_type', $type)
                ->where('tier', 'standard')
                ->first();

            if ($existing) {
                $existing->update($data);
                $updated++;
            } else {
                PricingRule::create($data);
                $created++;
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }
}
