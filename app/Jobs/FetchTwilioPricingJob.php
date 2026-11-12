<?php

namespace App\Jobs;

use App\Models\PricingRule;
use App\Services\PricingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class FetchTwilioPricingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes
    public $tries = 2;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $serviceTypes,
        public string $markupType,
        public float $markupValue,
        public ?float $markupFixed = null,
        public string $cacheKey = 'twilio_fetch_progress'
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $accountSid = config('services.twilio.account_sid');
        $authToken = config('services.twilio.auth_token');

        if (!$accountSid || !$authToken) {
            $this->updateProgress('error', 'Twilio credentials not configured');
            return;
        }

        $totalImported = 0;
        $totalUpdated = 0;
        $totalSkipped = 0;

        foreach ($this->serviceTypes as $serviceType) {
            $this->updateProgress('processing', "Fetching {$serviceType} pricing...", [
                'current_service' => $serviceType,
                'progress' => 0,
            ]);

            try {
                // Fetch countries list
                $countries = $this->fetchCountriesList($serviceType, $accountSid, $authToken);
                
                if (empty($countries)) {
                    $this->updateProgress('processing', "No countries found for {$serviceType}", [
                        'current_service' => $serviceType,
                    ]);
                    continue;
                }

                $total = count($countries);
                $processed = 0;

                // Process countries in chunks to avoid memory issues
                foreach (array_chunk($countries, 10) as $chunk) {
                    foreach ($chunk as $country) {
                        $result = $this->processCountry(
                            $serviceType,
                            $country,
                            $accountSid,
                            $authToken
                        );

                        if ($result === 'imported') {
                            $totalImported++;
                        } elseif ($result === 'updated') {
                            $totalUpdated++;
                        } elseif ($result === 'skipped') {
                            $totalSkipped++;
                        }

                        $processed++;
                        $progress = round(($processed / $total) * 100);

                        // Update progress every 5 countries
                        if ($processed % 5 === 0 || $processed === $total) {
                            $this->updateProgress('processing', "Processing {$serviceType}...", [
                                'current_service' => $serviceType,
                                'progress' => $progress,
                                'processed' => $processed,
                                'total' => $total,
                            ]);
                        }

                        // Small delay to avoid rate limiting
                        usleep(50000); // 50ms
                    }
                }
            } catch (\Exception $e) {
                Log::error("Error fetching {$serviceType} pricing", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                $this->updateProgress('error', "Failed to fetch {$serviceType}: " . $e->getMessage());
                continue;
            }
        }

        // Clear pricing cache
        app(PricingService::class)->clearCache();

        // Mark as completed
        $this->updateProgress('completed', 'Pricing sync completed', [
            'imported' => $totalImported,
            'updated' => $totalUpdated,
            'skipped' => $totalSkipped,
        ]);
    }

    /**
     * Fetch list of countries from Twilio
     */
    private function fetchCountriesList(string $serviceType, string $accountSid, string $authToken): array
    {
        $endpoints = [
            'call' => 'Voice',
            'sms' => 'Messaging',
            'phone_number' => 'PhoneNumbers',
        ];

        $endpoint = $endpoints[$serviceType] ?? 'Voice';
        $allCountries = [];
        $url = "https://pricing.twilio.com/v2/{$endpoint}/Countries";
        $pageSize = 50;

        do {
            $response = Http::withBasicAuth($accountSid, $authToken)
                ->timeout(30)
                ->get($url, ['PageSize' => $pageSize]);

            if (!$response->successful()) {
                Log::error("Twilio API failed for {$serviceType}", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                break;
            }

            $data = $response->json();
            $countries = $data['countries'] ?? [];
            $allCountries = array_merge($allCountries, $countries);

            $url = $data['meta']['next_page_url'] ?? null;
        } while ($url);

        return $allCountries;
    }

    /**
     * Process a single country
     */
    private function processCountry(
        string $serviceType,
        array $country,
        string $accountSid,
        string $authToken
    ): string {
        $endpoints = [
            'call' => 'Voice',
            'sms' => 'Messaging',
            'phone_number' => 'PhoneNumbers',
        ];

        $endpoint = $endpoints[$serviceType] ?? 'Voice';
        $detailUrl = "https://pricing.twilio.com/v2/{$endpoint}/Countries/{$country['iso_country']}";

        try {
            $detailResponse = Http::withBasicAuth($accountSid, $authToken)
                ->timeout(30)
                ->get($detailUrl);

            if (!$detailResponse->successful()) {
                return 'skipped';
            }

            $details = $detailResponse->json();
            $baseCost = $this->extractBaseCost($serviceType, $details);

            // Skip if base cost is 0
            if ($baseCost <= 0) {
                return 'skipped';
            }

            // Calculate customer charge
            $customerCharge = $this->calculateCustomerCharge(
                $baseCost,
                $this->markupType,
                $this->markupValue,
                $this->markupFixed
            );

            $baseCostUnit = match($serviceType) {
                'call' => 'per_minute',
                'sms' => 'per_sms',
                'phone_number' => 'per_month',
                default => 'per_minute',
            };

            // Check if exists
            $existingRule = PricingRule::where('service_type', $serviceType)
                ->where('country_code', $country['iso_country'])
                ->where('tier', 'standard')
                ->first();

            $ruleData = [
                'service_type' => $serviceType,
                'country_code' => $country['iso_country'],
                'country_name' => $country['country'],
                'base_cost' => $baseCost,
                'base_cost_unit' => $baseCostUnit,
                'markup_type' => $this->markupType,
                'markup_value' => $this->markupValue,
                'markup_fixed' => $this->markupType === 'hybrid' ? $this->markupFixed : null,
                'minimum_charge' => $customerCharge,
                'tier' => 'standard',
                'is_active' => true,
                'auto_update_base_cost' => true,
            ];

            if ($existingRule) {
                $existingRule->update($ruleData);
                return 'updated';
            } else {
                PricingRule::create($ruleData);
                return 'imported';
            }
        } catch (\Exception $e) {
            Log::error("Error processing country {$country['iso_country']}", [
                'error' => $e->getMessage()
            ]);
            return 'skipped';
        }
    }

    /**
     * Extract base cost from Twilio response
     */
    private function extractBaseCost(string $serviceType, array $details): float
    {
        return match($serviceType) {
            'call' => $this->extractVoicePrice($details),
            'sms' => $this->extractSmsPrice($details),
            'phone_number' => $this->extractPhoneNumberPrice($details),
            default => 0,
        };
    }

    private function extractVoicePrice(array $details): float
    {
        $outboundPrices = $details['outbound_prefix_prices'] ?? [];
        if (!empty($outboundPrices)) {
            return (float) ($outboundPrices[0]['base_price'] ?? 0);
        }
        return 0;
    }

    private function extractSmsPrice(array $details): float
    {
        $outboundPrices = $details['outbound_sms_prices'] ?? [];
        if (!empty($outboundPrices)) {
            $prices = array_values($outboundPrices)[0] ?? [];
            return (float) ($prices['base_price'] ?? 0);
        }
        return 0;
    }

    private function extractPhoneNumberPrice(array $details): float
    {
        $phoneNumberPrices = $details['phone_number_prices'] ?? [];
        foreach ($phoneNumberPrices as $price) {
            if (in_array($price['number_type'], ['local', 'mobile'])) {
                return (float) ($price['base_price'] ?? 0);
            }
        }
        return 0;
    }

    /**
     * Calculate customer charge based on markup
     */
    private function calculateCustomerCharge(
        float $baseCost,
        string $markupType,
        float $markupValue,
        ?float $markupFixed = null
    ): float {
        return match($markupType) {
            'percentage' => $baseCost * (1 + $markupValue / 100),
            'fixed' => $baseCost + $markupValue,
            'hybrid' => ($baseCost * (1 + $markupValue / 100)) + ($markupFixed ?? 0),
            default => $baseCost,
        };
    }

    /**
     * Update progress in cache
     */
    private function updateProgress(string $status, string $message, array $data = []): void
    {
        Cache::put($this->cacheKey, [
            'status' => $status,
            'message' => $message,
            'data' => $data,
            'updated_at' => now()->toIso8601String(),
        ], now()->addHours(1));
    }
}
