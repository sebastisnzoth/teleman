<?php

namespace App\Console\Commands;

use App\Models\PhoneNumber;
use App\Models\TwilioGlobalConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class UpdatePhoneNumberPricing extends Command
{
    protected $signature = 'phone-numbers:update-pricing 
                            {--force : Force update even if pricing was recently updated}
                            {--country= : Update pricing for specific country only}';

    protected $description = 'Update phone number pricing from Twilio API';

    public function handle()
    {
        $this->info('🔄 Updating phone number pricing from Twilio...');
        $this->newLine();

        // Get Twilio config
        $config = TwilioGlobalConfig::active();
        if (!$config) {
            $this->error('❌ Twilio not configured. Please configure Twilio in Settings.');
            return Command::FAILURE;
        }

        // Get all unique country codes from phone numbers
        $query = PhoneNumber::query();
        
        if ($countryCode = $this->option('country')) {
            $query->where('country_code', $countryCode);
            $this->info("Filtering by country: {$countryCode}");
        }

        $countryCodes = $query->pluck('country_code')
            ->filter()
            ->unique()
            ->values();

        if ($countryCodes->isEmpty()) {
            $this->warn('⚠️  No phone numbers found to update.');
            return Command::SUCCESS;
        }

        $this->info("Found " . $countryCodes->count() . " countries to update:");
        $this->table(['Country Code'], $countryCodes->map(fn($c) => [$c])->toArray());
        $this->newLine();

        $pricingCache = [];
        $updatedCount = 0;
        $errorCount = 0;

        // Fetch pricing for each country
        foreach ($countryCodes as $country) {
            $this->info("📊 Fetching pricing for {$country}...");
            
            try {
                // Fetch pricing from Twilio using HTTP
                $response = Http::withBasicAuth(
                    $config->account_sid,
                    $config->getDecryptedAuthToken()
                )->get("https://pricing.twilio.com/v1/PhoneNumbers/Countries/{$country}");

                if (!$response->successful()) {
                    throw new \Exception("API returned status {$response->status()}");
                }

                $data = $response->json();
                $phoneNumberPrices = $data['phone_number_prices'] ?? [];
                $monthlyPrice = null; // No default - must fetch from API
                
                if (!empty($phoneNumberPrices)) {
                    // Get the first available price (usually local numbers)
                    foreach ($phoneNumberPrices as $priceData) {
                        if (isset($priceData['base_price'])) {
                            $monthlyPrice = (float) $priceData['base_price'];
                            $priceUnit = $data['price_unit'] ?? 'USD';
                            break;
                        }
                    }
                    
                    $this->line("  → Found pricing: \${$monthlyPrice} {$priceUnit}/month");
                } else {
                    $this->warn("  → No pricing data available - price not set");
                }

                if ($monthlyPrice !== null) {
                    $pricingCache[$country] = $monthlyPrice;

                    // Update all numbers for this country
                    $updated = PhoneNumber::where('country_code', $country)
                        ->update(['monthly_cost' => $monthlyPrice]);

                    $updatedCount += $updated;
                    $this->info("  ✓ Updated {$updated} numbers for {$country}");
                } else {
                    $this->error("  ✗ Skipping {$country} - no pricing available");
                    $errorCount++;
                }


            } catch (\Exception $e) {
                $errorCount++;
                $this->error("  ✗ Failed to fetch pricing for {$country}: " . $e->getMessage());
                Log::error('Pricing update failed', [
                    'country' => $country,
                    'error' => $e->getMessage()
                ]);
            }

            $this->newLine();
        }

        // Summary
        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📈 UPDATE SUMMARY');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Countries Processed', count($countryCodes)],
                ['Numbers Updated', $updatedCount],
                ['Errors', $errorCount],
            ]
        );

        // Show current pricing by country
        $this->newLine();
        $this->info('💰 CURRENT PRICING BY COUNTRY:');
        $currentPricing = PhoneNumber::select('country_code')
            ->selectRaw('AVG(monthly_cost) as avg_price')
            ->selectRaw('COUNT(*) as number_count')
            ->groupBy('country_code')
            ->get();

        $this->table(
            ['Country', 'Avg Price', 'Numbers'],
            $currentPricing->map(fn($p) => [
                $p->country_code ?? 'Unknown',
                '$' . number_format($p->avg_price, 2),
                $p->number_count
            ])->toArray()
        );

        if ($errorCount > 0) {
            $this->newLine();
            $this->warn("⚠️  {$errorCount} countries had errors. Check logs for details.");
            return Command::FAILURE;
        }

        $this->newLine();
        $this->info('✅ Pricing update completed successfully!');
        return Command::SUCCESS;
    }
}
