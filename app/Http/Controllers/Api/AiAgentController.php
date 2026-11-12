<?php

namespace App\Http\Controllers\Api;

use App\Enums\TelephonyProvider;
use App\Http\Controllers\Controller;
use App\Models\AiAgent;
use App\Models\PhoneNumber;
use App\Models\TwilioGlobalConfig;
use App\Services\AiAgent\ConversationEngine;
use App\Services\AiAgent\DeepgramService;
use App\Services\Telephony\TelephonyManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Exists;
use Twilio\Rest\Client;

class AiAgentController extends Controller
{
    /**
     * A knowledge base the signed-in user may give an agent: their own, or
     * any for an admin.
     */
    private function ownKnowledgeBase(): Exists
    {
        $rule = new Exists('knowledge_bases', 'id');

        return auth()->user()->isAdmin() ? $rule : $rule->where('user_id', auth()->id());
    }

    /**
     * A number the signed-in user may give an agent: one they own, or any for
     * an admin. Saving an agent points the number's calls at it, so without
     * this one customer could take over another's inbound line. The number an
     * agent already has may always be kept — the edit form sends it back with
     * every save.
     */
    private function ownNumber(?string $current = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($current): void {
            if ($value === null || $value === '' || $value === $current || auth()->user()->isAdmin()) {
                return;
            }

            if (! PhoneNumber::where('number', $value)->where('user_id', auth()->id())->exists()) {
                $fail('Choose one of your own phone numbers.');
            }
        };
    }

    public function index(Request $request)
    {
        $query = AiAgent::query()
            ->where('user_id', auth()->id());

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by active status
        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        $agents = $query->with(['knowledgeBase'])
            ->withCount(['calls', 'activeCalls', 'completedCalls'])
            ->paginate($request->input('per_page', 15));

        return response()->json($agents);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone_number' => ['nullable', 'string', 'unique:ai_agents,phone_number', $this->ownNumber()],
            'description' => 'nullable|string',
            'type' => 'required|in:inbound,outbound,both',
            'system_prompt' => 'required|string',
            'first_message' => 'nullable|string|max:500',
            'goodbye_message' => 'nullable|string|max:500',
            'knowledge_base' => 'nullable|string',
            'knowledge_base_id' => ['nullable', 'integer', $this->ownKnowledgeBase()],
            'model' => 'required|string',
            'voice' => 'required|string',
            'max_tokens' => 'nullable|integer|min:50|max:4000',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'active' => 'nullable|boolean',
            'enable_transfer' => 'nullable|boolean',
            'transfer_number' => 'nullable|string|required_if:enable_transfer,true',
            'enable_recording' => 'nullable|boolean',
            'max_duration' => 'nullable|integer|min:60|max:3600',
            'silence_timeout' => 'nullable|integer|min:5|max:60',
            'response_timeout' => 'nullable|integer|min:5|max:30',
            'trigger_keywords' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $agent = AiAgent::create(array_merge($validator->validated(), [
            'user_id' => auth()->id(),
        ]));

        return response()->json([
            'message' => 'AI Agent created successfully',
            'agent' => $agent,
        ], 201);
    }

    public function show($id)
    {
        $agent = AiAgent::where('user_id', auth()->id())
            ->with(['knowledgeBase'])
            ->withCount(['calls', 'activeCalls', 'completedCalls'])
            ->findOrFail($id);

        $stats = $agent->getStats();

        // The knowledgeBase relation serialises to the key "knowledge_base",
        // shadowing the inline knowledge_base column — clients always saw the
        // related record (usually null) instead of the agent's own text.
        // Expose the relation under its own key and keep the column intact.
        $payload = $agent->toArray();
        $payload['knowledge_base'] = $agent->getAttribute('knowledge_base');
        $payload['knowledge_base_record'] = $agent->knowledgeBase;

        return response()->json([
            'data' => $payload,
            'stats' => $stats,
        ]);
    }

    public function calls($id)
    {
        $agent = AiAgent::where('user_id', auth()->id())->findOrFail($id);

        $calls = $agent->calls()
            ->with(['conversations'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $calls,
        ]);
    }

    public function update(Request $request, $id)
    {
        $agent = AiAgent::where('user_id', auth()->id())->findOrFail($id);

        // Clean up phone_number - treat empty string as null
        if ($request->has('phone_number') && $request->phone_number === '') {
            $request->merge(['phone_number' => null]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'phone_number' => ['nullable', 'string', $this->ownNumber($agent->phone_number)],
            'description' => 'nullable|string',
            'type' => 'sometimes|required|in:inbound,outbound,both',
            'system_prompt' => 'sometimes|required|string',
            'first_message' => 'nullable|string|max:500',
            'goodbye_message' => 'nullable|string|max:500',
            'knowledge_base' => 'nullable|string',
            'knowledge_base_id' => ['nullable', 'integer', $this->ownKnowledgeBase()],
            'model' => 'sometimes|required|string',
            'voice' => 'sometimes|required|string',
            'max_tokens' => 'nullable|integer|min:50|max:4000',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'active' => 'nullable|boolean',
            'enable_transfer' => 'nullable|boolean',
            'transfer_number' => 'nullable|string',
            'enable_recording' => 'nullable|boolean',
            'max_duration' => 'nullable|integer|min:60|max:3600',
            'silence_timeout' => 'nullable|integer|min:5|max:60',
            'response_timeout' => 'nullable|integer|min:5|max:30',
            'trigger_keywords' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            \Log::error('AI Agent validation failed', [
                'agent_id' => $id,
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Force assignment: If assigning a phone number, remove it from other agents
        if ($request->filled('phone_number')) {
            AiAgent::where('phone_number', $request->phone_number)
                ->where('id', '!=', $id)
                ->update(['phone_number' => null]);

            \Log::info('Phone number reassigned to AI agent', [
                'agent_id' => $id,
                'phone_number' => $request->phone_number,
            ]);
        }

        $agent->update($validator->validated());

        // Auto-configure the number's webhooks if one is assigned.
        //
        // The same work the configure-webhooks route offers, which is
        // gated on this key — and this caller is not, so every save on a
        // provider that has no per-number URLs to write ran it anyway,
        // took the refusal, and logged it. The gate names the feature; it
        // is not re-derived here.
        if ($agent->phone_number && ! app(TelephonyManager::class)->provider()->hides('ai_agent_number_webhooks')) {
            try {
                $this->autoConfigurePhoneNumber($agent);
            } catch (\Exception $e) {
                \Log::warning('Failed to auto-configure Twilio webhooks', [
                    'agent_id' => $agent->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'message' => 'AI Agent updated successfully',
            'agent' => $agent,
        ]);
    }

    public function destroy($id)
    {
        $agent = AiAgent::where('user_id', auth()->id())->findOrFail($id);

        // Check if agent has active calls
        if ($agent->activeCalls()->exists()) {
            return response()->json([
                'message' => 'Cannot delete agent with active calls. Please wait for calls to complete.',
                'active_calls' => $agent->activeCalls()->count(),
            ], 400);
        }

        // Check if agent has call history
        $totalCalls = $agent->calls()->count();
        if ($totalCalls > 0) {
            return response()->json([
                'message' => 'Cannot delete agent with call history. This would permanently delete '.$totalCalls.' call records. Please use archive instead.',
                'total_calls' => $totalCalls,
                'suggestion' => 'Use the archive feature to preserve call history while deactivating the agent.',
            ], 400);
        }

        // Check if agent is used in campaigns
        $campaigns = $agent->campaigns;
        if ($campaigns->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete agent used in '.$campaigns->count().' campaign(s). Please unlink campaigns first.',
                'campaigns' => $campaigns->pluck('name'),
            ], 400);
        }

        // Safe to delete (no data loss)
        $agent->delete();

        return response()->json([
            'message' => 'AI Agent deleted successfully',
        ]);
    }

    /**
     * Archive an AI agent (soft delete + deactivate)
     */
    public function archive($id)
    {
        $agent = AiAgent::where('user_id', auth()->id())->findOrFail($id);

        // Check if agent has active calls
        if ($agent->activeCalls()->exists()) {
            return response()->json([
                'message' => 'Cannot archive agent with active calls. Please wait for calls to complete.',
                'active_calls' => $agent->activeCalls()->count(),
            ], 400);
        }

        // Deactivate agent
        $agent->update(['active' => false]);

        // Unlink from campaigns (preserve campaign but remove agent)
        $campaignsAffected = $agent->campaigns()->count();
        $agent->campaigns()->update(['ai_agent_id' => null]);

        // Soft delete
        $agent->delete();

        return response()->json([
            'message' => 'AI Agent archived successfully',
            'preserved_calls' => $agent->calls()->count(),
            'campaigns_unlinked' => $campaignsAffected,
        ]);
    }

    /**
     * Restore an archived agent
     */
    public function restore($id)
    {
        $agent = AiAgent::withTrashed()->where('user_id', auth()->id())->findOrFail($id);

        if (! $agent->trashed()) {
            return response()->json([
                'message' => 'Agent is not archived',
            ], 400);
        }

        $agent->restore();

        return response()->json([
            'message' => 'AI Agent restored successfully',
            'agent' => $agent,
        ]);
    }

    public function test($id)
    {
        $agent = AiAgent::where('user_id', auth()->id())->findOrFail($id);

        $conversationEngine = new ConversationEngine($agent);
        $deepgram = app(DeepgramService::class);

        $results = [
            'agent' => $agent->name,
            'tests' => [],
        ];

        // Test Deepgram
        try {
            $deepgramTest = $deepgram->test();
            $results['tests']['deepgram'] = [
                'status' => 'success',
                'message' => 'Deepgram API is accessible',
            ];
        } catch (\Exception $e) {
            $results['tests']['deepgram'] = [
                'status' => 'failed',
                'message' => $e->getMessage(),
            ];
        }

        // Test Text Generation (OpenRouter)
        try {
            $openrouterTest = $conversationEngine->test();
            $results['tests']['openrouter'] = [
                'status' => 'success',
                'message' => 'OpenRouter API is accessible',
            ];
        } catch (\Exception $e) {
            $results['tests']['openrouter'] = [
                'status' => 'failed',
                'message' => $e->getMessage(),
            ];
        }

        return response()->json($results);
    }

    public function availableModels()
    {
        return response()->json(config('ai-agent.openrouter_models', []));
    }

    public function availableVoices()
    {
        return response()->json(config('ai-agent.voices'));
    }

    public function availableTemplates()
    {
        return response()->json(config('ai-agent.templates', []));
    }

    /**
     * Configure Twilio webhooks for AI Agent
     */
    public function configureWebhooks($id)
    {
        $agent = AiAgent::where('user_id', auth()->id())->findOrFail($id);

        if (! $agent->phone_number) {
            return response()->json([
                'message' => 'AI Agent must have a phone number assigned',
            ], 400);
        }

        try {
            $result = $this->autoConfigurePhoneNumber($agent);

            return response()->json([
                'message' => 'Webhooks configured successfully',
                'webhook_url' => $result['webhook_url'],
                'phone_sid' => $result['phone_sid'],
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to configure Twilio webhooks', [
                'error' => $e->getMessage(),
                'agent_id' => $agent->id,
            ]);

            return response()->json([
                'message' => 'Failed to configure webhooks: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * The absolute URL an AI-agent number should report its call status
     * to, for the provider this installation is locked to.
     *
     * The AI-agent status webhook is the only writer of an AiAgentCall's
     * status, duration, ended-at and the provider's own cost, and it now
     * has a Plivo sibling — a number pointed at the other provider's URL
     * leaves every one of those fields unwritten for ever.
     *
     * Built from the route name rather than a literal path (the same
     * reason TwimlController::callbackRoute() exists), with the host
     * still taken from config('app.url') rather than route()'s own
     * absolute form, so a Twilio installation goes on configuring the
     * byte-identical URL it always has.
     */
    protected function statusCallbackRoute(): string
    {
        $name = app(TelephonyManager::class)->provider() === TelephonyProvider::PLIVO
            ? 'webhooks.plivo.ai-agent.call.status'
            : 'webhooks.twilio.ai-agent.call.status';

        return rtrim(config('app.url'), '/').route($name, [], false);
    }

    /**
     * The answer URL an AI-agent number should be pointed at, for the
     * provider this installation is locked to. twiml/* is signed for
     * Twilio and plivo-xml/* for Plivo, so the wrong one is rejected the
     * moment a call comes in.
     */
    protected function answerRoute(): string
    {
        $name = app(TelephonyManager::class)->provider() === TelephonyProvider::PLIVO
            ? 'plivo-xml.ai-agent.call'
            : 'twiml.ai-agent.call';

        return rtrim(config('app.url'), '/').route($name, [], false);
    }

    /**
     * The whole webhook configuration written onto an AI-agent number.
     *
     * A seam on purpose: autoConfigurePhoneNumber() below reaches the
     * provider's API on the next statement, so this is the last point at
     * which the wiring — which URL is handed to which parameter — can be
     * checked without a network call.
     *
     * @return array<string, string>
     */
    protected function numberWebhookConfig(): array
    {
        return [
            'voiceUrl' => $this->answerRoute(),
            'voiceMethod' => 'POST',
            'statusCallback' => $this->statusCallbackRoute(),
            'statusCallbackMethod' => 'POST',
        ];
    }

    /**
     * Auto-configure phone number voice webhooks for AI Agent
     */
    protected function autoConfigurePhoneNumber(AiAgent $agent)
    {
        // Get active Twilio configuration
        $globalConfig = TwilioGlobalConfig::active();

        if (! $globalConfig) {
            throw new \Exception('Twilio is not configured. Please configure Twilio in Settings.');
        }

        $accountSid = $globalConfig->account_sid;
        $authToken = $globalConfig->getDecryptedAuthToken();
        $twilioClient = new Client($accountSid, $authToken);

        $webhookConfig = $this->numberWebhookConfig();
        $webhookUrl = $webhookConfig['voiceUrl'];

        // Find the phone number in Twilio
        $phoneNumbers = $twilioClient->incomingPhoneNumbers->read([
            'phoneNumber' => $agent->phone_number,
        ]);

        if (empty($phoneNumbers)) {
            throw new \Exception('Phone number not found in Twilio account');
        }

        $phoneNumber = $phoneNumbers[0];

        // Update the phone number with AI Agent webhook
        $phoneNumber->update($webhookConfig);

        \Log::info('Twilio webhook auto-configured for AI Agent', [
            'agent_id' => $agent->id,
            'phone' => $agent->phone_number,
            'webhook' => $webhookUrl,
        ]);

        // Update agent record
        $agent->update([
            'twilio_auto_configured' => true,
            'twilio_webhook_sid' => $phoneNumber->sid,
        ]);

        return [
            'webhook_url' => $webhookUrl,
            'phone_sid' => $phoneNumber->sid,
        ];
    }

    /**
     * Test AI Agent configuration
     */
    public function testConfiguration($id)
    {
        $agent = AiAgent::where('user_id', auth()->id())->findOrFail($id);
        $isAdmin = auth()->user()->hasRole('admin');

        $results = [
            'agent' => $agent->name,
            'is_admin' => $isAdmin,
            'checks' => [],
        ];

        // Check AI service (OpenRouter) — hide provider name from non-admins
        $results['checks']['ai_service'] = $this->checkOpenRouter($isAdmin);

        // Check telephony configuration — hide provider details from non-admins
        $results['checks']['telephony'] = $this->checkTwilio($agent, $isAdmin);

        // Check webhook URL accessibility — hide internal URLs from non-admins
        $results['checks']['webhook'] = $this->checkWebhookUrl($isAdmin);

        // Overall status
        $results['status'] = collect($results['checks'])->every(fn ($check) => $check['status'] === 'success') ? 'success' : 'warning';

        return response()->json($results);
    }

    private function checkOpenRouter(bool $isAdmin = false)
    {
        try {
            $apiKey = config('openrouter.api_key');
            if (empty($apiKey)) {
                return [
                    'status' => 'error',
                    'message' => $isAdmin ? 'OpenRouter API key not configured' : 'AI service not configured',
                ];
            }

            return [
                'status' => 'success',
                'message' => $isAdmin ? 'OpenRouter API key configured' : 'AI service connected',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $isAdmin ? $e->getMessage() : 'AI service check failed',
            ];
        }
    }

    private function checkTwilio($agent, bool $isAdmin = false)
    {
        try {
            $globalConfig = TwilioGlobalConfig::active();

            if (! $globalConfig) {
                return [
                    'status' => 'error',
                    'message' => $isAdmin ? 'Twilio credentials not configured' : 'Phone system not configured',
                ];
            }

            $accountSid = $globalConfig->account_sid;
            $authToken = $globalConfig->getDecryptedAuthToken();

            $twilioClient = new Client($accountSid, $authToken);
            $account = $twilioClient->api->v2010->accounts($accountSid)->fetch();

            $result = [
                'status' => 'success',
                'message' => $isAdmin ? 'Twilio account connected' : 'Phone system connected',
            ];

            // Only expose account name to admins
            if ($isAdmin) {
                $result['account_name'] = $account->friendlyName;
            }

            // Check if agent has phone number configured
            if ($agent->phone_number) {
                $phoneNumbers = $twilioClient->incomingPhoneNumbers->read([
                    'phoneNumber' => $agent->phone_number,
                ]);

                if (! empty($phoneNumbers)) {
                    $result['phone_configured'] = true;
                    $result['webhook_configured'] = $agent->twilio_auto_configured;
                } else {
                    $result['phone_configured'] = false;
                    $result['status'] = 'warning';
                    $result['message'] .= $isAdmin ? ' - Phone number not found in Twilio' : ' - Phone number not verified';
                }
            }

            return $result;
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $isAdmin ? 'Twilio connection failed: '.$e->getMessage() : 'Phone system check failed',
            ];
        }
    }

    private function checkWebhookUrl(bool $isAdmin = false)
    {
        try {
            $webhookUrl = $this->answerRoute();

            // Check if URL is accessible (basic check)
            if (str_starts_with($webhookUrl, 'https://')) {
                $result = [
                    'status' => 'success',
                    'message' => $isAdmin ? 'Webhook URL configured' : 'Call routing configured',
                ];
            } else {
                $result = [
                    'status' => 'warning',
                    'message' => $isAdmin ? 'Webhook URL should use HTTPS for production' : 'Call routing needs attention',
                ];
            }

            // Only expose internal URL to admins
            if ($isAdmin) {
                $result['url'] = $webhookUrl;
            }

            return $result;
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $isAdmin ? $e->getMessage() : 'Call routing check failed',
            ];
        }
    }
}
