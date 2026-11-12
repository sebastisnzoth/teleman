<?php

namespace App\Services\AiAgent;

use App\Jobs\TrackAiAgentCostJob;
use App\Models\AiAgentCall;
use App\Models\AiAgentConversation;
use Exception;
use Illuminate\Support\Facades\Log;

class CallStateManager
{
    private DeepgramService $deepgram;
    private ConversationEngine $conversation;

    public function __construct(
        DeepgramService $deepgram,
        ConversationEngine $conversation
    ) {
        $this->deepgram = $deepgram;
        $this->conversation = $conversation;
    }

    /**
     * Initialize a new AI agent call
     */
    public function initializeCall(
        AiAgent $agent,
        string $callSid,
        string $direction,
        string $from,
        string $to,
        ?int $contactId = null,
        ?int $campaignId = null
    ): AiAgentCall {
        $call = AiAgentCall::create([
            'ai_agent_id' => $agent->id,
            'call_sid' => $callSid,
            'direction' => $direction,
            'from_number' => $from,
            'to_number' => $to,
            'contact_id' => $contactId,
            'campaign_id' => $campaignId,
            'status' => 'initiated',
            'started_at' => now(),
        ]);

        // Add system message
        $this->addConversation($call, 'system', $agent->system_prompt, 0);

        Log::info('AI Agent call initialized', [
            'call_id' => $call->id,
            'agent_id' => $agent->id,
            'call_sid' => $callSid,
        ]);

        return $call;
    }

    /**
     * Handle call answered
     */
    public function handleCallAnswered(AiAgentCall $call): ?array
    {
        try {
            $call->markAnswered();

            // Eager load agent if not already loaded
            if (!$call->relationLoaded('agent')) {
                $call->load('agent');
            }
            $agent = $call->agent;
            $greeting = $this->conversation->generateGreeting($agent);

            // Add assistant greeting to conversation
            $this->addConversation($call, 'assistant', $greeting, $this->nextTurnNumber($call));

            return [
                'text' => $greeting,
                'action' => 'speak',
            ];
        } catch (Exception $e) {
            Log::error('Error handling call answered', [
                'call_id' => $call->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Process user speech input
     */
    public function processUserInput(
        AiAgentCall $call,
        string $transcript,
        float $confidence = 1.0
    ): ?array {
        try {
            // Eager load agent if not already loaded
            if (!$call->relationLoaded('agent')) {
                $call->load('agent');
            }
            $agent = $call->agent;
            
            // Add user message to conversation. Rows are numbered from the
            // ones already stored rather than from the turn counter: one
            // exchange is two rows but counts as one turn, so deriving the
            // row number from the counter left a gap per exchange.
            $turnNumber = $this->nextTurnNumber($call);
            $this->addConversation($call, 'user', $transcript, $turnNumber, $confidence);

            // One exchange, counted once. Every branch below writes the
            // agent's half of it, so the exchange is complete from here on
            // whichever way it ends.
            $call->increment('turn_count');

            // Check if conversation should end
            if ($this->conversation->shouldEndConversation($transcript)) {
                $goodbye = $this->conversation->generateGoodbye($agent);
                $this->addConversation($call, 'assistant', $goodbye, $turnNumber + 1);

                return [
                    'text' => $goodbye,
                    'action' => 'end',
                ];
            }

            // Generate AI response
            $response = $this->conversation->generateResponse($agent, $call, $transcript);

            if (!$response) {
                // Track consecutive LLM failures so we don't loop "sorry, repeat that"
                // forever — after 2 fails, escalate (transfer to human or end gracefully).
                $metadata = is_array($call->metadata) ? $call->metadata : [];
                $llmFailures = (int) ($metadata['llm_failures'] ?? 0) + 1;
                $metadata['llm_failures'] = $llmFailures;
                $call->update(['metadata' => $metadata]);

                if ($llmFailures >= 2 && $agent->enable_transfer && !empty($agent->transfer_number)) {
                    $transferMsg = "I'm having trouble processing that. Let me connect you with a team member.";
                    $this->addConversation($call, 'assistant', $transferMsg, $turnNumber + 1);
                    return [
                        'text' => $transferMsg,
                        'action' => 'transfer',
                        'transfer_to' => $agent->transfer_number,
                    ];
                }

                if ($llmFailures >= 3) {
                    $goodbye = "I apologize for the trouble. Please feel free to call back anytime. Goodbye.";
                    $this->addConversation($call, 'assistant', $goodbye, $turnNumber + 1);
                    return [
                        'text' => $goodbye,
                        'action' => 'end',
                    ];
                }

                // First failure — ask politely
                $fallback = "Sorry, could you say that again?";
                $this->addConversation($call, 'assistant', $fallback, $turnNumber + 1);

                return [
                    'text' => $fallback,
                    'action' => 'continue',
                ];
            }

            // Successful response — reset failure counter
            $metadata = is_array($call->metadata) ? $call->metadata : [];
            if (!empty($metadata['llm_failures'])) {
                $metadata['llm_failures'] = 0;
                $call->update(['metadata' => $metadata]);
            }

            // Add assistant response to conversation
            $this->addConversation(
                $call,
                'assistant',
                $response['content'],
                $turnNumber + 1,
                null,
                $response['usage'] ?? null
            );

            // Track LLM tokens and cost via OpenRouter (queued to avoid blocking response)
            if (isset($response['usage'])) {
                TrackAiAgentCostJob::dispatch($call->id, $response['usage'], $response['model'] ?? null);
            }

            // Handle transfer if needed
            if ($response['action'] === 'transfer') {
                return [
                    'text' => $response['content'],
                    'action' => 'transfer',
                    'transfer_to' => $response['transfer_to'],
                ];
            }

            return [
                'text' => $response['content'],
                'action' => 'continue',
            ];
        } catch (Exception $e) {
            Log::error('Error processing user input', [
                'call_id' => $call->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Handle call completion
     */
    public function handleCallCompleted(
        AiAgentCall $call,
        string $reason = 'completed',
        ?string $recordingUrl = null
    ): void {
        try {
            $call->markCompleted($reason);

            if ($recordingUrl) {
                $call->update(['recording_url' => $recordingUrl]);
            }

            // Calculate cost estimate
            $call->updateCostEstimate();

            Log::info('AI Agent call completed', [
                'call_id' => $call->id,
                'duration' => $call->duration,
                'turns' => $call->turn_count,
                'cost' => $call->cost_estimate,
                'reason' => $reason,
            ]);
        } catch (Exception $e) {
            Log::error('Error handling call completion', [
                'call_id' => $call->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Add conversation turn
     */
    private function addConversation(
        AiAgentCall $call,
        string $role,
        string $content,
        int $turnNumber,
        ?float $confidence = null,
        ?array $metadata = null
    ): AiAgentConversation {
        return AiAgentConversation::create([
            'ai_agent_call_id' => $call->id,
            'turn_number' => $turnNumber,
            'role' => $role,
            'content' => $content,
            'confidence' => $confidence,
            'metadata' => $metadata,
        ]);
    }

    /**
     * The number the next conversation row takes: one past the highest
     * already stored, so the rows of a call run 0, 1, 2, ... unbroken.
     */
    private function nextTurnNumber(AiAgentCall $call): int
    {
        return (int) $call->conversations()->max('turn_number') + 1;
    }

    /**
     * Check if call has exceeded limits
     */
    public function shouldEndCall(AiAgentCall $call): bool
    {
        if (!$call->relationLoaded('agent')) {
            $call->load('agent');
        }
        $agent = $call->agent;

        // Check duration limit
        if ($call->answered_at) {
            $duration = now()->diffInSeconds($call->answered_at);
            if ($duration >= $agent->max_duration) {
                Log::info('Call exceeded max duration', [
                    'call_id' => $call->id,
                    'duration' => $duration,
                    'max' => $agent->max_duration,
                ]);
                return true;
            }
        }

        // Check conversation turn limit
        $maxTurns = config('ai-agent.conversation.max_turns', 50);
        if ($call->turn_count >= $maxTurns) {
            Log::info('Call exceeded max turns', [
                'call_id' => $call->id,
                'turns' => $call->turn_count,
            ]);
            return true;
        }

        return false;
    }

    /**
     * Get call statistics
     */
    public function getCallStats(AiAgentCall $call): array
    {
        return [
            'duration' => $call->duration,
            'turns' => $call->turn_count,
            'cost' => $call->cost_estimate,
            'twilio_cost' => $call->twilio_cost,
            'openai_cost' => $call->openai_cost,
            'total_tokens' => $call->total_tokens,
            'status' => $call->status,
            'transferred' => $call->transferred,
            'end_reason' => $call->end_reason,
        ];
    }

}
