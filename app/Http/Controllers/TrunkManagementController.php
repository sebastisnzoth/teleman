<?php

namespace App\Http\Controllers;

use App\Models\UserSipTrunk;
use App\Models\TrunkPhoneNumber;
use App\Models\User;
use App\Models\Campaign;
use App\Models\AiAgent;
use App\Models\TwilioGlobalConfig;
use App\Jobs\SetupSipTrunkJob;
use App\Services\TrunkAutoSetupService;
use App\Services\TrunkCallService;
use App\Services\TrunkHealthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TrunkManagementController extends Controller
{
    protected TrunkAutoSetupService $autoSetupService;
    protected TrunkCallService $callService;
    protected TrunkHealthService $healthService;

    public function __construct(
        TrunkAutoSetupService $autoSetupService,
        TrunkCallService $callService,
        TrunkHealthService $healthService
    ) {
        $this->autoSetupService = $autoSetupService;
        $this->callService = $callService;
        $this->healthService = $healthService;
    }

    /**
     * Display list of user's SIP trunks
     */
    public function index()
    {
        $user = Auth::user();
        
        $trunks = $user->sipTrunks()
            ->with(['phoneNumbers'])
            ->withCount('phoneNumbers')
            ->latest()
            ->get()
            ->map(function ($trunk) {
                return [
                    'id' => $trunk->id,
                    'friendly_name' => $trunk->trunk_friendly_name ?? 'SIP Trunk',
                    'status' => $trunk->status ?? 'unknown',
                    'health_status' => $trunk->health_status ?? 'unknown',
                    'trunk_domain_name' => $trunk->trunk_domain_name ?? 'N/A',
                    'phone_numbers_count' => $trunk->phone_numbers_count ?? 0,
                    'total_calls_count' => $trunk->total_calls_count ?? 0,
                    'total_minutes_used' => number_format((float)($trunk->total_minutes_used ?? 0), 2),
                    'last_call_at' => $trunk->last_call_at?->toISOString(),
                    'last_health_check_at' => $trunk->last_health_check_at?->toISOString(),
                    'created_at' => $trunk->created_at->toISOString(),
                    'is_operational' => $trunk->isOperational(),
                    'is_setup_complete' => $trunk->isSetupComplete(),
                    'setup_progress' => $trunk->setup_progress ?? 0,
                ];
            });

        // User can add a new trunk if they have no active/setting_up trunks
        $hasActiveTrunk = $trunks->filter(function ($trunk) {
            return in_array($trunk['status'], ['active', 'setting_up']);
        })->isNotEmpty();

        return Inertia::render('TwilioIntegration/SipTrunk/Index', [
            'trunks' => $trunks,
            'hasTrunk' => $trunks->isNotEmpty(),
            'hasActiveTrunk' => $hasActiveTrunk,
        ]);
    }

    /**
     * Show setup wizard
     */
    public function create()
    {
        $user = Auth::user();

        // Check if user already has an active trunk
        if ($user->hasSipTrunk()) {
            return redirect()->route('sip-trunk.index')
                ->with('warning', 'You already have an active SIP trunk. Please disconnect it first if you want to connect a new one.');
        }

        // Check if global Twilio config exists in database
        $twilioConfig = TwilioGlobalConfig::active();
        
        // More strict validation: check if config exists AND has valid credentials
        $twilioConfigured = false;
        $twilioAccountSid = null;
        
        if ($twilioConfig) {
            $accountSid = trim($twilioConfig->account_sid ?? '');
            $authToken = trim($twilioConfig->auth_token ?? '');
            
            // Validate Account SID format (starts with AC and is 34 characters)
            $validAccountSid = !empty($accountSid) && 
                              str_starts_with($accountSid, 'AC') && 
                              strlen($accountSid) === 34;
            
            // Validate Auth Token (should be 32 characters minimum)
            $validAuthToken = !empty($authToken) && strlen($authToken) >= 32;
            
            $twilioConfigured = $validAccountSid && $validAuthToken;
            $twilioAccountSid = $twilioConfigured ? substr($accountSid, 0, 8) . '...' : null;
        }

        return Inertia::render('TwilioIntegration/SipTrunk/SetupWizard', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'twilioConfigured' => $twilioConfigured,
            'twilioAccountSid' => $twilioAccountSid,
        ]);
    }

    /**
     * Store - create trunk record and dispatch async setup job
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'friendly_name' => 'nullable|string|max:100',

            // Local PBX routing (optional)
            'routes_to_local_pbx' => 'boolean',
            'local_pbx_ip' => 'required_if:routes_to_local_pbx,true|nullable|ip',
            'local_pbx_port' => 'nullable|integer|min:1|max:65535',
            'local_pbx_transport' => 'nullable|in:udp,tcp,tls',
            'local_pbx_username' => 'nullable|string|max:100',
            'local_pbx_password' => 'nullable|string|max:255',
        ]);

        // Get global Twilio config from database
        $twilioConfig = TwilioGlobalConfig::active();

        if (!$twilioConfig) {
            return response()->json([
                'success' => false,
                'message' => 'Twilio configuration is not set up. Please configure your Twilio credentials first at /settings/twilio',
            ], 400);
        }

        $twilioAccountSid = trim($twilioConfig->account_sid ?? '');
        $twilioAuthToken = trim($twilioConfig->auth_token ?? '');

        // Validate credentials format
        if (empty($twilioAccountSid) || !str_starts_with($twilioAccountSid, 'AC') || strlen($twilioAccountSid) !== 34) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Twilio Account SID. Please reconfigure your Twilio credentials at /settings/twilio',
            ], 400);
        }

        if (empty($twilioAuthToken) || strlen($twilioAuthToken) < 32) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Twilio Auth Token. Please reconfigure your Twilio credentials at /settings/twilio',
            ], 400);
        }

        $twilioAuthToken = $twilioConfig->getDecryptedAuthToken();

        $user = Auth::user();

        // Check if user already has an active or in-progress trunk
        if ($user->hasSipTrunk()) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an active or in-progress SIP trunk. Please disconnect it first.',
            ], 400);
        }

        // Generate webhook token if not exists
        if (!$user->webhook_token) {
            $user->webhook_token = Str::random(40);
            $user->save();
        }

        $friendlyName = $validated['friendly_name'] ?? "{$user->name}'s SIP Trunk";

        // Build local PBX SIP URI if provided
        $localPbxSipUri = null;
        if (!empty($validated['routes_to_local_pbx']) && !empty($validated['local_pbx_ip'])) {
            $port = $validated['local_pbx_port'] ?? 5060;
            $transport = $validated['local_pbx_transport'] ?? 'udp';
            $localPbxSipUri = "sip:{$validated['local_pbx_ip']}:{$port};transport={$transport}";
        }

        try {
            // Create trunk record immediately (so we can return the ID for polling)
            $trunk = UserSipTrunk::create([
                'user_id' => $user->id,
                'twilio_account_sid' => trim($twilioAccountSid),
                'twilio_auth_token' => encrypt(trim($twilioAuthToken)),
                'trunk_friendly_name' => $friendlyName,
                'setup_step' => 'credentials',
                'setup_progress' => 0,
                'status' => 'setting_up',

                // Local PBX fields
                'routes_to_local_pbx' => !empty($validated['routes_to_local_pbx']),
                'local_pbx_sip_uri' => $localPbxSipUri,
                'local_pbx_ip' => $validated['local_pbx_ip'] ?? null,
                'local_pbx_port' => $validated['local_pbx_port'] ?? 5060,
                'local_pbx_transport' => $validated['local_pbx_transport'] ?? 'udp',
                'local_pbx_username' => $validated['local_pbx_username'] ?? null,
                'local_pbx_password' => !empty($validated['local_pbx_password'])
                    ? encrypt($validated['local_pbx_password'])
                    : null,
            ]);

            // Dispatch async job — setup runs in background
            SetupSipTrunkJob::dispatch($trunk);

            Log::info("SIP Trunk setup job dispatched", [
                'trunk_id' => $trunk->id,
                'user_id' => $user->id,
                'routes_to_local_pbx' => $trunk->routes_to_local_pbx,
                'local_pbx_ip' => $trunk->local_pbx_ip,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'SIP Trunk setup started. Please wait while we configure everything.',
                'trunk_id' => $trunk->id,
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to create SIP Trunk record for user {$user->id}", [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display trunk details
     */
    public function show(UserSipTrunk $trunk)
    {
        // Ensure user owns this trunk
        if ($trunk->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this trunk.');
        }

        $trunk->load(['phoneNumbers.assignedUser']);

        // Get statistics
        $statistics = $this->callService->getTrunkStatistics($trunk);

        // Get recent health logs
        $healthHistory = $this->healthService->getHealthHistory($trunk, 10);

        // Get issues if any
        $issues = $this->healthService->needsAttention($trunk);

        // Pre-load assigned resource names for phone numbers
        $campaignIds = $trunk->phoneNumbers->where('assigned_to', 'campaign')->pluck('assigned_id')->filter();
        $aiAgentIds = $trunk->phoneNumbers->where('assigned_to', 'ai_agent')->pluck('assigned_id')->filter();
        $campaigns = $campaignIds->isNotEmpty() ? Campaign::whereIn('id', $campaignIds)->pluck('name', 'id') : collect();
        $aiAgents = $aiAgentIds->isNotEmpty() ? AiAgent::whereIn('id', $aiAgentIds)->pluck('name', 'id') : collect();

        return Inertia::render('TwilioIntegration/SipTrunk/Show', [
            'trunk' => [
                'id' => $trunk->id,
                'friendly_name' => $trunk->trunk_friendly_name ?? 'SIP Trunk',
                'status' => $trunk->status ?? 'unknown',
                'health_status' => $trunk->health_status ?? 'unknown',
                'trunk_sid' => $trunk->trunk_sid ?? 'N/A',
                'trunk_domain_name' => $trunk->trunk_domain_name ?? 'N/A',
                'origination_sip_url' => $trunk->origination_sip_url ?? 'N/A',
                'termination_method' => $trunk->termination_method ?? 'N/A',
                'created_at' => $trunk->created_at->toISOString(),
                'last_call_at' => $trunk->last_call_at?->toISOString(),
                'last_health_check_at' => $trunk->last_health_check_at?->toISOString(),
                'is_operational' => $trunk->isOperational(),

                // Local PBX configuration
                'routes_to_local_pbx' => $trunk->routes_to_local_pbx,
                'local_pbx_sip_uri' => $trunk->local_pbx_sip_uri,
                'local_pbx_ip' => $trunk->local_pbx_ip,
                'local_pbx_port' => $trunk->local_pbx_port,
                'local_pbx_transport' => $trunk->local_pbx_transport,

                'phone_numbers' => $trunk->phoneNumbers->map(function ($number) use ($campaigns, $aiAgents) {
                    // Resolve the assigned resource name
                    $assignedName = null;
                    if ($number->assigned_to === 'softphone' && $number->assignedUser) {
                        $assignedName = $number->assignedUser->name;
                    } elseif ($number->assigned_to === 'campaign' && $number->assigned_id) {
                        $assignedName = $campaigns->get($number->assigned_id);
                    } elseif ($number->assigned_to === 'ai_agent' && $number->assigned_id) {
                        $assignedName = $aiAgents->get($number->assigned_id);
                    }

                    return [
                        'id' => $number->id,
                        'phone_number' => $number->phone_number,
                        'formatted' => $number->formatted,
                        'friendly_name' => $number->friendly_name,
                        'assigned_to' => $number->assigned_to ?? 'unassigned',
                        'assigned_id' => $number->assigned_id,
                        'assigned_name' => $assignedName,
                        'user_id' => $number->user_id,
                        'has_voice' => $number->hasVoice(),
                        'has_sms' => $number->hasSms(),
                        'has_mms' => $number->hasMms(),
                        'is_available' => $number->isAvailable(),
                    ];
                }),
            ],
            'statistics' => $statistics,
            'healthHistory' => $healthHistory->map(fn($log) => [
                'id' => $log->id,
                'check_type' => $log->check_type ?? 'unknown',
                'status' => $log->status ?? 'unknown',
                'response_time_ms' => $log->response_time_ms ?? 0,
                'error_message' => $log->error_message,
                'checked_at' => $log->checked_at->toISOString(),
            ]),
            'issues' => $issues['issues'] ?? [],
        ]);
    }

    /**
     * Get setup status - for polling during setup
     */
    public function setupStatus(UserSipTrunk $trunk)
    {
        // Ensure user owns this trunk
        if ($trunk->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this trunk.');
        }

        // Refresh from DB to get latest progress from the background job
        $trunk->refresh();

        $response = [
            'status' => $trunk->status,
            'setup_step' => $trunk->setup_step,
            'setup_progress' => $trunk->setup_progress,
            'setup_error' => $trunk->last_error,
            'is_setup_complete' => $trunk->isSetupComplete(),
            'is_failed' => $trunk->status === 'error',
        ];

        // Include trunk details when setup is complete
        if ($trunk->isSetupComplete()) {
            $response['trunk'] = [
                'id' => $trunk->id,
                'friendly_name' => $trunk->trunk_friendly_name,
                'trunk_domain_name' => $trunk->trunk_domain_name,
                'phone_numbers_count' => $trunk->phoneNumbers()->count(),
            ];
        }

        return response()->json($response);
    }

    /**
     * Sync phone numbers
     */
    public function sync(UserSipTrunk $trunk)
    {
        // Ensure user owns this trunk
        if ($trunk->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this trunk.');
        }

        try {
            $result = $this->autoSetupService->syncPhoneNumbers($trunk);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => "Synced {$result['synced_count']} phone numbers successfully.",
                    'synced_count' => $result['synced_count'],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'] ?? 'Failed to sync phone numbers.',
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error("Phone number sync failed for trunk {$trunk->id}", [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during sync: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Run health check
     */
    public function health(UserSipTrunk $trunk)
    {
        // Ensure user owns this trunk
        if ($trunk->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this trunk.');
        }

        try {
            $result = $this->healthService->performHealthCheck($trunk);

            return response()->json([
                'success' => true,
                'healthy' => $result['healthy'],
                'health_status' => $result['health_status'],
                'response_time_ms' => $result['response_time_ms'],
                'issues' => $result['issues'],
                'tests' => $result['tests'],
            ]);
        } catch (\Exception $e) {
            Log::error("Health check failed for trunk {$trunk->id}", [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during health check: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Disconnect/Delete trunk
     * 
     * If ?permanent=true, also deletes from Twilio and removes from database
     */
    public function destroy(UserSipTrunk $trunk, Request $request)
    {
        // Ensure user owns this trunk
        if ($trunk->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this trunk.');
        }

        $permanent = $request->boolean('permanent', false);

        try {
            if ($permanent) {
                // Permanently delete: remove from Twilio and delete from database

                // Only attempt Twilio deletion if trunk_sid exists
                if (!empty($trunk->trunk_sid)) {
                    try {
                        $client = $trunk->getTwilioClient();

                        // Remove phone numbers from trunk first
                        $trunkNumbers = $client->trunking->v1
                            ->trunks($trunk->trunk_sid)
                            ->phoneNumbers
                            ->read();

                        foreach ($trunkNumbers as $number) {
                            try {
                                $client->trunking->v1
                                    ->trunks($trunk->trunk_sid)
                                    ->phoneNumbers($number->sid)
                                    ->delete();
                            } catch (\Exception $e) {
                                Log::warning("Failed to remove number {$number->sid} from trunk: " . $e->getMessage());
                            }
                        }

                        // Delete the trunk from Twilio
                        $client->trunking->v1->trunks($trunk->trunk_sid)->delete();

                        Log::info("SIP Trunk permanently deleted from Twilio", ['trunk_sid' => $trunk->trunk_sid]);

                    } catch (\Exception $e) {
                        Log::warning("Failed to delete trunk from Twilio (continuing with local deletion): " . $e->getMessage());
                        // Continue with local deletion even if Twilio deletion fails
                    }
                } else {
                    Log::info("Trunk has no trunk_sid, skipping Twilio deletion", ['trunk_id' => $trunk->id]);
                }

                // Delete related records
                $trunk->phoneNumbers()->delete();
                $trunk->healthLogs()->delete();
                
                // Delete the trunk record
                $trunk->delete();

                Log::info("SIP Trunk permanently deleted for user {$trunk->user_id}", [
                    'trunk_id' => $trunk->id,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'SIP Trunk permanently deleted.',
                ]);
            } else {
                // Soft disconnect: just mark as suspended
                $trunk->update([
                    'status' => 'suspended',
                    'health_status' => 'down',
                ]);

                Log::info("SIP Trunk disconnected for user {$trunk->user_id}", [
                    'trunk_id' => $trunk->id,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'SIP Trunk disconnected successfully. You can reconnect it later.',
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Trunk disconnect/delete failed for trunk {$trunk->id}", [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while disconnecting: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign trunk phone number to a resource
     */
    public function assignNumber(Request $request, UserSipTrunk $trunk, TrunkPhoneNumber $number)
    {
        // Ensure user owns this trunk
        if ($trunk->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this trunk.');
        }

        // Ensure number belongs to this trunk
        if ($number->trunk_id !== $trunk->id) {
            abort(403, 'This phone number does not belong to this trunk.');
        }

        $validated = $request->validate([
            'assigned_to' => 'required|in:softphone,campaign,ai_agent,unassigned',
            'assigned_id' => 'nullable|integer',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        try {
            $assignedTo = $validated['assigned_to'];
            $assignedId = $validated['assigned_id'] ?? null;
            $userId = $validated['user_id'] ?? null;

            // Validate assignment logic
            if ($assignedTo === 'softphone') {
                // For softphone, user_id is required
                if (!$userId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'User ID is required for softphone assignment.',
                    ], 422);
                }

                // Use the helper method
                $number->assignToUser($userId);

                $assignedUser = User::find($userId);
                $message = "Number assigned to {$assignedUser->name}'s softphone successfully.";

            } elseif ($assignedTo === 'campaign') {
                if (!$assignedId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Campaign ID is required for campaign assignment.',
                    ], 422);
                }

                // Verify campaign exists and belongs to user
                $campaign = Campaign::where('id', $assignedId)
                    ->where('user_id', Auth::id())
                    ->first();

                if (!$campaign) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Campaign not found or does not belong to you.',
                    ], 404);
                }

                $number->update([
                    'assigned_to' => 'campaign',
                    'assigned_id' => $assignedId,
                    'user_id' => null,
                ]);

                $message = "Number assigned to campaign '{$campaign->name}' successfully.";

            } elseif ($assignedTo === 'ai_agent') {
                if (!$assignedId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'AI Agent ID is required for AI agent assignment.',
                    ], 422);
                }

                // Verify AI agent exists and belongs to user
                $aiAgent = AiAgent::where('id', $assignedId)
                    ->where('user_id', Auth::id())
                    ->first();

                if (!$aiAgent) {
                    return response()->json([
                        'success' => false,
                        'message' => 'AI Agent not found or does not belong to you.',
                    ], 404);
                }

                $number->update([
                    'assigned_to' => 'ai_agent',
                    'assigned_id' => $assignedId,
                    'user_id' => null,
                ]);

                $message = "Number assigned to AI Agent '{$aiAgent->name}' successfully.";

            } else { // unassigned
                $number->unassign();
                $message = "Number unassigned successfully.";
            }

            Log::info("Trunk phone number assignment updated", [
                'number' => $number->phone_number,
                'assigned_to' => $assignedTo,
                'assigned_id' => $assignedId,
                'user_id' => $userId,
            ]);

            return response()->json([
                'success' => true,
                'message' => $message,
                'number' => [
                    'id' => $number->id,
                    'phone_number' => $number->phone_number,
                    'assigned_to' => $number->assigned_to,
                    'assigned_id' => $number->assigned_id,
                    'user_id' => $number->user_id,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to assign trunk phone number", [
                'number_id' => $number->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign number: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available resources for assignment (users, campaigns, AI agents)
     */
    public function getAssignmentOptions(UserSipTrunk $trunk)
    {
        // Ensure user owns this trunk
        if ($trunk->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this trunk.');
        }

        try {
            // Get all users (for softphone assignment)
            $users = User::select('id', 'name', 'email')
                ->orderBy('name')
                ->get();

            // Get user's campaigns
            $campaigns = Campaign::where('user_id', Auth::id())
                ->select('id', 'name', 'status')
                ->orderBy('name')
                ->get();

            // Get user's AI agents
            $aiAgents = AiAgent::where('user_id', Auth::id())
                ->select('id', 'name', 'type', 'active')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'users' => $users,
                'campaigns' => $campaigns,
                'ai_agents' => $aiAgents,
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to get assignment options", [
                'trunk_id' => $trunk->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load assignment options.',
            ], 500);
        }
    }
}
