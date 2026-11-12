<?php

namespace App\Services;

use App\Models\TwilioGlobalConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Twilio's pricing API, as the pricing command has always read it.
 *
 * The pricing command reaches it through TwilioPricingProvider, which
 * builds it through the container with the account PricingProviderFactory
 * resolved — so a buyer's own binding of this class is still the one used.
 * Built with no account at all, as a buyer's code resolving or injecting
 * it always could, it looks up the installation's account itself, exactly
 * as it always has.
 */
class TwilioPricingService
{
    protected $accountSid;

    protected $authToken;

    public function __construct(?string $accountSid = null, ?string $authToken = null)
    {
        if ($accountSid === null && $authToken === null) {
            [$accountSid, $authToken] = self::installationAccount();
        }

        $this->accountSid = $accountSid;
        $this->authToken = $authToken;

        // Validate credentials
        if (empty($this->accountSid) || empty($this->authToken)) {
            throw new \Exception('Twilio credentials not configured. Please set TWILIO_ACCOUNT_SID and TWILIO_AUTH_TOKEN in .env or configure in database.');
        }
    }

    /**
     * Where the pricing sync has always found the installation's Twilio
     * account: the first global configuration row — active or not — when
     * it holds both an account SID and a token, the .env/services account
     * otherwise. The one copy of that lookup; PricingProviderFactory uses
     * it too.
     *
     * @return array{0: mixed, 1: mixed} [account SID, auth token], either possibly empty
     */
    public static function installationAccount(): array
    {
        // Get credentials from database (TwilioGlobalConfig) or fallback to config
        $twilioConfig = TwilioGlobalConfig::first();

        if ($twilioConfig && $twilioConfig->account_sid && $twilioConfig->auth_token) {
            return [$twilioConfig->account_sid, $twilioConfig->auth_token];
        }

        // Fallback to config (which reads from .env)
        return [config('services.twilio.sid'), config('services.twilio.token')];
    }

    /**
     * Fetch voice call pricing for all countries from Twilio
     */
    public function fetchVoicePricing(?string $nextPageUrl = null): array
    {
        try {
            // Build the request URL - don't pass full next_page_url with all params
            if ($nextPageUrl) {
                // Use the next page URL directly as the full URL
                $fullUrl = $nextPageUrl;
            } else {
                // First page
                $fullUrl = 'https://pricing.twilio.com/v2/Voice/Countries?PageSize=50';
            }

            \Log::info('Requesting: '.$fullUrl);

            // Make request with full URL (no additional params)
            $response = Http::withBasicAuth($this->accountSid, $this->authToken)->get($fullUrl);

            if (! $response->successful()) {
                Log::error('Twilio Voice Pricing API failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return ['pricing' => [], 'next_page_url' => null];
            }

            $data = $response->json();
            $countries = $data['countries'] ?? [];

            // If no countries, we've reached the end
            if (empty($countries)) {
                \Log::info('No countries found - end of pagination');

                return ['pricing' => [], 'next_page_url' => null];
            }

            $nextPage = $data['meta']['next_page_url'] ?? null;

            \Log::info('Page fetched', [
                'countries' => count($countries),
                'next_page_url' => $nextPage,
                'current_url' => $fullUrl,
            ]);

            $pricing = [];

            // Process countries in concurrent batches
            $batchSize = 10; // 10 concurrent requests at a time
            for ($i = 0; $i < count($countries); $i += $batchSize) {
                $batch = array_slice($countries, $i, $batchSize);

                // Create concurrent requests for this batch
                $responses = Http::pool(fn ($pool) => array_map(
                    fn ($country) => $pool->withBasicAuth($this->accountSid, $this->authToken)
                        ->get("https://pricing.twilio.com/v2/Voice/Countries/{$country['iso_country']}"),
                    $batch
                ));

                // Process responses
                foreach ($batch as $index => $country) {
                    $detailResponse = $responses[$index];

                    // Skip if response is an exception (connection error, timeout, etc.)
                    if ($detailResponse instanceof \Exception) {
                        Log::warning('Failed to fetch pricing for country', [
                            'country' => $country['iso_country'],
                            'error' => $detailResponse->getMessage(),
                        ]);

                        continue;
                    }

                    if ($detailResponse->successful()) {
                        $details = $detailResponse->json();

                        // Get outbound call pricing
                        $outboundPrices = $details['outbound_prefix_prices'] ?? [];
                        $baseCost = 0;

                        if (! empty($outboundPrices)) {
                            // Get the first (typically default) outbound price
                            $baseCost = (float) ($outboundPrices[0]['base_price'] ?? 0);
                        }

                        $pricing[] = [
                            'country_code' => $country['iso_country'],
                            'country_name' => $country['country'],
                            'base_cost' => $baseCost,
                            'base_cost_unit' => 'per_minute',
                        ];
                    }
                }

                // Small delay between batches
                if ($i + $batchSize < count($countries)) {
                    usleep(100000); // 100ms delay between batches
                }
            }

            return [
                'pricing' => $pricing,
                'next_page_url' => $nextPage,
            ];
        } catch (\Exception $e) {
            Log::error('Error fetching Twilio Voice pricing', [
                'error' => $e->getMessage(),
            ]);

            return ['pricing' => [], 'next_page_url' => null];
        }
    }

    /**
     * Fetch SMS pricing for all countries from Twilio
     */
    public function fetchSmsPricing(?string $nextPageUrl = null): array
    {
        try {
            // Build the request URL with PageSize in the URL itself
            if ($nextPageUrl) {
                $fullUrl = $nextPageUrl;
            } else {
                $fullUrl = 'https://pricing.twilio.com/v1/Messaging/Countries?PageSize=50';
            }

            $response = Http::withBasicAuth($this->accountSid, $this->authToken)->get($fullUrl);

            if (! $response->successful()) {
                Log::error('Twilio SMS Pricing API failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return ['pricing' => [], 'next_page_url' => null];
            }

            $data = $response->json();
            $countries = $data['countries'] ?? [];

            // If no countries, we've reached the end
            if (empty($countries)) {
                return ['pricing' => [], 'next_page_url' => null];
            }

            $nextPage = $data['meta']['next_page_url'] ?? null;

            $pricing = [];

            // Process countries in concurrent batches
            $batchSize = 10; // 10 concurrent requests at a time
            for ($i = 0; $i < count($countries); $i += $batchSize) {
                $batch = array_slice($countries, $i, $batchSize);

                // Create concurrent requests for this batch
                $responses = Http::pool(fn ($pool) => array_map(
                    fn ($country) => $pool->withBasicAuth($this->accountSid, $this->authToken)
                        ->get("https://pricing.twilio.com/v1/Messaging/Countries/{$country['iso_country']}"),
                    $batch
                ));

                // Process responses
                foreach ($batch as $index => $country) {
                    $detailResponse = $responses[$index];

                    // Skip if response is an exception (connection error, timeout, etc.)
                    if ($detailResponse instanceof \Exception) {
                        Log::warning('Failed to fetch SMS pricing for country', [
                            'country' => $country['iso_country'],
                            'error' => $detailResponse->getMessage(),
                        ]);

                        continue;
                    }

                    if ($detailResponse->successful()) {
                        $details = $detailResponse->json();

                        // Get outbound SMS pricing
                        $outboundPrices = $details['outbound_sms_prices'] ?? [];
                        $baseCost = 0;

                        if (! empty($outboundPrices)) {
                            // outbound_sms_prices is an array of carriers, each with a prices array
                            $firstCarrier = array_values($outboundPrices)[0] ?? null;
                            if ($firstCarrier && isset($firstCarrier['prices']) && ! empty($firstCarrier['prices'])) {
                                $firstPrice = $firstCarrier['prices'][0] ?? null;
                                $baseCost = (float) ($firstPrice['base_price'] ?? 0);
                            }
                        }

                        $pricing[] = [
                            'country_code' => $country['iso_country'],
                            'country_name' => $country['country'],
                            'base_cost' => $baseCost,
                            'base_cost_unit' => 'per_sms',
                        ];
                    }
                }

                // Small delay between batches
                if ($i + $batchSize < count($countries)) {
                    usleep(100000); // 100ms delay between batches
                }
            }

            return [
                'pricing' => $pricing,
                'next_page_url' => $nextPage,
            ];
        } catch (\Exception $e) {
            Log::error('Error fetching Twilio SMS pricing', [
                'error' => $e->getMessage(),
            ]);

            return ['pricing' => [], 'next_page_url' => null];
        }
    }

    /**
     * Fetch phone number pricing for all countries from Twilio
     */
    public function fetchPhoneNumberPricing(?string $nextPageUrl = null): array
    {
        try {
            // Build the request URL with PageSize in the URL itself
            if ($nextPageUrl) {
                $fullUrl = $nextPageUrl;
            } else {
                $fullUrl = 'https://pricing.twilio.com/v1/PhoneNumbers/Countries?PageSize=50';
            }

            $response = Http::withBasicAuth($this->accountSid, $this->authToken)->get($fullUrl);

            if (! $response->successful()) {
                Log::error('Twilio Phone Number Pricing API failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return ['pricing' => [], 'next_page_url' => null];
            }

            $data = $response->json();
            $countries = $data['countries'] ?? [];

            // If no countries, we've reached the end
            if (empty($countries)) {
                return ['pricing' => [], 'next_page_url' => null];
            }

            $nextPage = $data['meta']['next_page_url'] ?? null;

            $pricing = [];

            // Process countries in concurrent batches
            $batchSize = 10; // 10 concurrent requests at a time
            for ($i = 0; $i < count($countries); $i += $batchSize) {
                $batch = array_slice($countries, $i, $batchSize);

                // Create concurrent requests for this batch
                $responses = Http::pool(fn ($pool) => array_map(
                    fn ($country) => $pool->withBasicAuth($this->accountSid, $this->authToken)
                        ->get("https://pricing.twilio.com/v1/PhoneNumbers/Countries/{$country['iso_country']}"),
                    $batch
                ));

                // Process responses
                foreach ($batch as $index => $country) {
                    $detailResponse = $responses[$index];

                    // Skip if response is an exception (connection error, timeout, etc.)
                    if ($detailResponse instanceof \Exception) {
                        Log::warning('Failed to fetch phone number pricing for country', [
                            'country' => $country['iso_country'],
                            'error' => $detailResponse->getMessage(),
                        ]);

                        continue;
                    }

                    if ($detailResponse->successful()) {
                        $details = $detailResponse->json();

                        // Get phone number pricing
                        $phoneNumberPrices = $details['phone_number_prices'] ?? [];
                        $baseCost = 0;

                        if (! empty($phoneNumberPrices)) {
                            // Get local or mobile number price
                            foreach ($phoneNumberPrices as $price) {
                                if (in_array($price['number_type'], ['local', 'mobile'])) {
                                    $baseCost = (float) ($price['base_price'] ?? 0);
                                    break;
                                }
                            }
                        }

                        $pricing[] = [
                            'country_code' => $country['iso_country'],
                            'country_name' => $country['country'],
                            'base_cost' => $baseCost,
                            'base_cost_unit' => 'per_month',
                        ];
                    }
                }

                // Small delay between batches
                if ($i + $batchSize < count($countries)) {
                    usleep(100000); // 100ms delay between batches
                }
            }

            return [
                'pricing' => $pricing,
                'next_page_url' => $nextPage,
            ];
        } catch (\Exception $e) {
            Log::error('Error fetching Twilio Phone Number pricing', [
                'error' => $e->getMessage(),
            ]);

            return ['pricing' => [], 'next_page_url' => null];
        }
    }

    /**
     * Fetch pricing based on service type
     */
    public function fetchPricing(string $serviceType, ?string $nextPageUrl = null): array
    {
        return match ($serviceType) {
            'call' => $this->fetchVoicePricing($nextPageUrl),
            'sms' => $this->fetchSmsPricing($nextPageUrl),
            'phone_number' => $this->fetchPhoneNumberPricing($nextPageUrl),
            default => ['pricing' => [], 'next_page_url' => null],
        };
    }
}
