<?php

namespace App\Jobs;

use App\Events\SmsMessageSent;
use App\Models\AiAgent;
use App\Models\AiAgentSmsSession;
use App\Models\PhoneNumber;
use App\Models\SmsConversation;
use App\Models\SmsMessage;
use App\Services\CreditService;
use App\Services\PricingService;
use App\Services\SMS\SmsProviderFactory;
use App\Services\Telephony\TelephonyManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenerateAISmsResponseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $conversation;

    public $incomingMessage;

    public $aiAgent;

    public $phoneNumber;

    /**
     * Create a new job instance.
     */
    public function __construct(
        SmsConversation $conversation,
        SmsMessage $incomingMessage,
        AiAgent $aiAgent,
        PhoneNumber $phoneNumber
    ) {
        $this->conversation = $conversation;
        $this->incomingMessage = $incomingMessage;
        $this->aiAgent = $aiAgent;
        $this->phoneNumber = $phoneNumber;
    }

    /**
     * Execute the job.
     */
    public function handle(TelephonyManager $telephony): void
    {
        $startTime = microtime(true);

        try {
            // Check if user has sufficient credits before processing
            $user = $this->phoneNumber->user;
            if (! $user) {
                Log::error('Cannot process SMS: User not found', [
                    'phone_number_id' => $this->phoneNumber->id,
                ]);

                return;
            }

            // Estimate cost for SMS (assume 1 segment initially, will be adjusted)
            $creditService = app(CreditService::class);
            $pricingService = app(PricingService::class);

            $countryCode = $this->extractCountryCode($this->conversation->contact_phone);
            $estimatedCost = $pricingService->calculateSmsCost(
                $countryCode,
                1, // Estimate 1 segment initially
                $user->pricing_tier ?? 'standard'
            );

            // Check if user can afford the estimated cost
            if (! $creditService->canAfford($user, $estimatedCost['charge'])) {
                Log::error('Insufficient credits for SMS', [
                    'user_id' => $user->id,
                    'required' => $estimatedCost['charge'],
                    'balance' => $user->credit_balance,
                    'conversation_id' => $this->conversation->id,
                ]);

                // Send notification to user about insufficient funds
                $this->sendInsufficientFundsNotification($user);

                return;
            }

            // Get or create SMS session for tracking
            $session = $this->getOrCreateSession();

            // Get conversation history (last 10 messages for context)
            $history = $this->conversation->messages()
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->reverse();

            // Build SMS-specific prompt for AI
            $prompt = $this->buildSmsPrompt($history);

            // Get AI response from OpenRouter
            $aiResponse = $this->getAiResponse($prompt);

            // Send SMS via provider
            $result = $this->sendSms($aiResponse, $telephony);

            // Store outbound message
            $outMessage = SmsMessage::create([
                'conversation_id' => $this->conversation->id,
                'direction' => 'outbound',
                'message_body' => $aiResponse,
                'sender_phone' => $this->phoneNumber->number,
                'receiver_phone' => $this->conversation->contact_phone,
                'ai_generated' => true,
                'status' => $result['success'] ? 'sent' : 'failed',
                'provider' => $telephony->provider()->value,
                'provider_message_id' => $result['message_id'],
                'error_message' => $result['error'],
                // The real count the provider used, not an assumed 1 —
                // already read correctly here; sendSms() now resolves the
                // provider through the lock-driven seam instead of
                // hardcoding Twilio, so this keeps working for either.
                'num_segments' => $result['num_segments'] ?? 1,
                'cost' => $result['cost'],
                'sent_at' => $result['success'] ? now() : null,
            ]);

            // Update session metrics
            $responseTime = (int) ((microtime(true) - $startTime) * 1000); // milliseconds
            $session->incrementMessageCount();
            $session->updateResponseTime($responseTime);

            // Update conversation
            $this->conversation->updateLastMessageTime();

            // Broadcast to frontend (real-time)
            broadcast(new SmsMessageSent($outMessage, $this->conversation));

            Log::info('AI SMS response generated successfully', [
                'conversation_id' => $this->conversation->id,
                'response_time_ms' => $responseTime,
                'message_length' => strlen($aiResponse),
            ]);

        } catch (\Exception $e) {
            Log::error('AI SMS response generation failed', [
                'conversation_id' => $this->conversation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Store failed message. This path runs before sendSms() is
            // ever reached (the AI response itself failed to generate),
            // so there is no provider response to read a count from — but
            // the row must still say which provider this installation was
            // configured for, same as every other message row.
            SmsMessage::create([
                'conversation_id' => $this->conversation->id,
                'direction' => 'outbound',
                'message_body' => 'Error: Could not generate response',
                'sender_phone' => $this->phoneNumber->number,
                'receiver_phone' => $this->conversation->contact_phone,
                'ai_generated' => true,
                'status' => 'failed',
                'provider' => $telephony->provider()->value,
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get or create AI agent SMS session
     */
    private function getOrCreateSession(): AiAgentSmsSession
    {
        // Find active session for this conversation
        $session = AiAgentSmsSession::where('conversation_id', $this->conversation->id)
            ->whereNull('ended_at')
            ->first();

        if (! $session) {
            $session = AiAgentSmsSession::create([
                'ai_agent_id' => $this->aiAgent->id,
                'conversation_id' => $this->conversation->id,
                'message_count' => 0,
                'started_at' => now(),
            ]);
        }

        return $session;
    }

    /**
     * Build AI prompt with conversation history
     */
    private function buildSmsPrompt(mixed $history): string
    {
        $conversationHistory = '';

        foreach ($history as $msg) {
            $role = $msg->direction === 'inbound' ? 'Customer' : 'You';
            $conversationHistory .= "{$role}: {$msg->message_body}\n";
        }

        $systemPrompt = <<<PROMPT
You are {$this->aiAgent->name}, an AI assistant handling SMS conversations.

Your personality and instructions:
{$this->aiAgent->system_prompt}

CRITICAL RULES FOR SMS:
1. Keep responses under 160 characters when possible (single SMS segment)
2. Be conversational and friendly
3. Use proper grammar and punctuation
4. Respond naturally to the customer's message
5. If unclear, ask clarifying questions
6. Be helpful and professional
7. DO NOT use emojis unless specifically told to
8. Keep responses concise and to the point

Previous conversation:
{$conversationHistory}

Latest customer message: "{$this->incomingMessage->message_body}"

Respond naturally to the customer's latest message. Remember: Keep it under 160 characters if possible.
PROMPT;

        return $systemPrompt;
    }

    /**
     * Get AI response from OpenRouter
     */
    private function getAiResponse(string $prompt): string
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.config('openrouter.api_key'),
                'HTTP-Referer' => config('openrouter.site_url'),
                'X-Title' => config('openrouter.app_name'),
                'Content-Type' => 'application/json',
                // openrouter.api_url already ends in /chat/completions; appending it
                // again produced .../chat/completions/chat/completions and every AI
                // SMS reply failed.
            ])->post(config('openrouter.api_url'), [
                'model' => $this->aiAgent->model ?? config('openrouter.model'),
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'max_tokens' => 200, // Short responses for SMS
                'temperature' => $this->aiAgent->temperature ?? 0.7,
            ]);

            if (! $response->successful()) {
                throw new \Exception('OpenRouter API failed: '.$response->body());
            }

            $data = $response->json();
            $message = $data['choices'][0]['message']['content'] ?? '';

            // Trim and clean the response
            $message = trim($message);

            // Ensure it's not too long (max 320 chars = 2 SMS segments)
            if (strlen($message) > 320) {
                $message = substr($message, 0, 317).'...';
            }

            return $message;

        } catch (\Exception $e) {
            Log::error('OpenRouter API call failed', [
                'error' => $e->getMessage(),
            ]);

            // Fallback response
            return "I'm sorry, I'm having trouble responding right now. Please try again in a moment.";
        }
    }

    /**
     * Send SMS via provider
     */
    private function sendSms(string $message, TelephonyManager $telephony): array
    {
        try {
            $provider = SmsProviderFactory::makeForUser($this->phoneNumber->user, $telephony);

            return $provider->send(
                from: $this->phoneNumber->number,
                to: $this->conversation->contact_phone,
                message: $message
            );

        } catch (\Exception $e) {
            Log::error('SMS send failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message_id' => null,
                'error' => $e->getMessage(),
                'cost' => null,
            ];
        }
    }

    /**
     * Extract country code from phone number
     */
    private function extractCountryCode(string $phoneNumber): string
    {
        // Remove all non-numeric characters
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);

        // Common country code mappings
        if (str_starts_with($cleaned, '1')) {
            return 'US';
        }
        if (str_starts_with($cleaned, '44')) {
            return 'GB';
        }
        if (str_starts_with($cleaned, '61')) {
            return 'AU';
        }
        if (str_starts_with($cleaned, '91')) {
            return 'IN';
        }
        if (str_starts_with($cleaned, '49')) {
            return 'DE';
        }
        if (str_starts_with($cleaned, '33')) {
            return 'FR';
        }
        if (str_starts_with($cleaned, '81')) {
            return 'JP';
        }
        if (str_starts_with($cleaned, '55')) {
            return 'BR';
        }
        if (str_starts_with($cleaned, '52')) {
            return 'MX';
        }

        return 'US'; // Default
    }

    /**
     * Send notification to user about insufficient funds
     */
    private function sendInsufficientFundsNotification($user): void
    {
        try {
            // You can implement email/notification logic here
            Log::warning('User notified about insufficient credits for SMS', [
                'user_id' => $user->id,
                'balance' => $user->credit_balance,
            ]);

            // Optional: Send email notification
            // Mail::to($user->email)->send(new InsufficientCreditsNotification($user));

        } catch (\Exception $e) {
            Log::error('Failed to send insufficient funds notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
