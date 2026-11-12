<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PricingRule;
use App\Services\PricingService;
use App\Services\TwilioPricingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PricingRuleController extends Controller
{
    public function __construct(
        protected PricingService $pricingService
    ) {}

    /**
     * Display listing of pricing rules.
     */
    public function index(Request $request): Response
    {
        $query = PricingRule::query();

        // Filter by tier
        if ($request->has('tier') && $request->tier !== 'all') {
            $query->where('tier', $request->tier);
        }

        // Filter by active status (apply to individual rules before grouping)
        $activeFilter = null;
        if ($request->has('active') && $request->active !== 'all') {
            $activeFilter = $request->active === 'active';
            $query->where('is_active', $activeFilter);
        }

        // Search by country
        if ($request->has('search') && $request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('country_name', 'like', '%' . $request->search . '%')
                  ->orWhere('country_code', 'like', '%' . $request->search . '%');
            });
        }

        // Get all matching rules
        $allRules = $query->orderBy('country_name')
            ->orderBy('service_type')
            ->get();

        // Group by country
        $groupedByCountry = $allRules->groupBy('country_code')->map(function ($countryRules) use ($request) {
            $first = $countryRules->first();
            $services = [];
            
            foreach ($countryRules as $rule) {
                $services[$rule->service_type] = [
                    'id' => $rule->id,
                    'base_cost' => $rule->base_cost,
                    'base_cost_unit' => $rule->base_cost_unit,
                    'markup_type' => $rule->markup_type,
                    'markup_value' => $rule->markup_value,
                    'markup_fixed' => $rule->markup_fixed,
                    'customer_charge' => $rule->customer_charge,
                    'minimum_charge' => $rule->minimum_charge,
                    'is_active' => $rule->is_active,
                ];
            }
            
            // Apply service type filter after grouping
            if ($request->has('service_type') && $request->service_type !== 'all') {
                $filterType = $request->service_type;
                if (!isset($services[$filterType])) {
                    return null; // Skip this country if it doesn't have the filtered service
                }
            }
            
            return [
                'country_code' => $first->country_code,
                'country_name' => $first->country_name,
                'tier' => $first->tier,
                'is_pinned' => $first->is_pinned,
                'services' => $services,
            ];
        })->filter()->values();

        // Sort pinned countries first, then by name
        $groupedByCountry = $groupedByCountry->sortBy([
            ['is_pinned', 'desc'],
            ['country_name', 'asc']
        ])->values(); // Remove null entries and reindex

        // Paginate the grouped results
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);
        // If per_page is 9999, show all results
        if ($perPage >= 9999) {
            $perPage = $groupedByCountry->count();
        }
        $total = $groupedByCountry->count();
        $paginatedData = $groupedByCountry->slice(($page - 1) * $perPage, $perPage)->values();

        $pricingRules = [
            'data' => $paginatedData,
            'current_page' => (int) $page,
            'last_page' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
            'per_page' => (int) $perPage,
            'total' => $total,
        ];

        // Get statistics
        $stats = [
            'total_rules' => PricingRule::count(),
            'active_rules' => PricingRule::active()->count(),
            'by_service' => [
                'call' => PricingRule::active()->where('service_type', 'call')->count(),
                'sms' => PricingRule::active()->where('service_type', 'sms')->count(),
                'phone_number' => PricingRule::active()->where('service_type', 'phone_number')->count(),
            ],
            'by_tier' => [
                'standard' => PricingRule::active()->where('tier', 'standard')->count(),
                'premium' => PricingRule::active()->where('tier', 'premium')->count(),
                'enterprise' => PricingRule::active()->where('tier', 'enterprise')->count(),
            ],
        ];

        return Inertia::render('Admin/Pricing/Index', [
            'pricingRules' => $pricingRules,
            'stats' => $stats,
            'filters' => $request->only(['service_type', 'tier', 'active', 'search']),
        ]);
    }

    /**
     * Show the form for creating a new pricing rule.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Pricing/Create');
    }

    /**
     * Store a newly created pricing rule.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_type' => 'required|in:call,sms,phone_number',
            'country_code' => 'required|string|size:2',
            'country_name' => 'required|string|max:255',
            'base_cost' => 'required|numeric|min:0',
            'base_cost_unit' => 'required|string|in:per_minute,per_sms,per_month',
            'markup_type' => 'required|in:percentage,fixed,hybrid',
            'markup_value' => 'required|numeric|min:0',
            'markup_fixed' => 'nullable|numeric|min:0',
            'minimum_charge' => 'required|numeric|min:0',
            'tier' => 'required|string|in:standard,premium,enterprise',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
            'auto_update_base_cost' => 'boolean',
        ]);

        PricingRule::create($validated);

        // Clear pricing cache
        $this->pricingService->clearCache();

        return redirect()->route('admin.pricing.index')
            ->with('success', 'Pricing rule created successfully.');
    }

    /**
     * Display the specified pricing rule.
     */
    public function show(PricingRule $pricingRule): Response
    {
        // Example calculations
        $exampleCosts = [
            'call' => [60, 180, 300], // 1 min, 3 min, 5 min
            'sms' => [1, 2, 3], // segments
            'phone_number' => [1], // monthly
        ];

        $examples = [];
        if (isset($exampleCosts[$pricingRule->service_type])) {
            foreach ($exampleCosts[$pricingRule->service_type] as $value) {
                $baseCost = $pricingRule->service_type === 'phone_number' 
                    ? $pricingRule->base_cost 
                    : $value * $pricingRule->base_cost;
                
                $profit = $pricingRule->calculateProfit($baseCost);
                $examples[] = [
                    'input' => $value,
                    'base_cost' => $baseCost,
                    'charge' => $profit['charge'],
                    'profit' => $profit['profit'],
                    'margin' => $profit['margin'],
                ];
            }
        }

        return Inertia::render('Admin/Pricing/Show', [
            'pricingRule' => $pricingRule,
            'examples' => $examples,
        ]);
    }

    /**
     * Show the form for editing the specified pricing rule.
     */
    public function edit(PricingRule $pricingRule): Response
    {
        // Get all pricing rules for this country (all service types)
        $allServicesForCountry = PricingRule::where('country_code', $pricingRule->country_code)
            ->get()
            ->keyBy('service_type');

        // Structure the data by service type
        $pricingData = [
            'country_code' => $pricingRule->country_code,
            'country_name' => $pricingRule->country_name,
            'tier' => $pricingRule->tier,
            'services' => [
                'call' => $allServicesForCountry->get('call'),
                'sms' => $allServicesForCountry->get('sms'),
                'phone_number' => $allServicesForCountry->get('phone_number'),
            ],
        ];

        return Inertia::render('Admin/Pricing/Edit', [
            'pricingData' => $pricingData,
        ]);
    }

    /**
     * Update the specified pricing rule.
     */
    public function update(Request $request, PricingRule $pricingRule)
    {
        $validated = $request->validate([
            'services' => 'required|array',
            'services.*.id' => 'required|exists:pricing_rules,id',
            'services.*.service_type' => 'required|in:call,sms,phone_number',
            'services.*.base_cost' => 'required|numeric|min:0',
            'services.*.base_cost_unit' => 'required|string|in:per_minute,per_sms,per_month',
            'services.*.markup_type' => 'required|in:percentage,fixed,hybrid',
            'services.*.markup_value' => 'required|numeric|min:0',
            'services.*.markup_fixed' => 'nullable|numeric|min:0',
            'services.*.minimum_charge' => 'required|numeric|min:0',
            'services.*.notes' => 'nullable|string',
            'services.*.is_active' => 'boolean',
            'services.*.auto_update_base_cost' => 'boolean',
        ]);

        $updatedCount = 0;

        foreach ($validated['services'] as $serviceData) {
            $rule = PricingRule::find($serviceData['id']);
            
            if (!$rule) {
                continue;
            }

            // Calculate customer_charge based on markup type
            $baseCost = (float) $serviceData['base_cost'];
            $markupValue = (float) $serviceData['markup_value'];
            $markupFixed = (float) ($serviceData['markup_fixed'] ?? 0);
            
            $customerCharge = match($serviceData['markup_type']) {
                'percentage' => $baseCost * (1 + ($markupValue / 100)),
                'fixed' => $baseCost + $markupValue,
                'hybrid' => ($baseCost * (1 + ($markupValue / 100))) + $markupFixed,
            };
            
            $serviceData['customer_charge'] = $customerCharge;

            $rule->update($serviceData);
            $updatedCount++;
        }

        // Clear pricing cache
        $this->pricingService->clearCache();

        return redirect()->route('admin.pricing.index')
            ->with('success', "{$updatedCount} pricing rule(s) updated successfully.");
    }

    /**
     * Remove the specified pricing rule.
     */
    public function destroy(PricingRule $pricingRule)
    {
        $pricingRule->delete();

        // Clear pricing cache
        $this->pricingService->clearCache();

        return redirect()->route('admin.pricing.index')
            ->with('success', 'Pricing rule deleted successfully.');
    }

    /**
     * Bulk delete pricing rules.
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:pricing_rules,id',
        ]);

        $deleted = PricingRule::whereIn('id', $request->ids)->delete();

        // Clear pricing cache
        $this->pricingService->clearCache();

        return back()->with('success', "{$deleted} pricing rule(s) deleted successfully.");
    }

    /**
     * Toggle active status of a pricing rule.
     */
    public function toggleActive(PricingRule $pricingRule)
    {
        $pricingRule->update(['is_active' => !$pricingRule->is_active]);

        // Clear pricing cache
        $this->pricingService->clearCache();

        return back()->with('success', 'Pricing rule status updated.');
    }

    /**
     * Bulk import pricing rules.
     */
    public function bulkImport(Request $request)
    {
        $request->validate([
            'rules' => 'required|array',
            'rules.*.service_type' => 'required|in:call,sms,phone_number',
            'rules.*.country_code' => 'required|string|size:2',
            'rules.*.country_name' => 'required|string',
            'rules.*.base_cost' => 'required|numeric|min:0',
            'rules.*.markup_value' => 'required|numeric|min:0',
        ]);

        $created = 0;
        foreach ($request->rules as $ruleData) {
            PricingRule::updateOrCreate(
                [
                    'service_type' => $ruleData['service_type'],
                    'country_code' => $ruleData['country_code'],
                    'tier' => $ruleData['tier'] ?? 'standard',
                ],
                $ruleData
            );
            $created++;
        }

        // Clear pricing cache
        $this->pricingService->clearCache();

        return back()->with('success', "{$created} pricing rules imported successfully.");
    }

    /**
     * Bulk update markup for all countries in a specific service type and tier.
     */
    public function bulkUpdateMarkup(Request $request)
    {
        $request->validate([
            'service_type' => 'required|in:call,sms,phone_number,all',
            'markup_type' => 'required|in:percentage,fixed,hybrid',
            'markup_value' => 'required|numeric|min:0',
            'markup_fixed' => 'nullable|numeric|min:0',
        ]);

        // Validate hybrid type has fixed amount
        if ($request->markup_type === 'hybrid' && !$request->has('markup_fixed')) {
            return back()->withErrors(['markup_fixed' => 'Fixed amount is required for hybrid markup type.']);
        }

        // Determine which service types to update
        $serviceTypes = $request->service_type === 'all' 
            ? ['call', 'sms', 'phone_number'] 
            : [$request->service_type];
        
        $totalUpdated = 0;
        
        foreach ($serviceTypes as $serviceType) {
            // Get all matching pricing rules (removed tier filter to update all tiers)
            $rules = PricingRule::where('service_type', $serviceType)
                ->get();
            
            // Update each rule with recalculated customer_charge
            foreach ($rules as $rule) {
                $baseCost = $rule->base_cost;
                
                // Calculate new customer charge based on markup type
                $customerCharge = match($request->markup_type) {
                    'percentage' => $baseCost * (1 + ($request->markup_value / 100)),
                    'fixed' => $baseCost + $request->markup_value,
                    'hybrid' => ($baseCost * (1 + ($request->markup_value / 100))) + ($request->markup_fixed ?? 0),
                };
                
                $rule->update([
                    'markup_type' => $request->markup_type,
                    'markup_value' => $request->markup_value,
                    'markup_fixed' => $request->markup_type === 'hybrid' ? $request->markup_fixed : null,
                    'customer_charge' => $customerCharge,
                    'minimum_charge' => $customerCharge,
                ]);
                
                $totalUpdated++;
            }
        }

        // Clear pricing cache
        $this->pricingService->clearCache();

        if ($request->service_type === 'all') {
            return back()->with('success', "Markup updated for {$totalUpdated} pricing rules across all services.");
        } else {
            return back()->with('success', "Markup updated for {$totalUpdated} pricing rules.");
        }
    }

    /**
     * Toggle pin status for all pricing rules of a country.
     */
    public function togglePin(Request $request)
    {
        $request->validate([
            'country_code' => 'required|string|size:2',
            'is_pinned' => 'required|boolean',
        ]);

        // Update all pricing rules for this country
        $updated = PricingRule::where('country_code', $request->country_code)
            ->update(['is_pinned' => $request->is_pinned]);

        $message = $request->is_pinned 
            ? "Country pinned successfully. It will appear at the top of the list."
            : "Country unpinned successfully.";

        return back()->with('success', $message);
    }
}
