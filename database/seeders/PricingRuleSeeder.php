<?php

namespace Database\Seeders;

use App\Models\PricingRule;
use Illuminate\Database\Seeder;

class PricingRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pricingRules = [
            // Call pricing for major countries
            [
                'service_type' => 'call',
                'country_code' => 'US',
                'country_name' => 'United States',
                'base_cost' => 0.0085,
                'base_cost_unit' => 'per_minute',
                'markup_type' => 'percentage',
                'markup_value' => 25.00, // 25% markup
                'minimum_charge' => 0.0100,
                'tier' => 'standard',
                'notes' => 'Standard US call pricing with 25% profit margin',
                'is_active' => true,
            ],
            [
                'service_type' => 'call',
                'country_code' => 'CA',
                'country_name' => 'Canada',
                'base_cost' => 0.0085,
                'base_cost_unit' => 'per_minute',
                'markup_type' => 'percentage',
                'markup_value' => 25.00,
                'minimum_charge' => 0.0100,
                'tier' => 'standard',
                'notes' => 'Canada call pricing',
                'is_active' => true,
            ],
            [
                'service_type' => 'call',
                'country_code' => 'GB',
                'country_name' => 'United Kingdom',
                'base_cost' => 0.0120,
                'base_cost_unit' => 'per_minute',
                'markup_type' => 'percentage',
                'markup_value' => 30.00,
                'minimum_charge' => 0.0150,
                'tier' => 'standard',
                'notes' => 'UK call pricing with 30% margin',
                'is_active' => true,
            ],
            [
                'service_type' => 'call',
                'country_code' => 'AU',
                'country_name' => 'Australia',
                'base_cost' => 0.0200,
                'base_cost_unit' => 'per_minute',
                'markup_type' => 'percentage',
                'markup_value' => 28.00,
                'minimum_charge' => 0.0250,
                'tier' => 'standard',
                'notes' => 'Australia call pricing',
                'is_active' => true,
            ],
            [
                'service_type' => 'call',
                'country_code' => 'IN',
                'country_name' => 'India',
                'base_cost' => 0.0050,
                'base_cost_unit' => 'per_minute',
                'markup_type' => 'percentage',
                'markup_value' => 35.00,
                'minimum_charge' => 0.0070,
                'tier' => 'standard',
                'notes' => 'India call pricing with higher margin',
                'is_active' => true,
            ],

            // SMS pricing for major countries
            [
                'service_type' => 'sms',
                'country_code' => 'US',
                'country_name' => 'United States',
                'base_cost' => 0.0079,
                'base_cost_unit' => 'per_sms',
                'markup_type' => 'percentage',
                'markup_value' => 30.00,
                'minimum_charge' => 0.0100,
                'tier' => 'standard',
                'notes' => 'US SMS pricing with 30% margin',
                'is_active' => true,
            ],
            [
                'service_type' => 'sms',
                'country_code' => 'CA',
                'country_name' => 'Canada',
                'base_cost' => 0.0079,
                'base_cost_unit' => 'per_sms',
                'markup_type' => 'percentage',
                'markup_value' => 30.00,
                'minimum_charge' => 0.0100,
                'tier' => 'standard',
                'notes' => 'Canada SMS pricing',
                'is_active' => true,
            ],
            [
                'service_type' => 'sms',
                'country_code' => 'GB',
                'country_name' => 'United Kingdom',
                'base_cost' => 0.0400,
                'base_cost_unit' => 'per_sms',
                'markup_type' => 'percentage',
                'markup_value' => 25.00,
                'minimum_charge' => 0.0500,
                'tier' => 'standard',
                'notes' => 'UK SMS pricing',
                'is_active' => true,
            ],
            [
                'service_type' => 'sms',
                'country_code' => 'AU',
                'country_name' => 'Australia',
                'base_cost' => 0.0500,
                'base_cost_unit' => 'per_sms',
                'markup_type' => 'percentage',
                'markup_value' => 28.00,
                'minimum_charge' => 0.0640,
                'tier' => 'standard',
                'notes' => 'Australia SMS pricing',
                'is_active' => true,
            ],
            [
                'service_type' => 'sms',
                'country_code' => 'IN',
                'country_name' => 'India',
                'base_cost' => 0.0350,
                'base_cost_unit' => 'per_sms',
                'markup_type' => 'percentage',
                'markup_value' => 35.00,
                'minimum_charge' => 0.0470,
                'tier' => 'standard',
                'notes' => 'India SMS pricing',
                'is_active' => true,
            ],

            // Phone number monthly costs
            [
                'service_type' => 'phone_number',
                'country_code' => 'US',
                'country_name' => 'United States',
                'base_cost' => 1.15,
                'base_cost_unit' => 'per_month',
                'markup_type' => 'percentage',
                'markup_value' => 15.00,
                'minimum_charge' => 1.32,
                'tier' => 'standard',
                'notes' => 'US phone number monthly rental',
                'is_active' => true,
            ],
            [
                'service_type' => 'phone_number',
                'country_code' => 'CA',
                'country_name' => 'Canada',
                'base_cost' => 1.00,
                'base_cost_unit' => 'per_month',
                'markup_type' => 'percentage',
                'markup_value' => 15.00,
                'minimum_charge' => 1.15,
                'tier' => 'standard',
                'notes' => 'Canada phone number monthly rental',
                'is_active' => true,
            ],
            [
                'service_type' => 'phone_number',
                'country_code' => 'GB',
                'country_name' => 'United Kingdom',
                'base_cost' => 1.50,
                'base_cost_unit' => 'per_month',
                'markup_type' => 'percentage',
                'markup_value' => 20.00,
                'minimum_charge' => 1.80,
                'tier' => 'standard',
                'notes' => 'UK phone number monthly rental',
                'is_active' => true,
            ],
            [
                'service_type' => 'phone_number',
                'country_code' => 'AU',
                'country_name' => 'Australia',
                'base_cost' => 2.00,
                'base_cost_unit' => 'per_month',
                'markup_type' => 'percentage',
                'markup_value' => 18.00,
                'minimum_charge' => 2.36,
                'tier' => 'standard',
                'notes' => 'Australia phone number monthly rental',
                'is_active' => true,
            ],
            [
                'service_type' => 'phone_number',
                'country_code' => 'IN',
                'country_name' => 'India',
                'base_cost' => 3.00,
                'base_cost_unit' => 'per_month',
                'markup_type' => 'percentage',
                'markup_value' => 20.00,
                'minimum_charge' => 3.60,
                'tier' => 'standard',
                'notes' => 'India phone number monthly rental',
                'is_active' => true,
            ],
        ];

        foreach ($pricingRules as $rule) {
            PricingRule::updateOrCreate(
                [
                    'service_type' => $rule['service_type'],
                    'country_code' => $rule['country_code'],
                    'tier' => $rule['tier'],
                ],
                $rule
            );
        }

        $this->command->info('Pricing rules seeded successfully!');
    }
}
