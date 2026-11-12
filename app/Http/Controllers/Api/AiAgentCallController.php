<?php

namespace App\Http\Controllers\Api;

use App\Enums\TelephonyProvider;
use App\Http\Controllers\Controller;
use App\Models\AiAgent;
use App\Models\AiAgentCall;
use App\Models\TwilioCredential;
use App\Models\TwilioGlobalConfig;
use App\Services\AiAgent\CallStateManager;
use App\Services\Telephony\TelephonyManager;
use App\Services\TwilioService;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Twilio\Security\RequestValidator;

class AiAgentCallController extends Controller
{
    private CallStateManager $stateManager;

    private TwilioService $twilioService;

    public function __construct(CallStateManager $stateManager, TwilioService $twilioService)
    {
        $this->stateManager = $stateManager;
        $this->twilioService = $twilioService;
    }

    /**
     * The AI-agent calls the signed-in user may see: every call for an admin,
     * otherwise only calls taken by the user's own agents — archived agents
     * included, so their history stays with their owner.
     */
    private function visibleCalls(): Builder
    {
        $user = auth()->user();

        return AiAgentCall::query()->when(
            ! $user->isAdmin(),
            fn (Builder $query) => $query->whereHas(
                'agent',
                fn (Builder $agent) => $agent->withTrashed()->where('user_id', $user->id),
            ),
        );
    }

    public function index(Request $request)
    {
        $query = $this->visibleCalls();

        // Filter by agent
        if ($request->has('ai_agent_id')) {
            $query->where('ai_agent_id', $request->ai_agent_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by direction
        if ($request->has('direction')) {
            $query->where('direction', $request->direction);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('started_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('started_at', '<=', $request->to_date);
        }

        $calls = $query->with(['agent', 'contact', 'campaign'])
            ->orderBy('started_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json($calls);
    }

    public function show($id)
    {
        $call = $this->visibleCalls()->with(['agent', 'contact', 'campaign', 'conversations'])
            ->findOrFail($id);

        // Sync with Twilio to get real-time data
        try {
            $globalConfig = TwilioGlobalConfig::active();
            if (! $globalConfig) {
                \Log::warning('No active Twilio configuration found');

                return response()->json([
                    'call' => $call,
                    'transcript' => $call->getTranscript(),
                    'stats' => $this->stateManager->getCallStats($call),
                ]);
            }

            // Initialize Twilio service with global credentials
            $credential = new TwilioCredential([
                'account_sid' => $globalConfig->account_sid,
                'auth_token' => $globalConfig->getDecryptedAuthToken(),
            ]);
            $this->twilioService->initialize($credential);

            $twilioData = $this->twilioService->fetchCallDetails($call->call_sid);

            if ($twilioData) {
                // Update call with real-time Twilio data
                $twilioPrice = isset($twilioData['price']) && $twilioData['price'] ? abs((float) $twilioData['price']) : null;

                $call->update([
                    'status' => $this->mapTwilioStatus($twilioData['status']),
                    'duration' => $twilioData['duration'] ?? $call->duration,
                    'ended_at' => $twilioData['end_time'] ?? $call->ended_at,
                    'recording_url' => ! empty($twilioData['recordings']) ? $twilioData['recordings'][0]['url'] : $call->recording_url,
                ]);

                // Update Twilio cost if available
                if ($twilioPrice !== null) {
                    $call->updateCostBreakdown(twilioCost: $twilioPrice);
                }

                // Refresh the model to get updated data
                $call->refresh();
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to sync call with Twilio', [
                'call_id' => $id,
                'error' => $e->getMessage(),
            ]);
        }

        $transcript = $call->getTranscript();
        $stats = $this->stateManager->getCallStats($call);

        return response()->json([
            'call' => $call,
            'transcript' => $transcript,
            'stats' => $stats,
        ]);
    }

    /**
     * Map Twilio status to our internal status
     */
    private function mapTwilioStatus(string $twilioStatus): string
    {
        return match ($twilioStatus) {
            'queued', 'initiated' => 'initiated',
            'ringing' => 'ringing',
            'in-progress', 'answered' => 'in-progress',
            'completed' => 'completed',
            'busy', 'failed', 'no-answer', 'canceled' => 'failed',
            default => $twilioStatus,
        };
    }

    public function active()
    {
        $calls = $this->visibleCalls()->with(['agent', 'contact'])
            ->whereIn('status', ['initiated', 'ringing', 'in-progress'])
            ->whereNull('ended_at')  // Only calls that haven't ended
            ->orderBy('started_at', 'desc')
            ->get();

        return response()->json($calls);
    }

    /**
     * The absolute URL of one of this application's AI-agent webhooks,
     * for the provider the installation is locked to.
     *
     * Named after TwimlController::callbackRoute() and doing the same
     * job: the path belongs to the route definition, not to a string
     * literal that goes stale the moment a route moves — and Plivo's
     * siblings of these two routes exist, so there is a second path to
     * pick between. Unlike that method, the provider comes from the lock
     * rather than from a route parameter: nothing here is a webhook, it
     * is this application deciding where a call it is about to place
     * should report back to, which is the same thing MakeCampaignCallJob
     * and CampaignController already ask the lock.
     *
     * The host still comes from config('app.url'), not from route()'s own
     * absolute form, so a Twilio installation goes on sending the
     * byte-identical URL it always has.
     */
    private function callbackRoute(TelephonyProvider $provider, string $suffix): string
    {
        $name = $provider === TelephonyProvider::PLIVO
            ? "webhooks.plivo.{$suffix}"
            : "webhooks.twilio.{$suffix}";

        return rtrim(config('app.url'), '/').route($name, [], false);
    }

    /**
     * The answer URL for $provider — the document the provider fetches
     * when the callee picks up. Built the same way, and for the same
     * reason, as callbackRoute() above: twiml/* is signed for Twilio and
     * plivo-xml/* for Plivo, so a call placed on one provider pointed at
     * the other's answer URL is rejected the moment it is answered.
     */
    private function answerRoute(TelephonyProvider $provider): string
    {
        $name = $provider === TelephonyProvider::PLIVO
            ? 'plivo-xml.ai-agent.call'
            : 'twiml.ai-agent.call';

        return rtrim(config('app.url'), '/').route($name, [], false);
    }

    /**
     * Every URL an outbound AI-agent call needs, wired to $provider.
     *
     * A seam on purpose: initiate() below reaches the provider's API on
     * the next statement, so this is the last point at which the wiring
     * — which URL is handed to which parameter — can be checked without
     * a network call. Silently swapping the status and recording
     * callbacks would otherwise go unnoticed.
     *
     * @return array<string, mixed>
     */
    private function outboundCallParams(AiAgent $agent, TelephonyProvider $provider): array
    {
        $callParams = [
            'url' => $this->answerRoute($provider),
            'method' => 'POST',
            'statusCallback' => $this->callbackRoute($provider, 'ai-agent.call.status'),
            'statusCallbackMethod' => 'POST',
            'statusCallbackEvent' => ['initiated', 'ringing', 'answered', 'completed'],
            'record' => $agent->enable_recording ? true : false,
            'timeout' => 30,
        ];

        // If recording is enabled, tell the provider where to POST the
        // recording URL once it's ready. Without this it records but
        // never tells us the URL.
        if ($agent->enable_recording) {
            $callParams['recordingStatusCallback'] = $this->callbackRoute($provider, 'ai-agent.call.recording');
            $callParams['recordingStatusCallbackMethod'] = 'POST';
            $callParams['recordingStatusCallbackEvent'] = ['completed'];
        }

        return $callParams;
    }

    public function initiate(Request $request, TelephonyManager $telephony)
    {
        $validator = Validator::make($request->all(), [
            'ai_agent_id' => 'required|exists:ai_agents,id',
            'to_number' => 'required|string',
            'contact_id' => 'nullable|exists:contacts,id',
            'campaign_id' => 'nullable|exists:campaigns,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $agent = AiAgent::where('user_id', auth()->id())->findOrFail($request->ai_agent_id);

        if (! $agent->active) {
            return response()->json([
                'message' => 'AI Agent is not active',
            ], 400);
        }

        if ($agent->type === 'inbound') {
            return response()->json([
                'message' => 'AI Agent is not configured for outbound calls',
            ], 400);
        }

        try {
            $globalConfig = TwilioGlobalConfig::active();
            if (! $globalConfig) {
                return response()->json([
                    'message' => 'Twilio is not configured. Please configure Twilio in Settings.',
                ], 500);
            }

            $callParams = $this->outboundCallParams($agent, $telephony->provider());
            $webhookUrl = $callParams['url'];

            // Initialize Twilio Client
            $accountSid = $globalConfig->account_sid;
            $authToken = $globalConfig->getDecryptedAuthToken();
            $twilioClient = new \Twilio\Rest\Client($accountSid, $authToken);

            \Log::info('Initiating AI Agent outbound call', [
                'agent_id' => $agent->id,
                'to' => $request->to_number,
                'from' => $agent->phone_number,
                'webhook' => $webhookUrl,
            ]);

            $twilioCall = $twilioClient->calls->create(
                $request->to_number, // To
                $agent->phone_number, // From (AI Agent's Twilio number)
                $callParams
            );

            // Create call record with actual Twilio SID
            $call = AiAgentCall::create([
                'ai_agent_id' => $agent->id,
                'contact_id' => $request->contact_id,
                'campaign_id' => $request->campaign_id,
                'call_sid' => $twilioCall->sid,
                'direction' => 'outbound',
                'from_number' => $agent->phone_number,
                'to_number' => $request->to_number,
                'status' => 'initiated',
                'started_at' => now(),
            ]);

            \Log::info('AI Agent call initiated successfully', [
                'call_id' => $call->id,
                'twilio_sid' => $twilioCall->sid,
                'status' => $twilioCall->status,
            ]);

            return response()->json([
                'message' => 'Call initiated successfully',
                'call' => $call,
                'twilio_status' => $twilioCall->status,
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Failed to initiate AI Agent call', [
                'error' => $e->getMessage(),
                'agent_id' => $agent->id,
                'to' => $request->to_number,
            ]);

            return response()->json([
                'message' => 'Failed to initiate call: '.$e->getMessage(),
            ], 500);
        }
    }

    public function stop($id)
    {
        $call = AiAgentCall::whereHas('agent', function ($q) {
            $q->where('user_id', auth()->id());
        })->findOrFail($id);

        if (! in_array($call->status, ['initiated', 'ringing', 'in-progress'])) {
            return response()->json([
                'message' => 'Call is not active',
            ], 400);
        }

        try {
            $globalConfig = TwilioGlobalConfig::active();
            if (! $globalConfig) {
                return response()->json([
                    'message' => 'Twilio is not configured. Please configure Twilio in Settings.',
                ], 500);
            }

            // Initialize Twilio Client
            $accountSid = $globalConfig->account_sid;
            $authToken = $globalConfig->getDecryptedAuthToken();
            $twilioClient = new \Twilio\Rest\Client($accountSid, $authToken);

            // End the call
            $twilioCall = $twilioClient->calls($call->call_sid)->update([
                'status' => 'completed',
            ]);

            \Log::info('AI Agent call stopped', [
                'call_id' => $call->id,
                'call_sid' => $call->call_sid,
            ]);

            $this->stateManager->handleCallCompleted($call, 'user_stopped');

            return response()->json([
                'message' => 'Call stopped successfully',
                'call' => $call->fresh(),
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to stop AI Agent call', [
                'error' => $e->getMessage(),
                'call_id' => $call->id,
            ]);

            return response()->json([
                'message' => 'Failed to stop call: '.$e->getMessage(),
            ], 500);
        }
    }

    public function sync($id)
    {
        $call = $this->visibleCalls()->with(['agent', 'contact', 'campaign'])->findOrFail($id);

        try {
            // Get active Twilio configuration
            $globalConfig = TwilioGlobalConfig::active();

            if (! $globalConfig) {
                return response()->json([
                    'message' => 'Twilio is not configured. Please configure Twilio in Settings.',
                ], 500);
            }

            // Initialize Twilio service with global credentials
            $credential = new TwilioCredential([
                'account_sid' => $globalConfig->account_sid,
                'auth_token' => $globalConfig->getDecryptedAuthToken(),
            ]);
            $this->twilioService->initialize($credential);

            // Fetch real-time call details from Twilio
            $twilioData = $this->twilioService->fetchCallDetails($call->call_sid);

            if ($twilioData) {
                // Update call with real-time Twilio data
                $twilioPrice = isset($twilioData['price']) && $twilioData['price'] ? abs((float) $twilioData['price']) : null;

                $call->update([
                    'status' => $this->mapTwilioStatus($twilioData['status']),
                    'duration' => $twilioData['duration'] ?? $call->duration,
                    'ended_at' => $twilioData['end_time'] ?? $call->ended_at,
                    'recording_url' => $twilioData['recording_url'] ?? $call->recording_url,
                ]);

                // Update Twilio cost if available
                if ($twilioPrice !== null) {
                    $call->updateCostBreakdown(twilioCost: $twilioPrice);
                }

                // Refresh the model to get updated data
                $call->refresh();

                \Log::info('Call synced with Twilio', [
                    'call_id' => $call->id,
                    'call_sid' => $call->call_sid,
                    'status' => $call->status,
                    'duration' => $call->duration,
                ]);
            }

            return response()->json([
                'message' => 'Call synced successfully',
                'call' => $call->load('conversations'),
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to sync call with Twilio', [
                'error' => $e->getMessage(),
                'call_id' => $call->id,
                'call_sid' => $call->call_sid,
            ]);

            return response()->json([
                'message' => 'Failed to sync call: '.$e->getMessage(),
            ], 500);
        }
    }

    public function transcript($id)
    {
        $call = $this->visibleCalls()->with('conversations')->findOrFail($id);
        $transcript = $call->getTranscript();

        return response()->json([
            'call_sid' => $call->call_sid,
            'transcript' => $transcript,
            'conversations' => $call->conversations,
        ]);
    }

    public function stats(Request $request)
    {
        $query = $this->visibleCalls();

        // Filter by agent
        if ($request->has('ai_agent_id')) {
            $query->where('ai_agent_id', $request->ai_agent_id);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('started_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('started_at', '<=', $request->to_date);
        }

        $stats = [
            'total_calls' => $query->count(),
            'completed_calls' => (clone $query)->where('status', 'completed')->count(),
            'active_calls' => (clone $query)->whereIn('status', ['initiated', 'ringing', 'in-progress'])->count(),
            'failed_calls' => (clone $query)->where('status', 'failed')->count(),
            'average_duration' => (clone $query)->where('status', 'completed')->avg('duration'),
            'total_cost' => (clone $query)->sum('cost_estimate'),
            'transferred_calls' => (clone $query)->where('transferred', true)->count(),
        ];

        return response()->json($stats);
    }

    public function export(Request $request)
    {
        $query = $this->visibleCalls();

        // Filter by agent
        if ($request->has('ai_agent_id')) {
            $query->where('ai_agent_id', $request->ai_agent_id);
        }

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by direction
        if ($request->has('direction') && $request->direction !== 'all') {
            $query->where('direction', $request->direction);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('started_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('started_at', '<=', $request->to_date);
        }

        $calls = $query->with(['agent', 'contact', 'campaign'])
            ->orderBy('started_at', 'desc')
            ->limit(10000) // Limit to prevent memory issues
            ->get();

        $filename = 'ai-agent-calls-'.now()->format('Y-m-d-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($calls) {
            $file = fopen('php://output', 'w');

            // CSV Headers
            fputcsv($file, [
                'ID',
                'AI Agent',
                'Direction',
                'From Number',
                'To Number',
                'Contact Name',
                'Campaign',
                'Status',
                'Duration (seconds)',
                'Turn Count',
                'Cost Estimate',
                'Transferred',
                'Recording URL',
                'Started At',
                'Ended At',
                'Created At',
            ]);

            // CSV Data
            foreach ($calls as $call) {
                fputcsv($file, [
                    $call->id,
                    $call->agent?->name ?? 'N/A',
                    $call->direction,
                    $call->from_number,
                    $call->to_number,
                    $call->contact?->name ?? 'N/A',
                    $call->campaign?->name ?? 'N/A',
                    $call->status,
                    $call->duration ?? 0,
                    $call->turn_count ?? 0,
                    $call->cost_estimate ?? 0,
                    $call->transferred ? 'Yes' : 'No',
                    $call->recording_url ?? '',
                    $call->started_at?->format('Y-m-d H:i:s') ?? '',
                    $call->ended_at?->format('Y-m-d H:i:s') ?? '',
                    $call->created_at?->format('Y-m-d H:i:s') ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Handle a call status callback from Twilio or Plivo.
     *
     * This is the only writer of AiAgentCall's status, duration, ended_at
     * and the provider's own cost, so an AI-agent call that never reaches
     * it never closes: the row stays 'in-progress' for ever, with no
     * duration and no provider cost, and ProfitAnalyticsService then
     * reports AI-agent margin as higher than it is.
     *
     * The provider is read from the route's own 'provider' parameter
     * (webhooks/twilio/* never sets one, so it defaults to 'twilio';
     * webhooks/plivo/* sets it via ->defaults()) rather than from the
     * installation's lock — a webhook in flight must be read as whatever
     * actually sent it, even if the lock changed in between. It is
     * deliberately not a typed scalar parameter; see
     * CallController::webhookStatus()'s docblock for why.
     *
     * The Twilio branch below is byte-identical to this method's
     * pre-existing behaviour, its own in-handler signature check included.
     * Plivo's request is already verified by the plivo.signature
     * middleware on its route before it ever gets here, and its payload is
     * normalised through PlivoWebhookNormalizer into the same vocabulary
     * the Twilio branch produces.
     */
    public function handleCallStatus(Request $request, TelephonyManager $telephony)
    {
        try {
            $provider = $request->route('provider', 'twilio');

            if ($provider === 'twilio') {
                // Validate Twilio signature (skip in local dev)
                if (! app()->environment('local') || ! config('app.debug')) {
                    $globalConfig = TwilioGlobalConfig::active();
                    if ($globalConfig) {
                        $validator = new RequestValidator($globalConfig->getDecryptedAuthToken());
                        $signature = $request->header('X-Twilio-Signature', '');
                        $url = $request->fullUrl();
                        $params = $request->all();

                        if (! $validator->validate($signature, $url, $params)) {
                            \Log::warning('Invalid Twilio signature on call status callback');

                            return response('Forbidden', 403);
                        }
                    }
                }

                $callSid = $request->input('CallSid');
                $callStatus = $request->input('CallStatus');
                $duration = $request->input('CallDuration');
                $providerPrice = $request->input('Price');
            } else {
                $normalized = $telephony
                    ->webhookNormalizer(TelephonyProvider::tryFrom($provider) ?? TelephonyProvider::TWILIO)
                    ->normalizeStatusCallback($request->all());
                $callSid = $normalized['call_id'] ?: null;
                $callStatus = $normalized['status'];
                $duration = $normalized['duration'];
                $providerPrice = $normalized['price'];
            }

            \Log::info('Received call status callback', [
                'provider' => $provider,
                'call_sid' => $callSid,
                'status' => $callStatus,
                'duration' => $duration,
                'all_data' => $request->all(),
            ]);

            // Find the call by the provider's own call identifier
            $call = AiAgentCall::where('call_sid', $callSid)->first();

            if (! $call) {
                \Log::warning('Call not found for status callback', [
                    'call_sid' => $callSid,
                ]);

                return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200)
                    ->header('Content-Type', 'text/xml');
            }

            // Update call status. Twilio's branch writes whatever CallStatus
            // arrived, exactly as it always has. Plivo's normaliser answers
            // with the empty string for a status it has no mapping for, and
            // writing that would blank a live call's status — so there the
            // key is omitted and the row keeps the status it has, the same
            // rule CallController::webhookStatus() already follows.
            $updateData = [];

            if ($provider === 'twilio' || $callStatus !== '') {
                $updateData['status'] = $callStatus;
            }

            if ($duration !== null) {
                $updateData['duration'] = (int) $duration;
            }

            if (in_array($callStatus, ['completed', 'failed', 'busy', 'no-answer', 'canceled'])) {
                $updateData['ended_at'] = now();
            }

            if ($updateData !== []) {
                $call->update($updateData);
            }

            // Store the provider's actual price when provided (so we show the
            // true carrier cost, not an estimate). The column is still named
            // twilio_cost for both providers — renaming it is a schema change
            // for its own change, not this one.
            if ($providerPrice !== null && $providerPrice !== '') {
                $call->updateCostBreakdown(twilioCost: abs((float) $providerPrice));
            }

            \Log::info('Call status updated', [
                'call_id' => $call->id,
                'status' => $callStatus,
            ]);

            return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200)
                ->header('Content-Type', 'text/xml');

        } catch (\Exception $e) {
            \Log::error('Failed to handle call status callback', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200)
                ->header('Content-Type', 'text/xml');
        }
    }

    /**
     * Stream a call recording to the browser, fetching it from Twilio with
     * our account credentials. Twilio recordings are not publicly accessible
     * by default — the `<audio src>` tag can't provide auth headers, so we
     * proxy through here.
     *
     * Uses a streamed response (no full-file buffering in PHP memory) so
     * large recordings don't hit memory/time limits.
     */
    public function recording($id, Request $request)
    {
        $call = AiAgentCall::findOrFail($id);

        // Authorize: only the call's agent owner (or admin) can access the recording
        $agent = AiAgent::findOrFail($call->ai_agent_id);
        $user = $request->user();
        if (! $user || ($user->id !== $agent->user_id && ! $user->hasRole('admin'))) {
            abort(403, 'Not authorized to access this recording.');
        }

        if (empty($call->recording_url)) {
            abort(404, 'Recording not available yet. Twilio may still be processing it.');
        }

        $globalConfig = TwilioGlobalConfig::active();
        if (! $globalConfig) {
            \Log::error('Recording proxy: no active TwilioGlobalConfig', ['call_id' => $id]);

            return response()->json([
                'error' => 'Twilio is not configured on this server.',
            ], 503);
        }

        // Ensure MP3 extension so Twilio returns audio/mpeg
        $twilioUrl = $call->recording_url;
        if (! str_ends_with($twilioUrl, '.mp3') && ! str_ends_with($twilioUrl, '.wav')) {
            $twilioUrl = rtrim($twilioUrl, '/').'.mp3';
        }

        $accountSid = $globalConfig->account_sid;
        $authToken = $globalConfig->getDecryptedAuthToken();
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';
        $filename = 'call-'.$id.'.mp3';

        try {
            // Use Guzzle directly with streaming so we don't buffer the full
            // MP3 in PHP memory and don't hit request timeouts.
            $guzzle = new Client([
                'timeout' => 0,         // No PHP timeout — let the HTTP client stream
                'read_timeout' => 60,
                'stream' => true,
            ]);

            $twilioResponse = $guzzle->request('GET', $twilioUrl, [
                'auth' => [$accountSid, $authToken],
                'stream' => true,
                'http_errors' => false,
            ]);

            $status = $twilioResponse->getStatusCode();
            if ($status < 200 || $status >= 300) {
                \Log::error('Twilio recording fetch failed', [
                    'call_id' => $id,
                    'status' => $status,
                    'url' => $twilioUrl,
                ]);
                abort(502, 'Could not fetch recording from Twilio (status '.$status.').');
            }

            $stream = $twilioResponse->getBody();
            $contentLength = $twilioResponse->getHeaderLine('Content-Length');

            return response()->stream(function () use ($stream) {
                while (! $stream->eof()) {
                    echo $stream->read(8192);
                    @ob_flush();
                    @flush();
                }
            }, 200, array_filter([
                'Content-Type' => 'audio/mpeg',
                'Content-Length' => $contentLength ?: null,
                'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'private, max-age=3600',
                'X-Accel-Buffering' => 'no', // Disable nginx buffering
            ]));
        } catch (\Throwable $e) {
            \Log::error('Recording proxy error', [
                'call_id' => $id,
                'error' => $e->getMessage(),
            ]);
            abort(502, 'Could not fetch recording: '.$e->getMessage());
        }
    }

    /**
     * Webhook: Twilio or Plivo posts here when a call recording is
     * completed. Stores the recording URL + duration on the AiAgentCall
     * record so it can be played back from the UI — the only writer of
     * AiAgentCall.recording_url.
     *
     * See handleCallStatus() above for how $provider is read and why the
     * Twilio branch is left exactly as it was.
     */
    public function recordingCallback(Request $request, TelephonyManager $telephony)
    {
        $provider = $request->route('provider', 'twilio');

        if ($provider === 'twilio') {
            $callSid = $request->input('CallSid');
            $recordingSid = $request->input('RecordingSid');
            $recordingUrl = $request->input('RecordingUrl');
            $recordingDuration = $request->input('RecordingDuration');
        } else {
            $normalized = $telephony
                ->webhookNormalizer(TelephonyProvider::tryFrom($provider) ?? TelephonyProvider::TWILIO)
                ->normalizeRecordingCallback($request->all());
            $callSid = $normalized['call_id'] ?: null;
            $recordingSid = $normalized['recording_id'] ?: null;
            $recordingUrl = $normalized['recording_url'];
            $recordingDuration = $normalized['duration'];
        }

        \Log::info('AI Agent recording callback received', [
            'provider' => $provider,
            'call_sid' => $callSid,
            'recording_sid' => $recordingSid,
            'recording_url' => $recordingUrl,
            'duration' => $recordingDuration,
        ]);

        if (! $callSid || ! $recordingUrl) {
            return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200)
                ->header('Content-Type', 'text/xml');
        }

        $call = AiAgentCall::where('call_sid', $callSid)->first();

        if (! $call) {
            \Log::warning('Recording webhook: AiAgentCall not found', ['call_sid' => $callSid]);

            return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200)
                ->header('Content-Type', 'text/xml');
        }

        // Twilio's RecordingUrl does not include a file extension — appending
        // .mp3 serves a playable audio stream. Plivo's RecordUrl is already a
        // complete, playable URL, so appending there would produce
        // '...mp3.mp3' and a dead player. We also store it in metadata
        // alongside the raw SID for reference.
        $playableUrl = $provider === 'twilio'
            ? rtrim($recordingUrl, '/').'.mp3'
            : $recordingUrl;

        $metadata = is_array($call->metadata) ? $call->metadata : [];
        $metadata['recording_sid'] = $recordingSid;
        $metadata['recording_duration'] = $recordingDuration;

        $call->update([
            'recording_url' => $playableUrl,
            'metadata' => $metadata,
        ]);

        return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200)
            ->header('Content-Type', 'text/xml');
    }
}
