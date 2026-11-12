<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Calls\EndCallAction;
use App\Actions\Calls\InitiateCallAction;
use App\Actions\Calls\RecordDtmfAction;
use App\Actions\Calls\UpdateCallStatusAction;
use App\Actions\Telephony\IssueSoftphoneTokenAction;
use App\Enums\TelephonyProvider;
use App\Models\AiAgent;
use App\Models\Call;
use App\Models\PhoneNumber;
use App\Models\ProcessedWebhook;
use App\Models\TrunkPhoneNumber;
use App\Models\TwilioGlobalConfig;
use App\Services\SentimentAnalysisService;
use App\Services\Telephony\TelephonyManager;
use App\Services\TwilioSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Twilio\Rest\Client;

class CallController extends Controller
{
    /**
     * Every status value App\Actions\Calls\UpdateCallStatusAction::
     * mapTwilioStatus() recognises. Kept here (not read from that
     * method, which is private) so webhookStatus()'s Plivo branch can
     * decide whether a normalised status is safe to write — see that
     * branch's own comment for why writing an unrecognised one is worse
     * than writing none at all.
     */
    private const RECOGNISED_CALL_STATUSES = [
        'queued', 'initiated', 'ringing', 'in-progress',
        'completed', 'busy', 'no-answer', 'failed', 'canceled',
    ];

    /**
     * Display the softphone page
     */
    public function softphone(Request $request): Response
    {
        $user = $request->user();

        // Check if voice service is configured (don't mention Twilio to users).
        // Answers for whichever provider this installation is locked to —
        // asking TwilioGlobalConfig left a Plivo installation's dialler
        // permanently disabled behind "Voice calling service is not
        // configured". Same answer as /api/telephony/status.
        $isConfigured = app(IssueSoftphoneTokenAction::class)->isConfigured();

        // Check if user has assigned phone numbers (Twilio direct OR SIP trunk)
        $hasAssignedNumbers = PhoneNumber::where('user_id', $user->id)
            ->where('status', 'assigned')
            ->exists()
            || TrunkPhoneNumber::where('user_id', $user->id)
                ->where('status', 'active')
                ->where('assigned_to', 'softphone')
                ->exists();

        return Inertia::render('calls/manual', [
            'isConfigured' => $isConfigured,
            'hasAssignedNumbers' => $hasAssignedNumbers,
        ]);
    }

    /**
     * API endpoint for softphone to get user's assigned numbers
     * No KYC requirement - used by WebRTC softphone for both customers and agents
     */
    public function softphoneNumbers(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;

        try {
            // Get numbers assigned to AI agents (to exclude from softphone)
            $aiAgentNumbers = AiAgent::whereNotNull('phone_number')
                ->pluck('phone_number')
                ->toArray();

            // Get all phone numbers assigned to this user from phone_numbers table
            // This includes both twilio_direct and sip_trunk source numbers
            $numbers = PhoneNumber::where('user_id', $userId)
                ->where('status', 'assigned')
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
        } catch (\Exception $e) {
            Log::error('Failed to fetch softphone numbers', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'numbers' => [],
                'error' => 'Failed to load phone numbers',
            ], 500);
        }
    }

    /**
     * Display call history
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $user->loadMissing('roles');

        // Check permission - render empty state instead of throwing AuthorizationException
        // This prevents raw JSON 403 responses that break Inertia
        if (! $user->hasPermission('calls.view') && ! $user->isAdmin()) {
            return Inertia::render('calls/index', [
                'calls' => ['data' => [], 'links' => [], 'meta' => ['current_page' => 1, 'from' => null, 'last_page' => 1, 'per_page' => 10, 'to' => null, 'total' => 0]],
                'stats' => ['total' => 0, 'completed' => 0, 'failed' => 0, 'in_progress' => 0, 'inbound' => 0, 'outbound' => 0],
                'filters' => [],
                'error' => 'You do not have permission to view call history.',
            ]);
        }

        // Build query based on role
        $query = Call::query()
            ->with(['campaign', 'campaignContact', 'user'])
            ->latest();

        // Apply strict role-based filtering for data isolation
        if ($user->hasRole('admin')) {
            // Admin can see all calls - no filter needed
        } elseif ($user->hasRole('customer')) {
            // Customer can ONLY see their own calls (strict isolation)
            $query->where('user_id', $user->id);
        } elseif ($user->hasRole('agent')) {
            // Agent can see their parent customer's calls
            $query->where('user_id', $user->parent_user_id);
        } else {
            // Default: users can only see their own calls
            $query->where('user_id', $user->id);
        }

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by call type
        if ($request->has('call_type') && $request->call_type !== 'all') {
            $query->where('call_type', $request->call_type);
        }

        // Filter by direction (inbound/outbound)
        if ($request->has('direction') && $request->direction !== 'all') {
            $query->where('direction', $request->direction);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Search by phone number
        if ($request->has('search') && $request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('to_number', 'like', '%'.$request->search.'%')
                    ->orWhere('from_number', 'like', '%'.$request->search.'%');
            });
        }

        $calls = $query->paginate(10)->withQueryString();

        // Get statistics based on user role with strict isolation
        $statsQuery = Call::query();

        // Apply SAME role-based filtering to statistics - CRITICAL for data isolation
        if ($user->hasRole('admin')) {
            // Admin sees all calls - no filter needed
        } elseif ($user->hasRole('customer')) {
            // Customer can ONLY see their own call statistics (strict isolation)
            $statsQuery->where('user_id', $user->id);
        } elseif ($user->hasRole('agent')) {
            // Agent can see their parent customer's call statistics
            $statsQuery->where('user_id', $user->parent_user_id);
        } else {
            // Default: users can only see their own statistics
            $statsQuery->where('user_id', $user->id);
        }

        $stats = [
            'total' => (clone $statsQuery)->count(),
            'completed' => (clone $statsQuery)->where('status', 'completed')->count(),
            'failed' => (clone $statsQuery)->whereIn('status', ['failed', 'busy', 'no-answer'])->count(),
            'in_progress' => (clone $statsQuery)->whereIn('status', ['initiated', 'ringing', 'in-progress'])->count(),
            'inbound' => (clone $statsQuery)->where('direction', 'inbound')->count(),
            'outbound' => (clone $statsQuery)->where('direction', 'outbound')->count(),
        ];

        return Inertia::render('calls/index', [
            'calls' => $calls,
            'stats' => $stats,
            'filters' => $request->only(['status', 'call_type', 'direction', 'from_date', 'to_date', 'search']),
        ]);
    }

    /**
     * Display a specific call details
     */
    public function show(Request $request, Call $call): Response
    {
        $this->authorize('view', $call);

        $call->load(['campaign', 'campaignContact', 'logs', 'dtmfResponses']);

        // Get contact information if available
        $contact = null;
        if ($call->campaignContact) {
            $contact = [
                'id' => $call->campaignContact->contact->id ?? null,
                'name' => $call->campaignContact->contact->name ?? null,
                'phone_number' => $call->to_number,
            ];
        }

        return Inertia::render('calls/Show', [
            'call' => $call,
            'contact' => $contact,
            'breadcrumbs' => [
                ['title' => 'Dashboard', 'href' => route('dashboard')],
                ['title' => 'Call History', 'href' => route('calls.index')],
                ['title' => 'Call #'.$call->id, 'href' => route('calls.show', $call->id)],
            ],
        ]);
    }

    /**
     * Sync call data with Twilio
     */
    public function syncWithTwilio(
        Request $request,
        TwilioSyncService $syncService
    ): JsonResponse {
        try {
            $synced = $syncService->syncActiveCalls($request->user()->id);

            return response()->json([
                'success' => true,
                'synced' => $synced,
                'message' => "Synced $synced active calls",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync specific call with Twilio
     */
    public function syncCall(
        Request $request,
        Call $call,
        TwilioSyncService $syncService
    ): JsonResponse {
        $this->authorize('view', $call);

        try {
            $success = $syncService->syncCall($call);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'call' => $call->fresh(),
                    'message' => 'Call synced successfully',
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to sync call',
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Initiate a new call
     */
    public function initiate(
        Request $request,
        InitiateCallAction $action
    ): JsonResponse {
        $this->authorize('create', Call::class);

        // Log incoming request for debugging
        Log::info('Call Initiate Request', [
            'all_input' => $request->all(),
            'user_id' => $request->user()?->id,
            'method' => $request->method(),
        ]);

        try {
            $validated = $request->validate([
                'to_number' => ['required', 'string'],
                'from_number' => ['nullable', 'string'],
                'number_source' => ['nullable', 'string', 'in:twilio_direct,sip_trunk'],
                'enable_recording' => ['boolean'],
                'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
                'campaign_contact_id' => ['nullable', 'integer', 'exists:campaign_contacts,id'],
            ]);

            Log::info('Validation passed', ['validated' => $validated]);

            $call = $action->execute($request->user(), $validated);

            return response()->json([
                'success' => true,
                'call' => [
                    'id' => $call->id,
                    'twilio_call_sid' => $call->twilio_call_sid,
                    'to_number' => $call->to_number,
                    'from_number' => $call->from_number,
                    'status' => $call->status,
                ],
            ]);
        } catch (ValidationException $e) {
            Log::error('Call initiation validation failed', [
                'errors' => $e->errors(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'The payload is invalid.',
                'validation_errors' => $e->errors(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Call initiation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * End an active call
     */
    public function end(
        Request $request,
        Call $call,
        EndCallAction $action
    ): JsonResponse {
        $this->authorize('execute', $call);

        try {

            $success = $action->execute($call);

            return response()->json([
                'success' => $success,
                'call' => [
                    'id' => $call->id,
                    'status' => $call->fresh()->status,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Toggle hold on an active call via Twilio REST API.
     *
     * Architecture: Browser SDK leg (twilio_call_sid) dials via <Dial><Number>, which creates
     * a child PSTN leg. We redirect the PSTN child leg:
     *   Hold   → hold-music TwiML (plays music to the remote party)
     *   Unhold → resume-call TwiML (reconnects PSTN leg to the browser via <Dial><Client>)
     *
     * The PSTN child SID is stored in metadata['pstn_call_sid'] by UpdateCallStatusAction
     * when Twilio fires the <Number> statusCallback with ParentCallSid set.
     * Fallback: query Twilio REST API list by parentCallSid.
     */
    public function hold(Request $request, Call $call): JsonResponse
    {
        $this->authorize('execute', $call);

        $action = $request->input('action', 'hold'); // 'hold' or 'unhold'

        if (! $call->twilio_call_sid) {
            return response()->json(['success' => false, 'error' => 'No Twilio Call SID available'], 400);
        }

        $config = TwilioGlobalConfig::active();
        if (! $config) {
            return response()->json(['success' => false, 'error' => 'Twilio not configured'], 500);
        }

        try {
            $client = new Client($config->account_sid, $config->getDecryptedAuthToken());

            // Prefer the stored PSTN child SID (captured via statusCallback when call was answered)
            $pstnCallSid = $call->metadata['pstn_call_sid'] ?? null;

            // Fallback: query Twilio for calls with this parent SID
            if (! $pstnCallSid) {
                $childCalls = $client->calls->read(['parentCallSid' => $call->twilio_call_sid], 1);
                if (! empty($childCalls)) {
                    $pstnCallSid = $childCalls[0]->sid;
                    // Cache it for future operations
                    $meta = $call->metadata ?? [];
                    $meta['pstn_call_sid'] = $pstnCallSid;
                    $call->update(['metadata' => $meta]);
                }
            }

            if (! $pstnCallSid) {
                return response()->json([
                    'success' => false,
                    'error' => 'No active PSTN call leg found. The call may not be connected yet.',
                ], 400);
            }

            if ($action === 'hold') {
                // Redirect the PSTN leg to hold music — the remote party hears music
                $holdUrl = route('twiml.hold-music');
                $client->calls($pstnCallSid)->update(['url' => $holdUrl, 'method' => 'POST']);
            } else {
                // Reconnect: redirect the PSTN leg back to the browser via <Dial><Client>
                $resumeUrl = route('twiml.resume-call', ['call' => $call->id]);
                $client->calls($pstnCallSid)->update(['url' => $resumeUrl, 'method' => 'POST']);
            }

            Log::info("Call {$action}", [
                'call_id' => $call->id,
                'pstn_call_sid' => $pstnCallSid,
                'action' => $action,
            ]);

            return response()->json(['success' => true, 'action' => $action]);
        } catch (\Exception $e) {
            Log::error("Failed to {$action} call", ['call_id' => $call->id, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get call status
     */
    public function status(Request $request, Call $call): JsonResponse
    {
        $this->authorize('view', $call);

        return response()->json([
            'id' => $call->id,
            'status' => $call->status,
            'duration_seconds' => $call->duration_seconds,
            'started_at' => $call->started_at?->toISOString(),
            'ended_at' => $call->ended_at?->toISOString(),
            'recording_url' => $call->recording_url,
            'dtmf_digits' => $call->dtmf_digits,
        ]);
    }

    /**
     * Webhook: Handle call status updates from Twilio or Plivo.
     *
     * This endpoint is used as both:
     * - statusCallback on <Number>/<Client> (fire-and-forget, response format doesn't matter)
     * - action on <Dial> (Twilio expects TwiML response to control what happens next)
     *
     * We return TwiML/Plivo XML to satisfy both use cases — an empty
     * <Response/> is valid, inert XML for either provider's dialect (both
     * use <Response> as their root element).
     *
     * The provider is read from the route's own 'provider' parameter
     * (webhooks/twilio/* never sets one, so it defaults to 'twilio';
     * webhooks/plivo/* sets it via ->defaults()) rather than from the
     * installation's current lock: a webhook in flight must be
     * interpreted as whichever provider actually sent it, even if the
     * lock changes between the call being placed and this webhook
     * arriving. It is deliberately not a typed method parameter —
     * Laravel's controller-dependency resolution splices class
     * dependencies into the route-parameter list positionally
     * (ResolvesRouteDependencies::resolveMethodDependencies()), which
     * mis-assigns a scalar default sitting after them once the route
     * also has a real bound segment (call_id here); reading it from the
     * route explicitly sidesteps that entirely.
     */
    public function webhookStatus(
        Request $request,
        UpdateCallStatusAction $action,
        TelephonyManager $telephony
    ): \Illuminate\Http\Response {
        $provider = $request->route('provider', 'twilio');
        $twimlResponse = '<?xml version="1.0" encoding="UTF-8"?><Response/>';

        $callId = $request->input('call_id') ?? $request->route('call_id');

        if ($provider === 'twilio') {
            // Byte-identical to this method's pre-existing behaviour.
            $providerCallId = $request->input('CallSid');
            $callStatus = $request->input('CallStatus', 'unknown');
            $data = $request->all();
        } else {
            // Normalise, then re-express in the vocabulary
            // UpdateCallStatusAction already reads — the same handler
            // Twilio's branch above reaches, not a Plivo copy of it.
            $normalizer = $telephony->webhookNormalizer(TelephonyProvider::tryFrom($provider) ?? TelephonyProvider::TWILIO);
            $normalized = $normalizer->normalizeStatusCallback($request->all());
            $providerCallId = $normalized['call_id'] ?: null;
            $callStatus = $normalized['status'] ?: 'unknown';
            $data = [
                'CallSid' => $providerCallId,
                'CallDuration' => $normalized['duration'],
                'Price' => $normalized['price'],
                'AnsweredBy' => $normalized['answered_by'],
            ];

            // Only include CallStatus when it is one of the values
            // UpdateCallStatusAction::mapTwilioStatus() actually
            // recognises. That method falls back to the call's *current*
            // status only when the key is absent from $data — Plivo's
            // own normaliser passes an unrecognised or missing
            // CallStatus through unchanged (mapCallStatus()'s default
            // arm), and unconditionally setting the key here would
            // always satisfy that isset() check with a value
            // mapTwilioStatus() cannot map either, silently rewriting an
            // in-progress or even completed call back to 'initiated' —
            // which the campaign loop then counts as permanently active,
            // consuming a concurrency slot forever. Omitting the key
            // instead preserves the call's current status, exactly as
            // an absent CallStatus already does for Twilio.
            if (in_array($normalized['status'], self::RECOGNISED_CALL_STATUSES, true)) {
                $data['CallStatus'] = $normalized['status'];
            }
        }

        // Try to find call by call_id first, then by the provider's own
        // call id (for inbound calls, whose URL carries no call_id).
        if ($callId) {
            $call = Call::find($callId);
        } elseif ($providerCallId) {
            $call = Call::where('twilio_call_sid', $providerCallId)->first();
        } else {
            Log::warning('Webhook received without call_id or provider call id', $request->all());

            return response($twimlResponse, 200)->header('Content-Type', 'text/xml');
        }

        if (! $call) {
            Log::warning('Webhook received for non-existent call', [
                'call_id' => $callId,
                'provider_call_id' => $providerCallId,
            ]);

            return response($twimlResponse, 200)->header('Content-Type', 'text/xml');
        }

        // Idempotency: prevent duplicate processing of the same status
        // event. Same mechanism Twilio already uses (ProcessedWebhook),
        // keyed by provider so a Twilio and a Plivo event never collide.
        $eventId = ($providerCallId ?: 'call_'.$call->id).'_'.$callStatus;
        if (! ProcessedWebhook::markProcessed($provider, $eventId, 'call_status', ['call_id' => $call->id])) {
            Log::info('Duplicate webhook ignored', ['event_id' => $eventId]);

            return response($twimlResponse, 200)->header('Content-Type', 'text/xml');
        }

        try {
            $action->execute($call, $data);

            return response($twimlResponse, 200)->header('Content-Type', 'text/xml');
        } catch (\Exception $e) {
            Log::error('Webhook status error', [
                'call_id' => $call->id,
                'provider_call_id' => $providerCallId,
                'error' => $e->getMessage(),
            ]);

            return response($twimlResponse, 200)->header('Content-Type', 'text/xml');
        }
    }

    /**
     * Webhook: Handle recording completion from Twilio or Plivo.
     *
     * See webhookStatus()'s docblock for why $provider is read from the
     * route rather than declared as a typed method parameter. See
     * xmlAcknowledgement()'s own docblock for why every path answers
     * with an empty XML document at 200, never JSON and never a non-2xx
     * status: campaignCallTts()/campaignCallVoice() wire this URL up as
     * the `action` on Plivo's <Record>, which defaults redirect="true"
     * and therefore fetches and runs whatever this endpoint answers,
     * on failure paths too.
     */
    public function webhookRecording(Request $request, TelephonyManager $telephony): \Illuminate\Http\Response
    {
        $provider = $request->route('provider', 'twilio');
        $callId = $request->input('call_id') ?? $request->route('call_id');

        if ($provider === 'twilio') {
            // Byte-identical to this method's pre-existing behaviour.
            $recordingSid = $request->input('RecordingSid');
            $recordingUrl = $request->input('RecordingUrl');
            $recordingDuration = $request->input('RecordingDuration');
        } else {
            $normalized = $telephony
                ->webhookNormalizer(TelephonyProvider::tryFrom($provider) ?? TelephonyProvider::TWILIO)
                ->normalizeRecordingCallback($request->all());
            $recordingSid = $normalized['recording_id'] ?: null;
            $recordingUrl = $normalized['recording_url'];
            $recordingDuration = $normalized['duration'];
        }

        if (! $callId) {
            return $this->xmlAcknowledgement();
        }

        $call = Call::find($callId);

        if (! $call) {
            return $this->xmlAcknowledgement();
        }

        // Idempotency: prevent duplicate recording processing. Same
        // mechanism Twilio already uses (ProcessedWebhook), keyed by
        // provider.
        if ($recordingSid && ! ProcessedWebhook::markProcessed($provider, $recordingSid, 'recording', ['call_id' => $callId])) {
            Log::info('Duplicate recording webhook ignored', ['recording_sid' => $recordingSid]);

            return $this->xmlAcknowledgement();
        }

        $call->update([
            'recording_sid' => $recordingSid,
            'recording_url' => $recordingUrl,
            'recording_duration' => $recordingDuration,
        ]);

        Log::info('Recording webhook processed', [
            'call_id' => $callId,
            'recording_sid' => $recordingSid,
        ]);

        return $this->xmlAcknowledgement();
    }

    /**
     * Webhook: Handle transcription callback from Twilio
     */
    public function webhookTranscription(Request $request): JsonResponse
    {
        $callId = $request->input('call_id') ?? $request->route('call_id');
        $transcriptionSid = $request->input('TranscriptionSid');
        $transcriptionText = $request->input('TranscriptionText');
        $transcriptionStatus = $request->input('TranscriptionStatus');
        $transcriptionUrl = $request->input('TranscriptionUrl');
        $transcriptionPrice = $request->input('TranscriptionPrice');

        if (! $callId) {
            return response()->json(['error' => 'Missing call_id'], 400);
        }

        $call = Call::find($callId);

        if (! $call) {
            return response()->json(['error' => 'Call not found'], 404);
        }

        $call->update([
            'transcript_sid' => $transcriptionSid,
            'transcript_text' => $transcriptionText,
            'transcript_status' => $transcriptionStatus,
            'transcript_price' => $transcriptionPrice ? abs((float) $transcriptionPrice) : null,
        ]);

        Log::info('Transcription webhook processed', [
            'call_id' => $callId,
            'transcription_sid' => $transcriptionSid,
            'status' => $transcriptionStatus,
        ]);

        // Trigger AI sentiment analysis if transcription is complete and has text
        if ($transcriptionStatus === 'completed' && ! empty($transcriptionText)) {
            try {
                $sentimentService = app(SentimentAnalysisService::class);
                $sentimentService->processCall($call);
            } catch (\Exception $e) {
                Log::warning('Sentiment analysis trigger failed', [
                    'call_id' => $callId,
                    'error' => $e->getMessage(),
                ]);
                // Don't fail the webhook even if sentiment analysis fails
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * Export calls to CSV
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', Call::class);

        $user = $request->user();
        $user->loadMissing('roles');

        // Build query with same role-based filtering as index
        $query = Call::query()
            ->with(['campaign', 'campaignContact', 'user'])
            ->latest();

        // Apply strict role-based filtering
        if ($user->hasRole('admin')) {
            // Admin can see all calls
        } elseif ($user->hasRole('customer')) {
            $query->where('user_id', $user->id);
        } elseif ($user->hasRole('agent')) {
            $query->where('user_id', $user->parent_user_id);
        } else {
            $query->where('user_id', $user->id);
        }

        // Apply same filters as index page
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('call_type') && $request->call_type !== 'all') {
            $query->where('call_type', $request->call_type);
        }

        if ($request->has('direction') && $request->direction !== 'all') {
            $query->where('direction', $request->direction);
        }

        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->has('search') && $request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('to_number', 'like', '%'.$request->search.'%')
                    ->orWhere('from_number', 'like', '%'.$request->search.'%');
            });
        }

        // Generate CSV using chunked streaming to prevent memory exhaustion
        $filename = 'calls_export_'.date('Y-m-d_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($query) {
            $file = fopen('php://output', 'w');

            // CSV Headers
            fputcsv($file, [
                'ID',
                'Status',
                'Direction',
                'From Number',
                'To Number',
                'Contact Name',
                'Call Type',
                'Campaign',
                'Duration (seconds)',
                'Cost',
                'Recording URL',
                'Started At',
                'Ended At',
                'Created At',
            ]);

            // Stream in chunks of 500 records to keep memory usage low
            $query->chunk(500, function ($calls) use ($file) {
                foreach ($calls as $call) {
                    $contactName = '';
                    if ($call->campaignContact) {
                        $contactName = trim(
                            ($call->campaignContact->first_name ?? '').' '.
                            ($call->campaignContact->last_name ?? '')
                        );
                    }

                    fputcsv($file, [
                        $call->id,
                        $call->status,
                        $call->direction,
                        $call->from_number,
                        $call->to_number,
                        $contactName ?: $call->to_number,
                        $call->call_type,
                        $call->campaign ? $call->campaign->name : '',
                        $call->duration ?? 0,
                        $call->price ?? 0,
                        $call->recording_url ?? '',
                        $call->started_at ?? '',
                        $call->ended_at ?? '',
                        $call->created_at,
                    ]);
                }
            });

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Webhook: Handle DTMF input from Twilio or Plivo.
     *
     * See xmlAcknowledgement()'s own docblock for why every path answers
     * with an empty XML document at 200, never JSON and never a non-2xx
     * status: campaignCallTts()/campaignCallVoice() wire this URL up as
     * the `action` on Plivo's <GetDigits>, which defaults
     * redirect="true" and therefore fetches and runs whatever this
     * endpoint answers, on failure paths too.
     */
    public function webhookDtmf(
        Request $request,
        RecordDtmfAction $action
    ): \Illuminate\Http\Response {
        $callId = $request->input('call_id') ?? $request->route('call_id');
        $digits = $request->input('Digits');

        if (! $callId || ! $digits) {
            return $this->xmlAcknowledgement();
        }

        $call = Call::find($callId);

        if (! $call) {
            return $this->xmlAcknowledgement();
        }

        $action->execute($call, $digits);

        return $this->xmlAcknowledgement();
    }

    /**
     * Save disposition and notes for a call (post-call wrap-up)
     */
    public function saveDisposition(Request $request, Call $call): JsonResponse
    {
        $this->authorize('execute', $call);

        $validated = $request->validate([
            'disposition' => ['required', 'string', 'in:interested,not_interested,callback,wrong_number,voicemail,no_answer,do_not_call'],
            'call_notes' => ['nullable', 'string', 'max:5000'],
            'callback_at' => ['nullable', 'date'],
        ]);

        $call->update($validated);

        // Update linked contact's metrics if contact_id is set
        if ($call->contact_id) {
            $contact = $call->contact;
            if ($contact) {
                $contact->update([
                    'last_contacted_at' => $call->started_at ?? now(),
                ]);
                $contact->increment('total_calls');

                // Append disposition notes to contact notes if provided
                if (! empty($validated['call_notes'])) {
                    $dateStr = now()->format('Y-m-d H:i');
                    $existingNotes = $contact->notes ?? '';
                    $newNote = "[{$dateStr}] ({$validated['disposition']}): {$validated['call_notes']}";
                    $contact->update([
                        'notes' => $existingNotes ? $existingNotes."\n".$newNote : $newNote,
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'call' => [
                'id' => $call->id,
                'disposition' => $call->disposition,
                'call_notes' => $call->call_notes,
                'callback_at' => $call->callback_at?->toISOString(),
            ],
        ]);
    }

    /**
     * An empty acknowledgement document — the same literal webhookStatus()
     * already returns on every one of its own paths. Used by
     * webhookDtmf()/webhookRecording(), which campaignCallTts()/
     * campaignCallVoice() wire up as the `action` on Plivo's <GetDigits>/
     * <Record> (neither element sets redirect="false", so it defaults to
     * true): Plivo fetches whatever this endpoint answers and *runs* it
     * as the next document in the call, on both success and failure. A
     * JSON body there is not a Plivo XML document — the vocabulary
     * reference's own failure-code section names exactly this pair
     * (action URL + redirect defaulted true) as the shape that ends the
     * call. An empty <Response/> is valid, inert XML for either
     * provider's dialect and simply lets the call end normally instead.
     */
    private function xmlAcknowledgement(): \Illuminate\Http\Response
    {
        return response('<?xml version="1.0" encoding="UTF-8"?><Response/>', 200)
            ->header('Content-Type', 'text/xml');
    }
}
