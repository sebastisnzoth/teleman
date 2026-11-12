<?php

namespace App\Http\Controllers\SMS;

use App\Http\Controllers\Controller;
use App\Models\AiAgent;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\PhoneNumber;
use App\Models\SmsConversation;
use App\Models\SmsMessage;
use App\Services\SMS\SmsProviderFactory;
use App\Services\Telephony\TelephonyManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class SmsController extends Controller
{
    // Phone Numbers Management - Now uses existing assigned phone numbers
    public function index()
    {
        $user = Auth::user();

        // Get user's assigned phone numbers with SMS capability
        $phoneNumbers = PhoneNumber::where('user_id', $user->id)
            ->where('status', 'assigned')
            ->whereRaw("JSON_EXTRACT(capabilities, '$.sms') = true")
            ->with('aiAgent')
            ->withCount(['smsConversations as conversations_count'])
            ->get()
            ->map(function ($phone) {
                return [
                    'id' => $phone->id,
                    'number' => $phone->number ?? '',
                    'phone_number' => $phone->number ?? '',
                    'formatted_number' => $phone->formatted_number ?? '',
                    'friendly_name' => $phone->friendly_name,
                    'status' => $phone->status ?? 'assigned',
                    // The number's own provider, not a hardcoded 'twilio'.
                    // This is a white-label product and this string is shown
                    // to the customer — the same file already writes the real
                    // provider onto every message it sends. The column
                    // defaults to 'twilio', so every pre-existing row reads
                    // exactly as it did before.
                    'provider' => $phone->provider ?: 'twilio',
                    'is_active' => true,
                    'ai_agent' => $phone->aiAgent ? [
                        'id' => $phone->aiAgent->id,
                        'name' => $phone->aiAgent->name,
                    ] : null,
                    'conversations_count' => $phone->conversations_count ?? 0,
                    'has_agent' => $phone->ai_agent_id !== null,
                    'capabilities' => $phone->capabilities ?? ['sms' => true, 'mms' => false, 'voice' => false],
                ];
            });

        $aiAgents = AiAgent::where('active', true)->get(['id', 'name', 'description']);

        return Inertia::render('sms/index', [
            'phoneNumbers' => $phoneNumbers,
            'aiAgents' => $aiAgents,
        ]);
    }

    public function compose()
    {
        $user = Auth::user();

        // Get user's assigned phone numbers with SMS capability
        $phoneNumbers = PhoneNumber::where('user_id', $user->id)
            ->where('status', 'assigned')
            ->whereRaw("JSON_EXTRACT(capabilities, '$.sms') = true")
            ->get(['id', 'number', 'friendly_name']);

        // Get contact lists with contact counts
        $contactLists = ContactList::where('user_id', $user->id)
            ->withCount('contacts')
            ->get(['id', 'name', 'description'])
            ->map(function ($list) {
                return [
                    'id' => $list->id,
                    'name' => $list->name,
                    'description' => $list->description,
                    'contacts_count' => $list->contacts_count,
                ];
            });

        return Inertia::render('sms/compose', [
            'phoneNumbers' => $phoneNumbers,
            'contactLists' => $contactLists,
        ]);
    }

    public function send(Request $request, TelephonyManager $telephony)
    {
        try {
            $validated = $request->validate([
                'from_phone_id' => 'required|exists:phone_numbers,id',
                'recipients' => 'required|array|min:1',
                'recipients.*' => 'required|string',
                'message' => 'required|string|max:1600',
            ]);

            Log::info('SMS send request', [
                'from_phone_id' => $validated['from_phone_id'],
                'recipients_count' => count($validated['recipients']),
                'message_length' => strlen($validated['message']),
            ]);

            // Validate and filter phone numbers
            $validNumbers = [];
            $invalidNumbers = [];

            foreach ($validated['recipients'] as $phone) {
                $phone = trim($phone);

                // E.164 format validation
                if (preg_match('/^\+[1-9]\d{1,14}$/', $phone)) {
                    $validNumbers[] = $phone;
                } else {
                    $invalidNumbers[] = [
                        'phone' => $phone,
                        'reason' => 'Invalid phone number format (must be E.164: +1234567890)',
                    ];
                }
            }

            // If no valid numbers, return error
            if (empty($validNumbers)) {
                Log::warning('No valid phone numbers provided', [
                    'invalid_count' => count($invalidNumbers),
                ]);

                return back()->withErrors([
                    'recipients' => 'No valid phone numbers provided',
                ])->with('invalidNumbers', $invalidNumbers);
            }

            $phoneNumber = PhoneNumber::findOrFail($validated['from_phone_id']);

            // Verify ownership and SMS capability
            if ($phoneNumber->user_id !== Auth::id()) {
                return back()->withErrors(['message' => 'Unauthorized phone number access.']);
            }

            if (! ($phoneNumber->capabilities['sms'] ?? false)) {
                return back()->withErrors(['message' => 'This phone number does not support SMS.']);
            }

            $user = Auth::user();
            $smsProvider = SmsProviderFactory::makeForUser($user, $telephony);

            $results = [
                'success' => [],
                'failed' => [],
                'skipped' => $invalidNumbers,
            ];

            // Only send to valid numbers
            foreach ($validNumbers as $recipient) {
                try {
                    // Find or create conversation
                    $conversation = SmsConversation::firstOrCreate(
                        [
                            'phone_number_id' => $phoneNumber->id,
                            'contact_phone' => $recipient,
                        ],
                        [
                            'status' => 'active',
                            'last_message_at' => now(),
                        ]
                    );

                    // Send SMS via provider
                    $providerResponse = $smsProvider->send(
                        $phoneNumber->number,
                        $recipient,
                        $validated['message']
                    );

                    // Create message record. status/error_message read the
                    // provider's own success flag — sendMessage() below
                    // already does this correctly; send() used to hardcode
                    // 'sent' and report every recipient as a success
                    // regardless of what the provider actually returned,
                    // both providers report failure via this flag rather
                    // than throwing, so that silently recorded a failed
                    // send as delivered and told the customer it worked.
                    $message = SmsMessage::create([
                        'conversation_id' => $conversation->id,
                        'direction' => 'outbound',
                        'message_body' => $validated['message'],
                        'sender_phone' => $phoneNumber->number,
                        'receiver_phone' => $recipient,
                        'status' => $providerResponse['success'] ? 'sent' : 'failed',
                        'provider' => $telephony->provider()->value,
                        'provider_message_id' => $providerResponse['message_id'] ?? null,
                        // The real count the provider used to deliver this
                        // message, not an assumed 1 — this is what
                        // deductForSms() bills against once a delivery
                        // status confirms the send.
                        'num_segments' => $providerResponse['num_segments'] ?? 1,
                        'error_message' => $providerResponse['error'] ?? null,
                        'ai_generated' => false,
                    ]);

                    $conversation->update(['last_message_at' => now()]);

                    if ($providerResponse['success']) {
                        $results['success'][] = $recipient;
                    } else {
                        $results['failed'][] = [
                            'phone' => $recipient,
                            'reason' => $providerResponse['error'] ?? 'Unknown error',
                        ];
                    }
                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'phone' => $recipient,
                        'reason' => $e->getMessage(),
                    ];
                }
            }

            $successCount = count($results['success']);
            $failedCount = count($results['failed']);
            $skippedCount = count($results['skipped']);

            Log::info('SMS send completed', [
                'success' => $successCount,
                'failed' => $failedCount,
                'skipped' => $skippedCount,
            ]);

            // Return detailed results — merge skipped (invalid format) and failed (send errors)
            $allSkipped = array_merge($results['skipped'], $results['failed']);

            return back()->with([
                'smsResults' => [
                    'success' => $successCount,
                    'failed' => $failedCount,
                    'skipped' => $skippedCount + $failedCount,
                    'skippedNumbers' => $allSkipped,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('SMS send failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors([
                'message' => 'Failed to send SMS: '.$e->getMessage(),
            ]);
        }
    }

    public function getContactListNumbers(Request $request)
    {
        $validated = $request->validate([
            'contact_list_ids' => 'required|array',
            'contact_list_ids.*' => 'required|exists:contact_lists,id',
        ]);

        $user = Auth::user();

        // Get contacts from selected lists
        $contacts = Contact::whereIn('contact_list_id', $validated['contact_list_ids'])
            ->whereHas('contactList', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->whereNotNull('phone')
            ->distinct()
            ->pluck('phone')
            ->toArray();

        return response()->json([
            'phone_numbers' => $contacts,
        ]);
    }

    public function assignAgent(Request $request, PhoneNumber $phoneNumber)
    {
        // Verify ownership
        if ($phoneNumber->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'ai_agent_id' => 'nullable|exists:ai_agents,id',
        ]);

        $phoneNumber->update($validated);

        return redirect()->back()->with('success', 'AI agent assigned successfully');
    }

    // Conversations Management
    public function conversations(Request $request, ?PhoneNumber $phoneNumber = null)
    {
        $user = Auth::user();

        $query = SmsConversation::query()
            ->with(['phoneNumber', 'aiAgent', 'contact'])
            ->whereHas('phoneNumber', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });

        if ($phoneNumber) {
            $query->where('phone_number_id', $phoneNumber->id);
        }

        $conversations = $query
            ->withCount(['messages'])
            ->orderBy('last_message_at', 'desc')
            ->paginate(50);

        return Inertia::render('sms/conversations', [
            'conversations' => $conversations,
            'currentPhone' => $phoneNumber,
        ]);
    }

    public function conversationMessages(SmsConversation $conversation)
    {
        // Verify ownership via phone number
        if ($conversation->phoneNumber->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $messages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->get();

        // Mark as read
        $conversation->markAsRead();

        return response()->json([
            'messages' => $messages,
            'conversation' => $conversation->load(['phoneNumber', 'aiAgent', 'contact']),
        ]);
    }

    public function sendMessage(Request $request, SmsConversation $conversation, TelephonyManager $telephony)
    {
        // Verify ownership
        if ($conversation->phoneNumber->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'message' => 'required|string|max:1600',
        ]);

        try {
            $provider = SmsProviderFactory::makeForUser(Auth::user(), $telephony);

            $result = $provider->send(
                from: $conversation->phoneNumber->number,
                to: $conversation->contact_phone,
                message: $validated['message']
            );

            $message = SmsMessage::create([
                'conversation_id' => $conversation->id,
                'direction' => 'outbound',
                'message_body' => $validated['message'],
                'sender_phone' => $conversation->phoneNumber->number,
                'receiver_phone' => $conversation->contact_phone,
                'status' => $result['success'] ? 'sent' : 'failed',
                'ai_generated' => false,
                'provider' => $telephony->provider()->value,
                'provider_message_id' => $result['message_id'],
                // The real count the provider used, not an assumed 1 —
                // see send()'s identical comment.
                'num_segments' => $result['num_segments'] ?? 1,
                'error_message' => $result['error'],
            ]);

            $conversation->updateLastMessageTime();

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Templates Management
    public function templates()
    {
        $templates = Auth::user()->smsTemplates()
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('sms/templates', [
            'templates' => $templates,
        ]);
    }

    // Analytics
    public function analytics()
    {
        $user = Auth::user();

        $stats = [
            'total_conversations' => SmsConversation::whereHas('phoneNumber', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })->count(),

            'total_messages' => SmsMessage::whereHas('conversation.phoneNumber', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })->count(),

            'active_conversations' => SmsConversation::whereHas('phoneNumber', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })->where('status', 'active')->count(),

            'ai_response_rate' => SmsMessage::whereHas('conversation.phoneNumber', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })->where('ai_generated', true)->count(),
        ];

        return Inertia::render('sms/analytics', [
            'stats' => $stats,
        ]);
    }
}
