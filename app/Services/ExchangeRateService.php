<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRateService
{
    private const PRIMARY_API = 'https://open.er-api.com/v6/latest/';
    private const FALLBACK_API = 'https://api.exchangerate-api.com/v4/latest/';
    private const CACHE_KEY_PREFIX = 'exchange_rates_';
    private const CACHE_TTL = 21600; // 6 hours in seconds
    private const MANUAL_RATES_FILE = 'manual_exchange_rates.json';

    /**
     * Currency symbols mapping.
     */
    public const CURRENCY_SYMBOLS = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'JPY' => '¥',
        'CNY' => '¥',
        'INR' => '₹',
        'NGN' => '₦',
        'GHS' => 'GH₵',
        'ZAR' => 'R',
        'KES' => 'KSh',
        'BRL' => 'R$',
        'AUD' => 'A$',
        'CAD' => 'C$',
        'CHF' => 'CHF',
        'SEK' => 'kr',
        'NOK' => 'kr',
        'DKK' => 'kr',
        'PLN' => 'zł',
        'CZK' => 'Kč',
        'HUF' => 'Ft',
        'RUB' => '₽',
        'TRY' => '₺',
        'MXN' => 'MX$',
        'SGD' => 'S$',
        'HKD' => 'HK$',
        'NZD' => 'NZ$',
        'KRW' => '₩',
        'THB' => '฿',
        'MYR' => 'RM',
        'PHP' => '₱',
        'IDR' => 'Rp',
        'AED' => 'د.إ',
        'SAR' => '﷼',
        'EGP' => 'E£',
        'UGX' => 'USh',
        'TZS' => 'TSh',
        'XOF' => 'CFA',
        'XAF' => 'FCFA',
        'RWF' => 'FRw',
    ];

    /**
     * Get the system base currency.
     */
    public function getBaseCurrency(): string
    {
        return config('services.base_currency', 'USD');
    }

    /**
     * Get the symbol for a currency code.
     */
    public function getCurrencySymbol(string $currencyCode): string
    {
        return self::CURRENCY_SYMBOLS[strtoupper($currencyCode)] ?? $currencyCode;
    }

    /**
     * Get the exchange rate mode (auto or manual).
     */
    public function getExchangeRateMode(): string
    {
        return config('services.exchange_rate_mode', 'auto');
    }

    /**
     * Get exchange rate from one currency to another.
     */
    public function getRate(string $from, string $to): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return 1.0;
        }

        // Check if manual mode is enabled
        if ($this->getExchangeRateMode() === 'manual') {
            $manualRate = $this->getManualRate($from, $to);
            if ($manualRate !== null) {
                return $manualRate;
            }
            // Fall back to API if manual rate not found for this pair
            Log::warning("Manual rate not found for {$from} to {$to}, falling back to API");
        }

        $rates = $this->getRates($from);

        if (!isset($rates[$to])) {
            throw new \Exception("Exchange rate not available for {$from} to {$to}");
        }

        return (float) $rates[$to];
    }

    /**
     * Convert an amount from one currency to another.
     *
     * @return array{converted_amount: float, rate: float, from: string, to: string}
     */
    public function convert(float $amount, string $from, string $to): array
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return [
                'converted_amount' => $amount,
                'rate' => 1.0,
                'from' => $from,
                'to' => $to,
            ];
        }

        $rate = $this->getRate($from, $to);
        $convertedAmount = round($amount * $rate, 2);

        return [
            'converted_amount' => $convertedAmount,
            'rate' => $rate,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Get all rates for a base currency (cached).
     */
    public function getRates(string $base = 'USD'): array
    {
        $base = strtoupper($base);
        $cacheKey = self::CACHE_KEY_PREFIX . $base;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($base) {
            return $this->fetchRates($base);
        });
    }

    /**
     * Get list of supported currencies.
     */
    public function getSupportedCurrencies(): array
    {
        try {
            $rates = $this->getRates('USD');
            return array_keys($rates);
        } catch (\Exception $e) {
            // Return a default list if API is unavailable
            return array_keys(self::CURRENCY_SYMBOLS);
        }
    }

    /**
     * Force refresh rates from API.
     */
    public function refreshRates(string $base = 'USD'): void
    {
        $base = strtoupper($base);
        $cacheKey = self::CACHE_KEY_PREFIX . $base;

        Cache::forget($cacheKey);

        try {
            $rates = $this->fetchRates($base);
            Cache::put($cacheKey, $rates, self::CACHE_TTL);
            Log::info('Exchange rates refreshed', ['base' => $base, 'count' => count($rates)]);
        } catch (\Exception $e) {
            Log::error('Failed to refresh exchange rates', ['base' => $base, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get a manual exchange rate for a currency pair.
     */
    public function getManualRate(string $from, string $to): ?float
    {
        $rates = $this->getManualRates();

        if (isset($rates[$from][$to])) {
            return (float) $rates[$from][$to];
        }

        // Try inverse rate
        if (isset($rates[$to][$from]) && (float) $rates[$to][$from] > 0) {
            return round(1.0 / (float) $rates[$to][$from], 6);
        }

        return null;
    }

    /**
     * Get all manual exchange rates.
     */
    public function getManualRates(): array
    {
        $path = storage_path('app/' . self::MANUAL_RATES_FILE);

        if (!File::exists($path)) {
            return [];
        }

        $content = File::get($path);
        $rates = json_decode($content, true);

        return is_array($rates) ? $rates : [];
    }

    /**
     * Save manual exchange rates.
     */
    public function saveManualRates(array $rates): void
    {
        $path = storage_path('app/' . self::MANUAL_RATES_FILE);
        File::put($path, json_encode($rates, JSON_PRETTY_PRINT));
    }

    /**
     * Fetch rates from API (primary with fallback).
     */
    private function fetchRates(string $base): array
    {
        // Try primary API
        try {
            $response = Http::timeout(10)->get(self::PRIMARY_API . $base);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['rates']) && !empty($data['rates'])) {
                    return $data['rates'];
                }
            }
        } catch (\Exception $e) {
            Log::warning('Primary exchange rate API failed', ['error' => $e->getMessage()]);
        }

        // Try fallback API
        try {
            $response = Http::timeout(10)->get(self::FALLBACK_API . $base);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['rates']) && !empty($data['rates'])) {
                    return $data['rates'];
                }
            }
        } catch (\Exception $e) {
            Log::error('Fallback exchange rate API also failed', ['error' => $e->getMessage()]);
        }

        throw new \Exception('Unable to fetch exchange rates from any provider');
    }
}
