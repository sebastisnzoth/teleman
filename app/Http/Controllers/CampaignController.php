<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Campaigns\CreateCampaignAction;
use App\Actions\Campaigns\DeleteCampaignAction;
use App\Actions\Campaigns\LaunchCampaignAction;
use App\Actions\Campaigns\PauseCampaignAction;
use App\Actions\Campaigns\ResumeCampaignAction;
use App\Actions\Campaigns\UpdateCampaignAction;
use App\Actions\Campaigns\UploadCampaignContactsAction;
use App\Enums\TelephonyProvider;
use App\Models\AiAgent;
use App\Models\AudioFile;
use App\Models\Call;
use App\Models\Campaign;
use App\Models\CampaignTemplate;
use App\Models\ContactList;
use App\Models\PhoneNumber;
use App\Models\Role;
use App\Models\TelephonyProviderConfig;
use App\Models\User;
use App\Services\CreditService;
use App\Services\PhoneValidationService;
use App\Services\Telephony\TelephonyManager;
use App\Services\TwilioService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    /**
     * Display a listing of campaigns
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Campaign::class);

        $user = $request->user();
        $query = Campaign::query();

        // Admin sees all, Customer sees own, Agent sees only assigned
        if ($user->isAdmin()) {
            // Admin sees all campaigns
        } elseif ($user->isCustomer()) {
            $query->where('user_id', $user->id);
        } elseif ($user->isAgent()) {
            $query->where('assigned_agent_id', $user->id);
        }

        $campaigns = $query
            ->when($request->input('status'), function ($query, $status) {
                $query->where('status', $status);
            })
            ->withCount('campaignContacts')
            ->latest()
            ->paginate(15);

        return Inertia::render('campaigns/index', [
            'campaigns' => $campaigns,
            'filters' => [
                'status' => $request->input('status'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new campaign
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', Campaign::class);

        $audioFiles = AudioFile::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('filename')
            ->get(['id', 'filename']);

        // Get campaign templates (system + user's custom)
        $templates = CampaignTemplate::query()
            ->where(function ($q) use ($request) {
                $q->where('is_system_template', true)
                    ->orWhere('user_id', $request->user()->id);
            })
            ->orderByDesc('is_system_template')
            ->orderBy('category')
            ->orderByDesc('usage_count')
            ->get(['id', 'name', 'description', 'category', 'template_data', 'is_system_template']);

        // Get selected template if provided
        $selectedTemplate = null;
        if ($request->has('template')) {
            $selectedTemplate = CampaignTemplate::find($request->get('template'));
            if ($selectedTemplate) {
                $selectedTemplate->incrementUsage();
            }
        }

        // Get user's contact lists
        $contactLists = ContactList::where('user_id', $request->user()->id)
            ->withCount('contacts')
            ->orderBy('name')
            ->get(['id', 'name', 'contacts_count']);

        // Get agents the current user can assign
        $agents = $this->assignableAgentsQuery($request->user())
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        // Get user's phone numbers with AI agent information
        $phoneNumbers = $request->user()->phoneNumbers()
            ->provisioned()
            ->get()
            ->map(function ($phone) {
                // Resolved exactly the way store()/update() resolve it, so
                // the agent shown against a number is the agent the campaign
                // will actually get. Reading phone_numbers.ai_agent_id here
                // instead is what used to make this always empty: nothing in
                // the application writes that column.
                $agent = AiAgent::servingNumber($phone->number);

                return [
                    'id' => $phone->id,
                    'number' => $phone->number,
                    'formatted_number' => $phone->formatted_number,
                    'friendly_name' => $phone->friendly_name,
                    'ai_agent' => $agent ? [
                        'id' => $agent->id,
                        'name' => $agent->name,
                        'type' => $agent->type,
                        'is_active' => $agent->active,
                    ] : null,
                ];
            });

        return Inertia::render('campaigns/create', [
            'audioFiles' => $audioFiles,
            'templates' => $templates,
            'selectedTemplate' => $selectedTemplate,
            'contactLists' => $contactLists,
            'phoneNumbers' => $phoneNumbers,
            'agents' => $agents,
            'aiAgents' => $this->selectableAiAgentsQuery($request->user())
                ->orderBy('name')
                ->get(['id', 'name', 'type']),
        ]);
    }

    /**
     * Store a newly created campaign
     */
    public function store(
        Request $request,
        CreateCampaignAction $action
    ): RedirectResponse {
        $this->authorize('create', Campaign::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:text_to_speech,voice_to_voice'],
            'message' => ['nullable', 'string', 'max:250'],
            'audio_file_id' => ['nullable', 'exists:audio_files,id'],
            'language' => ['nullable', 'string', 'max:10'],
            'voice' => ['nullable', 'string', 'max:50'],
            'phone_number_id' => ['nullable', 'exists:phone_numbers,id'],
            'from_number' => ['nullable', 'string', 'max:20'],
            'enable_recording' => ['boolean'],
            'enable_dtmf' => ['boolean'],
            'max_concurrent_calls' => ['integer', 'min:1', 'max:50'],
            'retry_attempts' => ['integer', 'min:0', 'max:10'],
            'retry_delay_minutes' => ['integer', 'min:1', 'max:60'],
            'expected_variables' => ['nullable', 'array'],
            'expected_variables.*' => ['string', 'regex:/^[a-z_][a-z0-9_]*$/'],
            'campaign_variables' => ['nullable', 'array'],
            'campaign_variables.*' => ['string'],
            'launch_type' => ['nullable', 'in:draft,instant,scheduled'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'dtmf_actions' => ['nullable', 'array'],
            'dtmf_actions.*.key' => ['required', 'string', 'max:1'],
            'dtmf_actions.*.label' => ['required', 'string', 'max:255'],
            'dtmf_actions.*.action' => ['nullable', 'string', 'max:255'],
            'contact_list_ids' => ['nullable', 'array'],
            'contact_list_ids.*' => ['exists:contact_lists,id'],
            'assigned_agent_id' => $this->assignedAgentRules($request),
            'ai_agent_id' => $this->aiAgentRules($request),
        ]);

        // An explicit choice from the campaign screen wins. The screen sends
        // this field on every submission, so the fallback below now serves
        // callers that do not — the v1 API documents assigning the number's
        // agent automatically, and that stays true for it.
        $chosenAiAgent = $request->has('ai_agent_id');

        // Set from_number from the selected phone number
        if (! empty($validated['phone_number_id'])) {
            $phoneNumber = PhoneNumber::find($validated['phone_number_id']);
            if ($phoneNumber) {
                $validated['from_number'] = $phoneNumber->number;

                // Auto-assign AI agent if phone number has one assigned
                $aiAgent = $chosenAiAgent ? null : AiAgent::servingNumber($phoneNumber->number);

                if ($aiAgent) {
                    $validated['ai_agent_id'] = $aiAgent->id;
                    Log::info('Auto-assigned AI agent to campaign', [
                        'campaign_name' => $validated['name'],
                        'phone_number' => $phoneNumber->number,
                        'ai_agent_id' => $aiAgent->id,
                        'ai_agent_name' => $aiAgent->name,
                    ]);
                }
            }
        }

        // If no phone number is selected, force campaign to draft status
        if (empty($validated['phone_number_id'])) {
            $validated['launch_type'] = 'draft';
        }

        try {
            $campaign = $action->execute($request->user(), $validated);

            // Attach contact lists if provided
            if (! empty($validated['contact_list_ids'])) {
                $this->attachContactListsToCampaign($campaign, $validated['contact_list_ids']);
            }

            return redirect()->route('campaigns.show', $campaign->id)
                ->with('success', 'Campaign created successfully');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Attach contacts from contact lists to campaign
     */
    private function attachContactListsToCampaign($campaign, array $contactListIds): void
    {
        foreach ($contactListIds as $listId) {
            $contactList = ContactList::find($listId);
            if (! $contactList) {
                continue;
            }

            $contacts = $contactList->contacts;
            foreach ($contacts as $contact) {
                // Check if contact already in campaign
                $exists = $campaign->campaignContacts()
                    ->where('phone_number', $contact->phone_number)
                    ->exists();

                if (! $exists) {
                    $campaign->campaignContacts()->create([
                        'phone_number' => $contact->phone_number,
                        'first_name' => $contact->first_name,
                        'last_name' => $contact->last_name,
                        'email' => $contact->email,
                        'status' => 'pending',
                        'call_attempts' => 0,
                    ]);
                }
            }
        }

        // Update campaign total_contacts count
        $campaign->update([
            'total_contacts' => $campaign->campaignContacts()->count(),
        ]);
    }

    /**
     * Display the specified campaign
     */
    public function show(Request $request, Campaign $campaign): Response
    {
        $this->authorize('view', $campaign);

        $campaign->load([
            'campaignContacts' => function ($query) {
                $query->latest()->limit(10);
            },
            'calls' => function ($query) {
                $query->latest()->limit(10);
            },
            'audioFile',
            'aiAgent' => function ($query) {
                $query->select('id', 'name', 'model', 'voice', 'active');
            },
            'assignedAgent' => function ($query) {
                $query->select('id', 'name', 'email');
            },
        ]);

        $campaign->loadCount('campaignContacts', 'calls');

        // Get DTMF analytics if DTMF is enabled
        $dtmfStats = null;
        if ($campaign->enable_dtmf) {
            $totalResponses = $campaign->dtmfResponses()->count();

            $dtmfStats = [
                'total' => $totalResponses,
                'by_digit' => $campaign->dtmfResponses()
                    ->select('digits_pressed', DB::raw('count(*) as count'))
                    ->groupBy('digits_pressed')
                    ->get()
                    ->map(function ($item) use ($campaign, $totalResponses) {
                        $label = $this->getDtmfLabel($campaign, $item->digits_pressed);

                        return [
                            'digit' => $item->digits_pressed,
                            'label' => $label,
                            'count' => $item->count,
                            'percentage' => $totalResponses > 0 ? round(($item->count / $totalResponses) * 100, 1) : 0,
                        ];
                    }),
                'by_action' => $campaign->dtmfResponses()
                    ->whereNotNull('action_taken')
                    ->select('action_taken', DB::raw('count(*) as count'))
                    ->groupBy('action_taken')
                    ->get()
                    ->map(function ($item) use ($totalResponses) {
                        return [
                            'action' => $item->action_taken,
                            'count' => $item->count,
                            'percentage' => $totalResponses > 0 ? round(($item->count / $totalResponses) * 100, 1) : 0,
                        ];
                    }),
            ];
        }

        return Inertia::render('campaigns/show', [
            'campaign' => $campaign,
            'dtmfStats' => $dtmfStats,
        ]);
    }

    /**
     * Display the manual call queue for an assigned agent
     */
    public function queue(Request $request, Campaign $campaign): Response
    {
        $this->authorize('view', $campaign);

        $campaign->load([
            'assignedAgent' => function ($query) {
                $query->select('id', 'name', 'email');
            },
        ]);

        $campaign->loadCount('campaignContacts');

        // Load contacts ordered by status: pending first, failed second, completed last
        $contacts = $campaign->campaignContacts()
            ->orderByRaw("FIELD(status, 'pending', 'calling', 'failed', 'completed')")
            ->limit(200)
            ->get(['id', 'phone_number', 'first_name', 'last_name', 'status', 'call_attempts', 'contact_id']);

        $stats = [
            'total' => $campaign->campaign_contacts_count,
            'pending' => $campaign->campaignContacts()->where('status', 'pending')->count(),
            'calling' => $campaign->campaignContacts()->where('status', 'calling')->count(),
            'completed' => $campaign->campaignContacts()->where('status', 'completed')->count(),
            'failed' => $campaign->campaignContacts()->where('status', 'failed')->count(),
        ];

        return Inertia::render('campaigns/queue', [
            'campaign' => $campaign,
            'contacts' => $contacts,
            'stats' => $stats,
        ]);
    }

    /**
     * Helper method to get DTMF label from campaign actions
     */
    private function getDtmfLabel(Campaign $campaign, string $digit): string
    {
        $actions = $campaign->dtmf_actions ?? [];

        foreach ($actions as $action) {
            if (isset($action['key']) && $action['key'] === $digit) {
                return $action['label'] ?? $digit;
            }
        }

        return $digit;
    }

    /**
     * Show the form for editing the specified campaign
     */
    public function edit(Request $request, Campaign $campaign): Response
    {
        $this->authorize('update', $campaign);

        $audioFiles = AudioFile::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('filename')
            ->get(['id', 'filename']);

        // Get user's phone numbers with AI agent information
        $phoneNumbers = $request->user()->phoneNumbers()
            ->provisioned()
            ->get()
            ->map(function ($phone) {
                // Resolved exactly the way store()/update() resolve it, so
                // the agent shown against a number is the agent the campaign
                // will actually get. Reading phone_numbers.ai_agent_id here
                // instead is what used to make this always empty: nothing in
                // the application writes that column.
                $agent = AiAgent::servingNumber($phone->number);

                return [
                    'id' => $phone->id,
                    'number' => $phone->number,
                    'formatted_number' => $phone->formatted_number,
                    'friendly_name' => $phone->friendly_name,
                    'ai_agent' => $agent ? [
                        'id' => $agent->id,
                        'name' => $agent->name,
                        'type' => $agent->type,
                        'is_active' => $agent->active,
                    ] : null,
                ];
            });

        // Get agents the current user can assign
        $agents = $this->assignableAgentsQuery($request->user())
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('campaigns/edit', [
            'campaign' => $campaign,
            'audioFiles' => $audioFiles,
            'phoneNumbers' => $phoneNumbers,
            'agents' => $agents,
            'aiAgents' => $this->selectableAiAgentsQuery($request->user())
                ->orderBy('name')
                ->get(['id', 'name', 'type']),
        ]);
    }

    /**
     * Update the specified campaign
     */
    public function update(
        Request $request,
        Campaign $campaign,
        UpdateCampaignAction $action
    ): RedirectResponse {
        $this->authorize('update', $campaign);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'in:text_to_speech,voice_to_voice'],
            'message' => ['nullable', 'string', 'max:250'],
            'audio_file_id' => ['nullable', 'exists:audio_files,id'],
            'language' => ['nullable', 'string', 'max:10'],
            'voice' => ['nullable', 'string', 'max:50'],
            'phone_number_id' => ['nullable', 'exists:phone_numbers,id'],
            'from_number' => ['nullable', 'string', 'max:20'],
            'enable_recording' => ['boolean'],
            'enable_dtmf' => ['boolean'],
            'max_concurrent_calls' => ['integer', 'min:1', 'max:50'],
            'retry_attempts' => ['integer', 'min:0', 'max:10'],
            'retry_delay_minutes' => ['integer', 'min:1', 'max:60'],
            'scheduled_at' => ['nullable', 'date'],
            'expected_variables' => ['nullable', 'array'],
            'expected_variables.*' => ['string', 'regex:/^[a-z_][a-z0-9_]*$/'],
            'campaign_variables' => ['nullable', 'array'],
            'assigned_agent_id' => $this->assignedAgentRules($request),
            'ai_agent_id' => $this->aiAgentRules($request),
        ]);

        // Set from_number from the selected phone number
        if (isset($validated['phone_number_id']) && $validated['phone_number_id']) {
            $phoneNumber = PhoneNumber::find($validated['phone_number_id']);
            if ($phoneNumber) {
                $validated['from_number'] = $phoneNumber->number;

                // Auto-assign AI agent if phone number has one assigned.
                // Skipped when the request chose an agent itself: changing
                // the number must not silently overwrite a deliberate choice.
                if (! $request->has('ai_agent_id')) {
                    $aiAgent = AiAgent::servingNumber($phoneNumber->number);

                    if ($aiAgent) {
                        $validated['ai_agent_id'] = $aiAgent->id;
                    } else {
                        // Clear AI agent if phone number changed to one without agent
                        $validated['ai_agent_id'] = null;
                    }
                }
            }
        }

        try {
            $action->execute($campaign, $validated);

            return redirect()->route('campaigns.show', $campaign->id)
                ->with('success', 'Campaign updated successfully');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified campaign
     */
    public function destroy(
        Request $request,
        Campaign $campaign,
        DeleteCampaignAction $action
    ): RedirectResponse {
        $this->authorize('delete', $campaign);

        try {
            $action->execute($campaign);

            return redirect()->route('campaigns.index')
                ->with('success', 'Campaign deleted successfully');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Launch a campaign
     */
    public function launch(
        Request $request,
        Campaign $campaign,
        LaunchCampaignAction $action
    ): RedirectResponse {
        $this->authorize('execute', $campaign);

        try {
            $action->execute($campaign);

            return back()->with('success', 'Campaign launched successfully');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Pause a campaign
     */
    public function pause(
        Request $request,
        Campaign $campaign,
        PauseCampaignAction $action
    ): RedirectResponse {
        $this->authorize('execute', $campaign);

        try {
            $action->execute($campaign);

            return back()->with('success', 'Campaign paused successfully');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Resume a campaign
     */
    public function resume(
        Request $request,
        Campaign $campaign,
        ResumeCampaignAction $action
    ): RedirectResponse {
        $this->authorize('execute', $campaign);

        try {
            $action->execute($campaign);

            return back()->with('success', 'Campaign resumed successfully');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Upload contacts from CSV file
     */
    public function uploadContacts(
        Request $request,
        Campaign $campaign,
        UploadCampaignContactsAction $action
    ): RedirectResponse {
        $this->authorize('update', $campaign);

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120', // 5MB
        ]);

        try {
            $result = $action->execute($campaign, $request->file('file'));

            $message = sprintf(
                'Imported %d contacts. %d skipped.',
                $result['imported'],
                $result['skipped']
            );

            return back()->with([
                'success' => $message,
                'upload_result' => $result,
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Make on-demand call without queue (for testing)
     */
    public function makeOnDemandCall(
        Request $request,
        Campaign $campaign
    ): RedirectResponse {
        $this->authorize('update', $campaign);

        // Import necessary services
        $twilioService = app(TwilioService::class);
        $creditService = app(CreditService::class);
        $phoneValidator = app(PhoneValidationService::class);
        $telephony = app(TelephonyManager::class);

        // Whether the provider actually placed a real call — tracked
        // explicitly rather than inferred from $call->status; see
        // MakeCampaignCallJob::handle() for why status alone (which a
        // later, unrelated failure such as a credit-deduction race can
        // still leave at 'initiated') must not be what the catch block
        // below decides on.
        $providerCallPlaced = false;

        try {
            // Get next pending contact
            $contact = $campaign->campaignContacts()
                ->where('status', 'pending')
                ->orderBy('id')
                ->first();

            if (! $contact) {
                return back()->withErrors(['error' => 'No pending contacts found in this campaign']);
            }

            // Check user credits
            $user = $campaign->user;
            if (! $creditService->canAfford($user, 1.0)) {
                return back()->withErrors(['error' => 'Insufficient credits. Please top up your account.']);
            }

            // Validate phone number
            $phoneResult = $phoneValidator->sanitize($contact->phone_number);

            if (! $phoneResult['success']) {
                $contact->update([
                    'status' => 'failed',
                    'call_attempts' => $contact->call_attempts + 1,
                ]);

                return back()->withErrors(['error' => "Invalid phone number: {$phoneResult['error']}"]);
            }

            $sanitizedNumber = $phoneResult['formatted'];

            $provider = $telephony->provider();

            // Update contact to in_progress
            $contact->update([
                'status' => 'in_progress',
                'call_attempts' => $contact->call_attempts + 1,
                'last_call_at' => now(),
            ]);

            // Create call record
            $call = Call::create([
                'user_id' => $campaign->user_id,
                'campaign_id' => $campaign->id,
                'campaign_contact_id' => $contact->id,
                'provider' => $provider->value,
                'to_number' => $sanitizedNumber,
                'from_number' => $campaign->from_number,
                'status' => 'initiated',
                'direction' => 'outbound',
                'call_type' => 'campaign',
                'enable_recording' => $campaign->enable_recording,
                'enable_dtmf' => $campaign->enable_dtmf,
            ]);

            // Prepare answer URL based on campaign type. Route name
            // prefix follows the locked provider — 'twiml.' for Twilio
            // (byte-identical to the pre-existing route names),
            // 'plivo-xml.' for Plivo.
            $routePrefix = $provider === TelephonyProvider::PLIVO ? 'plivo-xml.' : 'twiml.';

            if ($campaign->type === 'text_to_speech') {
                $twimlUrl = route($routePrefix.'campaign.tts').'?call_id='.$call->id;
            } else {
                $twimlUrl = route($routePrefix.'campaign.voice').'?call_id='.$call->id;
            }

            Log::info('ON-DEMAND CALL - Starting', [
                'campaign_id' => $campaign->id,
                'contact_id' => $contact->id,
                'call_id' => $call->id,
                'provider' => $provider->value,
                'to' => $sanitizedNumber,
                'from' => $campaign->from_number,
                'twiml_url' => $twimlUrl,
            ]);

            // Resolve credentials for the locked provider. Twilio:
            // unchanged — the customer's own credential first, falling
            // back to the admin's global configuration, still resolved on
            // the injected (container-singleton) $twilioService so a test
            // overriding its initiateCall() still observes the real,
            // unmodified resolution. Plivo: this installation's one
            // configured account, read-only.
            if ($provider === TelephonyProvider::PLIVO) {
                $credentials = $this->resolvePlivoCredentials();
                $statusCallbackUrl = route('webhooks.plivo.call.status', ['call_id' => $call->id]);
            } else {
                $twilioService->initializeForUser($user);
                $credential = $twilioService->getCredential();
                $credentials = [
                    'account_sid' => $credential->account_sid,
                    'auth_token' => $credential->auth_token,
                ];
                $statusCallbackUrl = route('webhooks.twilio.call.status', ['call_id' => $call->id]);
            }

            // Make the call through the locked provider
            $callResult = $telephony->voice()->initialize($credentials)->makeCall(
                $sanitizedNumber,
                $campaign->from_number,
                $twimlUrl,
                [
                    'statusCallback' => $statusCallbackUrl,
                    'record' => $campaign->enable_recording ? 'record-from-answer' : null,
                ]
            );

            Log::info('ON-DEMAND CALL - Provider Result', [
                'call_id' => $call->id,
                'provider' => $provider->value,
                'result' => $callResult,
            ]);

            if (! $callResult['success']) {
                $errorMessage = $callResult['error'] ?? 'Unknown error';

                Log::error('ON-DEMAND CALL - Failed', [
                    'call_id' => $call->id,
                    'error' => $errorMessage,
                ]);

                $contact->update(['status' => 'failed']);
                $call->update([
                    'status' => 'failed',
                    'error_message' => $errorMessage,
                ]);

                return back()->withErrors(['error' => "Call failed: {$errorMessage}"]);
            }

            // The provider has now actually placed the call. From this
            // point on, any exception must not cause the catch block below
            // to terminalize the Call row — only a failure that happened
            // before the provider was ever contacted may do that.
            $providerCallPlaced = true;

            // Success - deduct credits
            $creditService->deductCredit(
                $user,
                1.0,
                "On-demand campaign call to {$sanitizedNumber}",
                [
                    'service_type' => 'call',
                    'reference_type' => 'Call',
                    'reference_id' => $call->id,
                    'metadata' => [
                        'campaign_id' => $campaign->id,
                        'contact_id' => $contact->id,
                        'on_demand' => true,
                    ],
                ]
            );

            // Update call with the provider's identifier, mirrored into
            // both twilio_call_sid (kept for compatibility) and
            // provider_call_sid. On Plivo this is only a RequestUUID —
            // the real CallUUID first arrives on the call's first status
            // webhook (UpdateCallStatusAction); written anyway so the row
            // is findable and hangupable in the meantime.
            $providerStatus = $callResult['status'];
            $mappedStatus = match ($providerStatus) {
                'queued', 'initiated' => 'initiated',
                'ringing' => 'ringing',
                'in-progress', 'answered' => 'in-progress',
                'completed' => 'completed',
                'busy' => 'busy',
                'no-answer', 'no_answer' => 'no-answer',
                'failed' => 'failed',
                'canceled', 'cancelled' => 'canceled',
                default => 'initiated',
            };

            $call->update([
                'twilio_call_sid' => $callResult['call_id'],
                'provider_call_sid' => $callResult['call_id'],
                // Permanent record of what makeCall() actually returned —
                // null on Twilio, the RequestUUID on Plivo — so
                // Call::shouldAdoptProviderCallId() can tell "still the
                // placeholder" from "confirmed" without looking at status.
                'provider_request_id' => $callResult['request_uuid'],
                'status' => $mappedStatus,
            ]);

            Log::info('ON-DEMAND CALL - Success', [
                'call_id' => $call->id,
                'provider' => $provider->value,
                'provider_call_sid' => $callResult['call_id'],
                'status' => $mappedStatus,
            ]);

            return back()->with('success', "Call initiated successfully! Call SID: {$callResult['call_id']}");

        } catch (\Exception $e) {
            Log::error('ON-DEMAND CALL - Exception', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // The Call record created above must not survive as a
            // permanently 'initiated' row when the provider was never
            // contacted at all — e.g. Twilio credential resolution
            // failing. $providerCallPlaced, not $call->status, is what
            // decides that: once the provider has actually placed a call,
            // a later, unrelated failure (a credit-deduction race under
            // concurrent dialling, a lock timeout) can still leave status
            // sitting at 'initiated', and that row must be left exactly as
            // the provider path gave it — it is the sync job reconciling
            // calls still in flight that finds it later, not this catch
            // block. Marking it 'failed' here would hide a real, possibly
            // billed call from every reconciliation path that matters.
            // Status is kept only as a secondary, belt-and-braces check.
            if (isset($call) && ! $providerCallPlaced && $call->status === 'initiated') {
                $call->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }

            // Reset contact if it was set to in_progress
            if (isset($contact)) {
                $contact->update(['status' => 'pending']);
            }

            return back()->withErrors(['error' => "Error: {$e->getMessage()}"]);
        }
    }

    /**
     * This installation's one configured Plivo account
     * (TelephonyProviderConfig, name 'plivo'), read-only — never
     * forProvider()'s firstOrCreate().
     *
     * @return array<string, string>
     */
    private function resolvePlivoCredentials(): array
    {
        $config = TelephonyProviderConfig::where('name', 'plivo')->first();
        $authId = $config?->credential('auth_id');
        $authToken = $config?->credential('auth_token');

        if (! $authId || ! $authToken) {
            throw new \Exception('Plivo is not configured for this platform. Ask an administrator to configure it in Settings → Telephony.');
        }

        return ['auth_id' => $authId, 'auth_token' => $authToken];
    }

    /**
     * The AI agents this user may put on a campaign.
     *
     * A campaign dials out, so an agent that only answers inbound calls
     * cannot serve one; `both` can. Scoped to the owner, because an agent
     * is a customer's own configuration — its name, voice and prompt — and
     * one customer must never be offered another's.
     *
     * A sub-agent works inside the customer's account, so they see the
     * customer's agents, which is the same rule their phone numbers follow.
     */
    private function selectableAiAgentsQuery(User $user): Builder
    {
        return AiAgent::query()
            ->where('user_id', $user->parent_user_id ?? $user->id)
            ->active()
            ->whereIn('type', ['outbound', 'both']);
    }

    /**
     * Validation for an explicitly chosen AI agent.
     *
     * `present` is deliberately absent: a request that says nothing about
     * an agent still gets the number's agent attached, which is what the
     * v1 API documents. Sending null is a choice — "no agent" — and is
     * honoured as one.
     *
     * @return array<int, mixed>
     */
    private function aiAgentRules(Request $request): array
    {
        $allowed = $this->selectableAiAgentsQuery($request->user())->pluck('id')->all();

        return ['nullable', 'integer', Rule::in($allowed)];
    }

    private function assignableAgentsQuery(User $user): Builder
    {
        $query = User::query()
            ->whereHas('roles', fn ($roleQuery) => $roleQuery->where('slug', Role::AGENT))
            ->where('status', 'active');

        if ($user->isCustomer()) {
            $query->where('parent_user_id', $user->id);
        } elseif (! $user->isAdmin()) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    private function assignedAgentRules(Request $request): array
    {
        return [
            'nullable',
            'integer',
            function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                if ($value === null || $value === '') {
                    return;
                }

                $agentExists = $this->assignableAgentsQuery($request->user())
                    ->whereKey($value)
                    ->exists();

                if (! $agentExists) {
                    $fail('The selected agent is invalid.');
                }
            },
        ];
    }
}
