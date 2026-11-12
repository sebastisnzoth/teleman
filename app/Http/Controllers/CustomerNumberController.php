<?php

namespace App\Http\Controllers;

use App\Models\AiAgent;
use App\Models\NumberRequest;
use App\Models\PhoneNumber;
use App\Models\PricingRule;
use App\Services\CreditService;
use App\Services\NumberRequestService;
use App\Services\PhoneNumberService;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class CustomerNumberController extends Controller
{
    protected $phoneNumberService;

    protected $numberRequestService;

    protected $creditService;

    protected $pricingService;

    public function __construct(
        PhoneNumberService $phoneNumberService,
        NumberRequestService $numberRequestService,
        CreditService $creditService,
        PricingService $pricingService
    ) {
        $this->phoneNumberService = $phoneNumberService;
        $this->numberRequestService = $numberRequestService;
        $this->creditService = $creditService;
        $this->pricingService = $pricingService;
    }

    /**
     * Get available countries with enabled geo-permissions
     */
    public function countries(Request $request)
    {
        $this->authorize('viewAny', PhoneNumber::class);

        try {
            $countries = $this->phoneNumberService->getEnabledCountries();

            return response()->json(['countries' => $countries]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch enabled countries: '.$e->getMessage());

            return response()->json(['error' => 'Failed to fetch countries'], 500);
        }
    }

    /**
     * Display available numbers for browsing
     */
    public function available(Request $request)
    {
        $this->authorize('viewAny', PhoneNumber::class);

        $numbers = $this->phoneNumberService->getAvailableNumbers(
            $request->country_code,
            $request->area_code,
            $request->has_voice ?? null,
            $request->has_sms ?? null
        );

        // Get enabled countries for the dropdown
        $countries = [];
        try {
            $countries = $this->phoneNumberService->getEnabledCountries();
        } catch (\Exception $e) {
            Log::warning('Failed to fetch enabled countries: '.$e->getMessage());
        }

        return Inertia::render('Customer/Numbers/Available', [
            'numbers' => $numbers->paginate($request->per_page ?? 24),
            'filters' => $request->only(['country_code', 'area_code', 'has_voice', 'has_sms']),
            'stats' => $this->phoneNumberService->getAvailableStats(),
            'countries' => $countries,
        ]);
    }

    /**
     * Display customer's assigned numbers
     */
    public function myNumbers(Request $request)
    {
        $this->authorize('viewAny', PhoneNumber::class);

        // Get user's numbers - MUST only return numbers with user_id AND status = 'assigned'
        $numbers = $this->phoneNumberService->getUserNumbers($request->user()->id)
            ->paginate($request->per_page ?? 15);

        // Get AI agents with their phone numbers
        $aiAgents = AiAgent::whereNotNull('phone_number')
            ->get()
            ->keyBy('phone_number');

        // Get calling prices for all countries
        $countryCodes = $numbers->pluck('country_code')->unique()->toArray();
        $callingPrices = PricingRule::whereIn('country_code', $countryCodes)
            ->where('service_type', 'call')
            ->where('is_active', true)
            ->get()
            ->keyBy('country_code');

        // Add AI agent info and calling price to each number
        $numbers->getCollection()->transform(function ($number) use ($aiAgents, $callingPrices) {
            $aiAgent = $aiAgents->get($number->number);
            $number->ai_agent = $aiAgent ? [
                'id' => $aiAgent->id,
                'name' => $aiAgent->name,
                'type' => $aiAgent->type,
                'active' => $aiAgent->active,
            ] : null;

            // Add calling price
            $pricingRule = $callingPrices->get($number->country_code);
            $number->calling_price = $pricingRule ? (float) $pricingRule->customer_charge : null;

            return $number;
        });

        return Inertia::render('Customer/Numbers/MyNumbers', [
            'numbers' => $numbers,
        ]);
    }

    /**
     * Display customer's number requests
     */
    public function myRequests(Request $request)
    {
        $this->authorize('viewAny', NumberRequest::class);

        $requests = $this->numberRequestService->getCustomerRequests($request->user())
            ->with(['phoneNumber'])
            ->latest('requested_at')
            ->paginate($request->per_page ?? 15);

        return Inertia::render('Customer/Numbers/MyRequests', [
            'requests' => $requests,
        ]);
    }

    /**
     * Get purchase price for a phone number (for confirmation dialog)
     */
    public function getPurchasePrice(PhoneNumber $phoneNumber, Request $request)
    {
        $this->authorize('request', $phoneNumber);

        $user = $request->user();
        $countryCode = $phoneNumber->country_code ?? 'US';
        $isSipTrunk = $phoneNumber->source === 'sip_trunk';

        // SIP trunk numbers are free
        if ($isSipTrunk) {
            return response()->json([
                'monthly_cost' => 0,
                'charge_amount' => 0,
                'user_balance' => round($user->credit_balance, 2),
                'can_afford' => true,
                'is_sip_trunk' => true,
                'phone_number' => $phoneNumber->formatted_number ?? $phoneNumber->number,
                'country_code' => $countryCode,
            ]);
        }

        // Check if phone number has valid pricing
        if (! $phoneNumber->monthly_cost || $phoneNumber->monthly_cost <= 0) {
            return response()->json([
                'monthly_cost' => 0,
                'charge_amount' => 0,
                'user_balance' => round($user->credit_balance, 2),
                'can_afford' => false,
                'is_sip_trunk' => false,
                'phone_number' => $phoneNumber->formatted_number ?? $phoneNumber->number,
                'country_code' => $countryCode,
                'error' => 'Pricing information not available. Contact administrator to sync pricing.',
            ], 400);
        }

        $pricing = $this->pricingService->calculatePhoneNumberCost($countryCode);
        $chargeAmount = $pricing['charge'] ?? $phoneNumber->monthly_cost;
        $monthlyCost = $pricing['base_cost'] ?? $phoneNumber->monthly_cost;

        return response()->json([
            'monthly_cost' => round($monthlyCost, 2),
            'charge_amount' => round($chargeAmount, 2),
            'user_balance' => round($user->credit_balance, 2),
            'can_afford' => $this->creditService->canAfford($user, $chargeAmount),
            'is_sip_trunk' => false,
            'phone_number' => $phoneNumber->formatted_number ?? $phoneNumber->number,
            'country_code' => $countryCode,
        ]);
    }

    /**
     * Purchase a phone number (instant purchase + assign)
     */
    public function request(PhoneNumber $phoneNumber, Request $request)
    {
        $this->authorize('request', $phoneNumber);

        $request->validate([
            'customer_notes' => 'nullable|string|max:500',
        ]);

        try {
            $numberRequest = $this->numberRequestService->createRequest(
                $phoneNumber,
                $request->user(),
                $request->customer_notes
            );

            return redirect()->route('numbers.my-numbers')
                ->with('success', sprintf(
                    'Phone number %s purchased and assigned successfully!',
                    $phoneNumber->formatted_number ?? $phoneNumber->number
                ));
        } catch (\Exception $e) {
            Log::error('Phone number purchase failed', [
                'number' => $phoneNumber->number,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            // Show user-friendly message, keep raw error in logs only
            $userMessage = 'Failed to purchase this phone number. Please try again or contact support.';
            if (str_contains($e->getMessage(), 'insufficient') || str_contains($e->getMessage(), 'Insufficient')) {
                $userMessage = 'Insufficient credits to purchase this phone number. Please top up your balance.';
            } elseif (str_contains($e->getMessage(), 'already')) {
                $userMessage = 'This phone number is no longer available. Please try another number.';
            }

            return redirect()->back()
                ->with('error', $userMessage);
        }
    }

    /**
     * Cancel a pending request
     */
    public function cancel(NumberRequest $numberRequest)
    {
        $this->authorize('cancel', $numberRequest);

        try {
            $this->numberRequestService->cancelRequest($numberRequest);

            return redirect()->back()
                ->with('success', 'Request cancelled successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to cancel request: '.$e->getMessage());
        }
    }

    /**
     * API endpoint to get user's assigned numbers (for softphone switcher)
     * EXCLUDES numbers assigned to AI Agents
     * INCLUDES both Twilio direct and SIP trunk numbers
     */
    public function myNumbersApi(Request $request)
    {
        $userId = $request->user()->id;

        // Get numbers assigned to AI agents (to exclude from softphone)
        $aiAgentNumbers = AiAgent::whereNotNull('phone_number')
            ->pluck('phone_number')
            ->toArray();

        // Get all phone numbers assigned to this user, excluding AI agent numbers
        $numbers = $this->phoneNumberService->getUserNumbers($userId)
            ->whereNotIn('number', $aiAgentNumbers)
            ->get()
            ->map(function ($number) {
                return [
                    'id' => $number->id,
                    'number' => $number->number,
                    'formatted_number' => $number->formatted_number,
                    'friendly_name' => $number->friendly_name,
                    'capabilities' => $number->capabilities,
                    'source' => $number->source ?? 'twilio_direct',
                ];
            });

        return response()->json([
            'numbers' => $numbers,
        ]);
    }

    /**
     * Get ALL available phone numbers for AI Agent assignment
     * Returns only numbers that are purchased from Twilio (have twilio_sid)
     * Includes AI agent information and supports filtering
     */
    public function allNumbersApi(Request $request)
    {
        $search = $request->query('search', '');
        $user = $request->user();

        // Get AI agents with their phone numbers for quick lookup.
        $aiAgents = AiAgent::whereNotNull('phone_number')
            ->when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id))
            ->get()
            ->keyBy('phone_number');

        // Every number this installation has bought from its provider, for an
        // admin. A customer may only give an agent a number they own, so they
        // are offered those alone — listing everyone's numbers, and which of
        // their agents answers each, let one customer take over another's line.
        $query = PhoneNumber::provisioned() // Must be purchased from the provider
            ->when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id));

        // Apply search filter if provided
        if ($search) {
            $query->where(function ($q) use ($search, $aiAgents) {
                // Search by phone number
                $q->where('number', 'like', '%'.$search.'%')
                    ->orWhere('friendly_name', 'like', '%'.$search.'%');

                // Search by AI agent name
                $agentNumbers = $aiAgents->filter(function ($agent) use ($search) {
                    return stripos($agent->name, $search) !== false;
                })->pluck('phone_number')->toArray();

                if (! empty($agentNumbers)) {
                    $q->orWhereIn('number', $agentNumbers);
                }
            });
        }

        $numbers = $query->orderBy('number')
            ->get()
            ->map(function ($number) use ($aiAgents) {
                $aiAgent = $aiAgents->get($number->number);

                return [
                    'id' => $number->id,
                    'number' => $number->number,
                    'formatted_number' => $number->formatted_number,
                    'friendly_name' => $number->friendly_name,
                    'capabilities' => $number->capabilities,
                    'status' => $number->status,
                    'is_assigned_to_user' => $number->user_id ? true : false,
                    'ai_agent' => $aiAgent ? [
                        'id' => $aiAgent->id,
                        'name' => $aiAgent->name,
                        'type' => $aiAgent->type,
                        'active' => $aiAgent->active,
                    ] : null,
                ];
            });

        return response()->json([
            'numbers' => $numbers,
        ]);
    }
}
