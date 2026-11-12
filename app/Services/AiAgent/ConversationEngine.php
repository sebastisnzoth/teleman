<?php

namespace App\Services\AiAgent;

use App\Models\AiAgent;
use App\Models\AiAgentCall;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ConversationEngine
{
    private string $apiKey;
    private string $apiUrl;
    private array $fallbackModels;

    public function __construct(AiAgent $agent)
    {
        $this->apiKey = config('openrouter.api_key');
        $this->apiUrl = 'https://openrouter.ai/api/v1';
        $this->fallbackModels = config('openrouter.fallback_models', []);

        if (empty($this->apiKey)) {
            throw new Exception("OpenRouter API key is not configured for agent {$agent->name}");
        }
    }

    /**
     * Generate AI response for conversation
     */
    public function generateResponse(
        AiAgent $agent,
        AiAgentCall $call,
        string $userMessage
    ): ?array {
        try {
            // Build conversation history
            $messages = $this->buildMessageHistory($agent, $call, $userMessage);
            
            // Add new user message
            $messages[] = [
                'role' => 'user',
                'content' => $userMessage,
            ];

            // Check if should transfer
            if ($agent->shouldTransfer($userMessage)) {
                return [
                    'content' => $this->getTransferMessage($agent),
                    'action' => 'transfer',
                    'transfer_to' => $agent->transfer_number,
                ];
            }

            // Prepare models to try — start with agent's preferred model, then fallbacks.
            // Auto-upgrade agents still configured with the slow `openrouter/free`
            // aggregator: put the fast primary first and push the slow one to the end.
            $agentModel = $agent->model ?: config('openrouter.model');
            if ($agentModel === 'openrouter/free' && !empty($this->fallbackModels)) {
                $agentModel = $this->fallbackModels[0]; // Use fastest fallback (Gemini Flash)
            }
            $modelsToTry = [$agentModel];

            // Add fallback models if available
            if (!empty($this->fallbackModels)) {
                foreach ($this->fallbackModels as $fallbackModel) {
                    if (!in_array($fallbackModel, $modelsToTry, true)) {
                        $modelsToTry[] = $fallbackModel;
                    }
                }
            }

            $lastException = null;

            // Try each model in sequence until one succeeds
            foreach ($modelsToTry as $index => $modelName) {
                try {
                    Log::debug('Attempting AI agent conversation', [
                        'model' => $modelName,
                        'attempt' => $index + 1,
                        'total_models' => count($modelsToTry),
                        'agent_id' => $agent->id,
                        'call_id' => $call->id,
                    ]);

                    $headers = [
                        'Authorization' => "Bearer {$this->apiKey}",
                        'Content-Type' => 'application/json',
                        'HTTP-Referer' => config('app.url'),
                        'X-Title' => config('app.name'),
                    ];
                    
                    // Tight cap for voice — most replies are 1–3 sentences (≈80-150 tokens).
                    // Lower cap = faster generation. 250 is enough for a full thought.
                    $maxTokens = min((int) ($agent->max_tokens ?: 250), 250);

                    // Fail fast on the primary model so we can try a fallback quickly.
                    // 8s is plenty for Gemini Flash (typically responds in 0.8-1.5s).
                    $baseTimeout = (int) ($agent->response_timeout ?? 8);
                    $timeout = $index === 0 ? max(6, min($baseTimeout, 10)) : 8;
                    
                    $startedAt = microtime(true);
                    $response = Http::withHeaders($headers)
                        ->timeout($timeout)
                        ->connectTimeout(3) // Fail fast on connection setup
                        ->post("{$this->apiUrl}/chat/completions", [
                            'model' => $modelName,
                            'messages' => $messages,
                            'max_tokens' => $maxTokens,
                            'temperature' => (float) $agent->temperature,
                            'stream' => false,
                            // Lower penalties = faster inference. Voice replies are
                            // already short so repetition isn't really a problem.
                            'presence_penalty' => 0.2,
                            'frequency_penalty' => 0.0,
                        ]);
                    $elapsedMs = (int) ((microtime(true) - $startedAt) * 1000);

                    if ($response->successful()) {
                        $data = $response->json();

                        $content = trim($data['choices'][0]['message']['content'] ?? '');
                        $usage = $data['usage'] ?? [];

                        // Sanitize the response for TTS — remove markdown, emojis,
                        // stage directions, and meta-commentary that break natural speech.
                        $content = $this->sanitizeForSpeech($content);

                        // Cap length for faster TTS (configurable). Prefer to truncate
                        // at the last sentence-ending punctuation so responses don't get
                        // cut mid-sentence. Only truncate if the overflow is meaningful.
                        $maxResponseChars = config('ai-agent.max_response_chars', 800);
                        if ($maxResponseChars > 0 && strlen($content) > $maxResponseChars) {
                            $truncated = substr($content, 0, $maxResponseChars);
                            // Prefer last sentence boundary (., !, ?)
                            $lastPunct = max(
                                strrpos($truncated, '. '),
                                strrpos($truncated, '! '),
                                strrpos($truncated, '? ')
                            );
                            if ($lastPunct !== false && $lastPunct > ($maxResponseChars * 0.5)) {
                                $content = substr($truncated, 0, $lastPunct + 1);
                            } else {
                                // Fall back to last space, but only if it leaves substantial content
                                $lastSpace = strrpos($truncated, ' ');
                                if ($lastSpace !== false && $lastSpace > ($maxResponseChars * 0.5)) {
                                    $content = substr($truncated, 0, $lastSpace);
                                } else {
                                    // Truncation would leave garbage — keep the full (overflow is small enough)
                                    $content = $truncated;
                                }
                            }
                            $content = trim($content);
                        }

                        if ($content === '') {
                            $lastException = new Exception('Model returned empty content');
                            continue;
                        }

                        Log::info('AI agent LLM timing', [
                            'model' => $modelName,
                            'attempt' => $index + 1,
                            'elapsed_ms' => $elapsedMs,
                            'call_id' => $call->id,
                            'input_tokens' => $usage['prompt_tokens'] ?? null,
                            'output_tokens' => $usage['completion_tokens'] ?? null,
                        ]);

                        Log::debug('AI agent conversation success', [
                            'model' => $modelName,
                            'attempt' => $index + 1,
                            'agent_id' => $agent->id,
                            'call_id' => $call->id,
                        ]);

                        return [
                            'content' => $content,
                            'action' => 'continue',
                            'usage' => $usage,
                            'model' => $modelName,
                        ];
                    }

                    // API returned error status, log and try next model
                    Log::warning('AI agent conversation failed for model', [
                        'model' => $modelName,
                        'attempt' => $index + 1,
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'agent_id' => $agent->id,
                        'call_id' => $call->id,
                    ]);
                    
                    $lastException = new Exception("Model {$modelName} failed: " . $response->status());

                } catch (Exception $e) {
                    Log::warning('AI agent conversation exception for model', [
                        'model' => $modelName,
                        'attempt' => $index + 1,
                        'error' => $e->getMessage(),
                        'agent_id' => $agent->id,
                        'call_id' => $call->id,
                    ]);
                    
                    $lastException = $e;
                    
                    // Continue to next model
                    continue;
                }
            }

            // All models failed
            Log::error('All AI agent conversation models failed', [
                'models_tried' => $modelsToTry,
                'last_error' => $lastException?->getMessage(),
                'agent_id' => $agent->id,
                'call_id' => $call->id,
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Conversation engine error', [
                'error' => $e->getMessage(),
                'agent_id' => $agent->id,
                'call_id' => $call->id,
            ]);
            return null;
        }
    }

    /**
     * Build message history for context
     */
    private function buildMessageHistory(AiAgent $agent, AiAgentCall $call, ?string $currentUtterance = null): array
    {
        $messages = [];

        // Cache assembled system prompt per agent (10 min TTL) to avoid rebuilding every turn.
        // Cache key includes agent.updated_at AND the base instruction file mtime so any
        // change to either invalidates the cache automatically.
        $baseFilePath = config('ai-agent.voice_ai_base_instruction_file');
        $baseFileMtime = ($baseFilePath && file_exists($baseFilePath)) ? filemtime($baseFilePath) : 0;
        $cacheKey = "ai_agent_system_prompt:{$agent->id}:" . md5(($agent->updated_at ?? '') . ':' . $baseFileMtime);
        $systemPrompt = Cache::remember($cacheKey, 600, function () use ($agent) {
            $baseInstruction = $this->getVoiceAiBaseInstruction();
            $prompt = $baseInstruction;

            // Add knowledge base if available
            $agent->loadMissing('knowledgeBase');
            $kbRaw = $agent->getEffectiveKnowledgeBaseContent();
            if ($kbRaw !== null && $kbRaw !== '') {
                $kb = $kbRaw;
                $maxKbChars = (int) config('ai-agent.max_knowledge_base_chars', 4000);
                if ($maxKbChars > 0 && Str::length($kb) > $maxKbChars) {
                    $kb = Str::limit($kb, $maxKbChars);
                }
                $prompt .= "\n\nCONTEXT:\n" . $kb;
            }

            // Add user's custom system prompt
            if (!empty($agent->system_prompt)) {
                $prompt .= "\n\nINSTRUCTIONS:\n" . trim($agent->system_prompt);
            }

            return $prompt;
        });

        // Append per-call context (contact + campaign) AFTER the cached agent prompt.
        // This is not cached because it changes per call — but it gives the AI
        // everything it needs to speak to this specific person about the right thing.
        $callContext = $this->buildCallContext($call);
        if (!empty($callContext)) {
            $systemPrompt .= "\n\nCALL CONTEXT:\n" . $callContext;
        }

        $messages[] = [
            'role' => 'system',
            'content' => $systemPrompt,
        ];

        // Include recent conversation history so the AI remembers what was said.
        // 6 turns (≈3 user + 3 AI) is a good latency/context balance —
        // smaller prompt = faster inference.
        //
        // Seven rows are fetched for six turns of history. The caller's
        // turn is written to the transcript before the engine is asked
        // about it, so on the live path the newest row is the utterance
        // appended below and is dropped from the history at the end of
        // this method. Fetching six would leave the model five turns of
        // context, not the six documented here.
        //
        // reorder() and not orderBy(): the relation itself orders oldest
        // first, which is what every other reader of a transcript wants,
        // and an orderBy() here would only be appended to that — a dead
        // secondary sort, leaving the limit to take the *oldest* six rows
        // of the call. This is the one reader that wants the newest few,
        // so it replaces the relation's ordering instead of adding to it,
        // then turns them back into the order they were spoken in.
        $conversations = $call->conversations()
            ->where('role', '!=', 'system')
            ->reorder('turn_number', 'desc')
            ->limit(7)
            ->get()
            ->reverse()
            ->values();

        foreach ($conversations as $conv) {
            $messages[] = [
                'role' => $conv->role,
                'content' => $conv->content,
            ];
        }

        // The caller's turn is written to the transcript before the model
        // is asked about it, so the newest row is usually the very
        // utterance the caller is about to append. Replayed as history as
        // well, it reads as the caller having said the same thing twice.
        $last = end($messages);
        if ($currentUtterance !== null && $last['role'] === 'user' && $last['content'] === $currentUtterance) {
            array_pop($messages);
        }

        return $messages;
    }

    /**
     * Get transfer message
     */
    private function getTransferMessage(AiAgent $agent): string
    {
        return config('ai-agent.transfer.confirmation',
            "I'll transfer you to a human agent right away. Please hold.");
    }

    /**
     * Build per-call context for the system prompt — contact info (name,
     * company) and campaign info. This lets the AI personalize the conversation
     * without requiring the agent's custom system_prompt to know about every
     * possible caller.
     */
    private function buildCallContext(AiAgentCall $call): string
    {
        $lines = [];

        // Load relationships if not already loaded.
        // AiAgentCall has direct contact() and campaign() relations.
        try {
            $call->loadMissing(['contact', 'campaign']);
        } catch (\Throwable $e) {
            // If relationships aren't defined, skip context enrichment gracefully
        }

        $contact = $call->contact ?? null;
        if ($contact) {
            $name = trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? ''));
            if ($name !== '') {
                $lines[] = "You are speaking with: {$name}";
            }
            if (!empty($contact->company)) {
                $lines[] = "Their company: {$contact->company}";
            }
            if (!empty($contact->email)) {
                $lines[] = "Their email (do not read aloud unless asked): {$contact->email}";
            }
            if (!empty($contact->notes)) {
                $lines[] = "Notes from previous interactions: {$contact->notes}";
            }
        }

        if ($call->campaign) {
            $lines[] = "Reason for this call: campaign '{$call->campaign->name}'";
            if (!empty($call->campaign->description)) {
                $lines[] = "Campaign description: {$call->campaign->description}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Strip anything that shouldn't be spoken aloud: markdown, emojis,
     * parenthetical stage directions, meta-commentary. LLMs sometimes leak
     * annotations like "(Backchannel word: Got it!)" which Twilio's <Say>
     * reads verbatim and makes the call sound broken.
     */
    private function sanitizeForSpeech(string $text): string
    {
        // Remove common markdown symbols
        $text = preg_replace('/[*_`#>]+/', '', $text);

        // Remove parenthetical stage directions / meta-notes that start with
        // keywords like "Backchannel", "Note", "pauses", "in a", "aside", etc.
        // We strip (anything containing ":") and short parentheticals (<40 chars)
        // that look like asides, while preserving legitimate parentheticals.
        $text = preg_replace('/\s*\([^)]*:[^)]*\)\s*/u', ' ', $text);
        // Remove simple asterisk-wrapped actions: *smiles*, *pauses*
        $text = preg_replace('/\*[^*]+\*/', '', $text);
        // Remove bracketed annotations: [note], [system], [in warm tone]
        $text = preg_replace('/\[[^\]]*\]/', '', $text);

        // Remove emojis and pictographic symbols (keep letters, digits, punctuation,
        // whitespace, and common text characters).
        $text = preg_replace('/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}\x{1F300}-\x{1F6FF}\x{1F900}-\x{1F9FF}]/u', '', $text);

        // Collapse multiple whitespace chars into one
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Generate greeting message for new call
     */
    public function generateGreeting(AiAgent $agent): string
    {
        // Use custom first_message if configured
        if (!empty($agent->first_message)) {
            return $agent->first_message;
        }

        // Fallback to default greetings
        $greetings = [
            'inbound' => "Hello! I'm an AI assistant. How can I help you today?",
            'outbound' => "Hello! This is an automated call from " . config('app.name') . ". Do you have a moment to talk?",
        ];

        return $greetings[$agent->type] ?? $greetings['inbound'];
    }

    /**
     * Generate goodbye message
     */
    public function generateGoodbye(AiAgent $agent = null): string
    {
        // Use custom goodbye_message if configured
        if ($agent && !empty($agent->goodbye_message)) {
            return $agent->goodbye_message;
        }

        return "Thank you for your time. Have a great day!";
    }

    /**
     * Get Voice AI Base Instruction
     * Loads from public/voice-ai-base-instruction.md with caching.
     * Cache key includes the file's mtime so edits invalidate the cache
     * automatically without requiring a manual cache clear.
     */
    private function getVoiceAiBaseInstruction(): string
    {
        $filePath = config('ai-agent.voice_ai_base_instruction_file');

        if (!$filePath || !file_exists($filePath)) {
            return '';
        }

        $cacheKey = 'voice_ai_base_instruction:' . filemtime($filePath);

        $cachedInstruction = cache()->get($cacheKey);
        if ($cachedInstruction !== null) {
            return $cachedInstruction;
        }

        $instruction = file_get_contents($filePath) ?: '';

        if (!empty($instruction)) {
            cache()->put($cacheKey, $instruction, 3600);
        }

        return $instruction;
    }

    /**
     * Check if conversation should end
     */
    public function shouldEndConversation(string $userMessage): bool
    {
        $endKeywords = [
            'goodbye',
            'bye',
            'end call',
            'hang up',
            'stop',
            'no thanks',
            'not interested',
        ];

        $message = strtolower(trim($userMessage));
        
        foreach ($endKeywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Test the conversation engine
     */
    public function test(): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'HTTP-Referer' => config('app.url'),
                'X-Title' => config('app.name'),
            ])->get("{$this->apiUrl}/models");

            return $response->successful();
        } catch (Exception $e) {
            Log::error('OpenRouter test failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get available models
     */
    public function getAvailableModels(): array
    {
        return config('ai-agent.models', []);
    }
}
