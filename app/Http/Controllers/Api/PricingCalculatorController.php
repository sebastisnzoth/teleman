<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;

class PricingCalculatorController extends Controller
{
    protected $pricingService;

    public function __construct(PricingService $pricingService)
    {
        $this->pricingService = $pricingService;
    }

    /**
     * Calculate international calling prices for customers
     * Shows only customer-facing prices (hides profit margins)
     */
    public function calculate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_country' => 'required|string|size:2',
            'to_country' => 'required|string|size:2',
            'minutes' => 'nullable|numeric|min:0.1|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Invalid input',
                'messages' => $validator->errors()
            ], 422);
        }

        $fromCountry = strtoupper($request->from_country);
        $toCountry = strtoupper($request->to_country);
        $minutes = $request->minutes ?? 1;

        // Get user's tier
        $tier = auth()->user()->tier ?? 'standard';

        // Calculate pricing
        $pricing = $this->pricingService->calculateInternationalCall(
            $fromCountry,
            $toCountry,
            $minutes,
            $tier
        );

        // Filter response for customer view (remove profit data)
        return response()->json([
            'from_country' => $pricing['from_country'],
            'to_country' => $pricing['to_country'],
            'minutes' => $pricing['minutes'],
            'tier' => $tier,
            'inbound' => [
                'customer_charge' => $pricing['inbound']['customer_charge'],
                'per_minute' => $pricing['inbound']['per_minute'],
                'available' => $pricing['inbound']['available'],
                'note' => $pricing['inbound']['note'] ?? null,
            ],
            'outbound' => [
                'customer_charge' => $pricing['outbound']['customer_charge'],
                'per_minute' => $pricing['outbound']['per_minute'],
                'available' => $pricing['outbound']['available'],
                'note' => $pricing['outbound']['note'] ?? null,
            ],
            'sip' => [
                'customer_charge' => $pricing['sip']['customer_charge'],
                'per_minute' => $pricing['sip']['per_minute'],
                'available' => $pricing['sip']['available'],
                'note' => $pricing['sip']['note'] ?? null,
            ],
            'total_cost' => $pricing['total_cost'],
            'rounded_minutes' => $pricing['rounded_minutes'],
        ]);
    }

    /**
     * Calculate international calling prices for admins
     * Shows full cost breakdown including profit margins
     */
    public function calculateAdmin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_country' => 'required|string|size:2',
            'to_country' => 'required|string|size:2',
            'minutes' => 'nullable|numeric|min:0.1|max:1000',
            'tier' => 'nullable|string|in:standard,premium,enterprise',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Invalid input',
                'messages' => $validator->errors()
            ], 422);
        }

        $fromCountry = strtoupper($request->from_country);
        $toCountry = strtoupper($request->to_country);
        $minutes = $request->minutes ?? 1;
        $tier = $request->tier ?? 'standard';

        // Calculate pricing with full details
        $pricing = $this->pricingService->calculateInternationalCall(
            $fromCountry,
            $toCountry,
            $minutes,
            $tier
        );

        // Return full pricing details for admin
        return response()->json($pricing);
    }

    /**
     * Get list of available countries for calculator
     */
    public function getCountries(Request $request)
    {
        $cacheKey = 'pricing_calculator_countries';
        
        $countries = Cache::remember($cacheKey, 3600, function () {
            return \App\Models\PricingRule::where('is_active', true)
                ->select('country_code', 'country_name')
                ->distinct()
                ->orderBy('country_name')
                ->get()
                ->map(function ($country) {
                    return [
                        'code' => $country->country_code,
                        'name' => $country->country_name,
                    ];
                });
        });

        return response()->json([
            'countries' => $countries,
            'count' => $countries->count(),
        ]);
    }
}
