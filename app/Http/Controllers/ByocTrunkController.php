<?php

namespace App\Http\Controllers;

use App\Models\ByocTrunk;
use App\Models\ConnectionPolicyTarget;
use App\Services\ByocAutoSetupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ByocTrunkController extends Controller
{
    public function __construct(
        private ByocAutoSetupService $setupService
    ) {}

    /**
     * Show BYOC setup wizard
     */
    public function setupWizard()
    {
        $user = Auth::user();

        return Inertia::render('TwilioIntegration/Byoc/SetupWizard', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'twilioConfigured' => !empty($user->twilio_account_sid) && !empty($user->twilio_auth_token),
            'twilioAccountSid' => $user->twilio_account_sid,
            'providerDefaults' => [
                'zadarma' => $this->setupService->getProviderDefaults('zadarma'),
                'voipms' => $this->setupService->getProviderDefaults('voipms'),
                'custom' => $this->setupService->getProviderDefaults('custom'),
            ],
        ]);
    }

    /**
     * Setup new BYOC trunk
     */
    public function setup(Request $request)
    {
        $validated = $request->validate([
            'friendly_name' => 'required|string|max:255',
            'provider_type' => 'required|in:zadarma,voipms,custom',
            'sip_uri' => 'required|string|starts_with:sip:',
            'sip_username' => 'nullable|string|max:255',
            'sip_password' => 'nullable|string|max:255',
            'cost_per_minute' => 'nullable|numeric|min:0|max:1',
            'currency' => 'nullable|string|size:3',
            'priority' => 'nullable|integer|min:0|max:65535',
            'weight' => 'nullable|integer|min:1|max:65535',
            'add_backup' => 'nullable|boolean',
        ]);

        try {
            $trunk = $this->setupService->setupByocTrunk(Auth::user(), $validated);

            return response()->json([
                'success' => true,
                'message' => 'BYOC trunk setup initiated successfully',
                'trunk' => [
                    'id' => $trunk->id,
                    'friendly_name' => $trunk->trunk_friendly_name,
                    'trunk_sid' => $trunk->trunk_sid,
                    'status' => $trunk->status,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('BYOC trunk setup failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get setup status
     */
    public function setupStatus(ByocTrunk $trunk)
    {
        $this->authorize('view', $trunk);

        return response()->json([
            'setup_progress' => $trunk->setup_progress,
            'setup_step' => $trunk->setup_step,
            'setup_error' => $trunk->setup_error,
            'is_setup_complete' => $trunk->is_setup_complete,
            'status' => $trunk->status,
        ]);
    }

    /**
     * List user's BYOC trunks
     */
    public function index()
    {
        $trunks = Auth::user()->byocTrunks()
            ->with(['connectionPolicy.targets'])
            ->latest()
            ->get()
            ->map(function ($trunk) {
                return [
                    'id' => $trunk->id,
                    'friendly_name' => $trunk->trunk_friendly_name,
                    'trunk_sid' => $trunk->trunk_sid,
                    'status' => $trunk->status,
                    'health_status' => $trunk->health_status,
                    'is_operational' => $trunk->is_operational,
                    'total_calls' => $trunk->total_calls,
                    'total_minutes' => $trunk->total_minutes,
                    'total_cost' => $trunk->total_cost,
                    'average_cost_per_minute' => $trunk->average_cost_per_minute,
                    'last_call_at' => $trunk->last_call_at?->toISOString(),
                    'targets_count' => $trunk->connectionPolicy?->targets->count() ?? 0,
                    'active_targets_count' => $trunk->connectionPolicy?->activeTargets()->count() ?? 0,
                    'created_at' => $trunk->created_at->toISOString(),
                ];
            });

        return Inertia::render('TwilioIntegration/Byoc/Index', [
            'trunks' => $trunks,
        ]);
    }

    /**
     * Show trunk details
     */
    public function show(ByocTrunk $trunk)
    {
        $this->authorize('view', $trunk);

        $trunk->load(['connectionPolicy.targets', 'healthLogs' => function ($query) {
            $query->latest('checked_at')->limit(20);
        }]);

        return Inertia::render('TwilioIntegration/Byoc/Show', [
            'trunk' => [
                'id' => $trunk->id,
                'friendly_name' => $trunk->trunk_friendly_name,
                'trunk_sid' => $trunk->trunk_sid,
                'status' => $trunk->status,
                'health_status' => $trunk->health_status,
                'is_operational' => $trunk->is_operational,
                'connection_policy_sid' => $trunk->connection_policy_sid,
                'total_calls' => $trunk->total_calls,
                'total_minutes' => $trunk->total_minutes,
                'total_cost' => $trunk->total_cost,
                'average_cost_per_minute' => $trunk->average_cost_per_minute,
                'last_call_at' => $trunk->last_call_at?->toISOString(),
                'created_at' => $trunk->created_at->toISOString(),
                'targets' => $trunk->connectionPolicy?->targets->map(function ($target) {
                    return [
                        'id' => $target->id,
                        'friendly_name' => $target->friendly_name,
                        'provider_type' => $target->provider_type,
                        'provider_display_name' => $target->provider_display_name,
                        'sip_uri' => $target->sip_uri,
                        'priority' => $target->priority,
                        'weight' => $target->weight,
                        'enabled' => $target->enabled,
                        'total_calls' => $target->total_calls,
                        'successful_calls' => $target->successful_calls,
                        'failed_calls' => $target->failed_calls,
                        'success_rate' => $target->success_rate,
                        'cost_per_minute' => $target->cost_per_minute,
                        'currency' => $target->currency,
                        'last_call_at' => $target->last_call_at?->toISOString(),
                    ];
                }) ?? [],
                'health_logs' => $trunk->healthLogs->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'status' => $log->status,
                        'check_type' => $log->check_type,
                        'message' => $log->message,
                        'response_time_ms' => $log->response_time_ms,
                        'error_message' => $log->error_message,
                        'checked_at' => $log->checked_at->toISOString(),
                    ];
                }),
            ],
        ]);
    }

    /**
     * Add SIP provider to trunk
     */
    public function addProvider(Request $request, ByocTrunk $trunk)
    {
        $this->authorize('update', $trunk);

        $validated = $request->validate([
            'friendly_name' => 'required|string|max:255',
            'provider_type' => 'required|in:zadarma,voipms,custom',
            'sip_uri' => 'required|string|starts_with:sip:',
            'sip_username' => 'nullable|string|max:255',
            'sip_password' => 'nullable|string|max:255',
            'cost_per_minute' => 'nullable|numeric|min:0|max:1',
            'priority' => 'nullable|integer|min:0|max:65535',
            'weight' => 'nullable|integer|min:1|max:65535',
        ]);

        try {
            $target = $this->setupService->addSipProvider($trunk, $validated);

            return response()->json([
                'success' => true,
                'message' => 'SIP provider added successfully',
                'target' => [
                    'id' => $target->id,
                    'friendly_name' => $target->friendly_name,
                    'provider_type' => $target->provider_type,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update SIP provider
     */
    public function updateProvider(Request $request, ConnectionPolicyTarget $target)
    {
        $this->authorize('update', $target->connectionPolicy->byocTrunk);

        $validated = $request->validate([
            'friendly_name' => 'sometimes|string|max:255',
            'sip_uri' => 'sometimes|string|starts_with:sip:',
            'sip_username' => 'nullable|string|max:255',
            'sip_password' => 'nullable|string|max:255',
            'cost_per_minute' => 'nullable|numeric|min:0|max:1',
            'priority' => 'sometimes|integer|min:0|max:65535',
            'weight' => 'sometimes|integer|min:1|max:65535',
            'enabled' => 'sometimes|boolean',
        ]);

        try {
            $target = $this->setupService->updateSipProvider($target, $validated);

            return response()->json([
                'success' => true,
                'message' => 'SIP provider updated successfully',
                'target' => [
                    'id' => $target->id,
                    'friendly_name' => $target->friendly_name,
                    'enabled' => $target->enabled,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove SIP provider
     */
    public function removeProvider(ConnectionPolicyTarget $target)
    {
        $this->authorize('update', $target->connectionPolicy->byocTrunk);

        try {
            $this->setupService->removeSipProvider($target);

            return response()->json([
                'success' => true,
                'message' => 'SIP provider removed successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete BYOC trunk
     */
    public function destroy(ByocTrunk $trunk)
    {
        $this->authorize('delete', $trunk);

        try {
            $trunk->delete();

            return response()->json([
                'success' => true,
                'message' => 'BYOC trunk deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle incoming voice webhook (for inbound calls)
     */
    public function voiceWebhook(Request $request)
    {
        // Handle inbound calls routed through BYOC trunk
        // Similar to SIP trunk voice webhook
        
        Log::info('BYOC trunk inbound call', [
            'from' => $request->input('From'),
            'to' => $request->input('To'),
            'call_sid' => $request->input('CallSid'),
        ]);

        // Return TwiML response
        $twiml = new \Twilio\TwiML\VoiceResponse();
        $twiml->say('Thank you for calling. This is a BYOC trunk inbound call.');

        return response($twiml)->header('Content-Type', 'text/xml');
    }
}
