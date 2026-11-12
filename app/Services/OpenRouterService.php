<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class OpenRouterService
{
    private string $apiKey;
    private string $apiUrl;
    private string $model;
    private array $fallbackModels;
    private int $maxTokens;
    private float $temperature;
    private string $siteUrl;
    private string $appName;

    public function __construct()
    {
        $this->apiKey = config('openrouter.api_key');
        $this->apiUrl = config('openrouter.api_url');
        $this->model = config('openrouter.model');
        $this->fallbackModels = config('openrouter.fallback_models', []);
        $this->maxTokens = config('openrouter.max_tokens');
        $this->temperature = config('openrouter.temperature');
        $this->siteUrl = config('openrouter.site_url');
        $this->appName = config('openrouter.app_name');
    }

    /**
     * Generate a campaign message using AI
     */
    public function generateMessage(string $prompt, array $variables = []): string
    {
        // Create cache key based on prompt and variables
        $cacheKey = 'ai_gen_' . md5($prompt . json_encode($variables));

        // Check cache first (1 hour TTL)
        $cached = Cache::get($cacheKey);
        if ($cached) {
            Log::info('AI generation cache hit', ['prompt_preview' => substr($prompt, 0, 50)]);
            return $cached;
        }

        $systemPrompt = $this->buildSystemPrompt($variables);
        $message = $this->callApi($systemPrompt, $prompt);

        // Cache successful generation
        Cache::put($cacheKey, $message, 3600);

        return $message;
    }

    /**
     * Enhance an existing campaign message
     */
    public function enhanceMessage(string $existingMessage, array $variables = []): string
    {
        $systemPrompt = $this->buildSystemPrompt($variables);
        $userPrompt = "Enhance and improve this campaign message while keeping it under 250 characters and preserving all variables:\n\n{$existingMessage}";

        return $this->callApi($systemPrompt, $userPrompt);
    }

    /**
     * Analyze sentiment from call transcript
     */
    public function analyzeSentiment(string $transcript): array
    {
        $systemPrompt = <<<PROMPT
You are an expert call quality analyst specializing in sentiment analysis and lead qualification for telemarketing calls.

Analyze call transcripts and provide structured insights in JSON format.

STRICT RULES:
1. Return ONLY valid JSON, nothing else
2. Be objective and data-driven
3. Consider context and conversation flow
4. Identify genuine customer intent
5. Provide actionable insights
PROMPT;

        $userPrompt = <<<PROMPT
Analyze this call transcript and provide insights in the following JSON format:

{
    "sentiment": "positive|neutral|negative",
    "confidence": 85,
    "lead_score": 7,
    "lead_quality": "hot|warm|cold|not_interested",
    "key_intents": ["interested_in_product", "price_concern", "wants_callback"],
    "summary": "Brief 2-sentence summary of the call",
    "follow_up": "Recommended next action"
}

Guidelines:
- sentiment: Overall emotional tone (positive/neutral/negative)
- confidence: How confident you are in sentiment (0-100)
- lead_score: Quality as sales lead (1-10, where 10 is best)
- lead_quality: hot (ready to buy), warm (interested), cold (maybe later), not_interested
- key_intents: Array of detected customer intentions
- summary: Concise call summary in 2 sentences max
- follow_up: What should happen next

Transcript:
{$transcript}

Return ONLY the JSON object, no explanations:
PROMPT;

        try {
            $jsonResponse = $this->callApiRaw($systemPrompt, $userPrompt);
            
            // Clean the response - remove markdown code blocks if present
            $jsonResponse = preg_replace('/```json\s*/', '', $jsonResponse);
            $jsonResponse = preg_replace('/```\s*$/', '', $jsonResponse);
            
            // Remove all control characters and non-printable characters
            // Keep only printable ASCII and standard whitespace
            $jsonResponse = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/', '', $jsonResponse);
            
            // Alternative method: use mb_convert_encoding to clean
            $jsonResponse = mb_convert_encoding($jsonResponse, 'UTF-8', 'UTF-8');
            
            // Remove any BOM
            $jsonResponse = str_replace("\xEF\xBB\xBF", '', $jsonResponse);
            
            $jsonResponse = trim($jsonResponse);
            
            $result = json_decode($jsonResponse, true, 512, JSON_INVALID_UTF8_IGNORE);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Sentiment analysis JSON decode failed', [
                    'error' => json_last_error_msg(),
                    'response' => substr($jsonResponse, 0, 500),
                    'hex_dump' => bin2hex(substr($jsonResponse, 0, 100)),
                ]);
                
                // Return default structure
                return [
                    'sentiment' => 'neutral',
                    'confidence' => 50,
                    'lead_score' => 5,
                    'lead_quality' => 'cold',
                    'key_intents' => [],
                    'summary' => 'Unable to analyze transcript.',
                    'follow_up' => 'Manual review recommended',
                ];
            }
            
            // Validate and normalize the response
            return [
                'sentiment' => in_array($result['sentiment'] ?? '', ['positive', 'neutral', 'negative']) 
                    ? $result['sentiment'] 
                    : 'neutral',
                'confidence' => max(0, min(100, (int)($result['confidence'] ?? 50))),
                'lead_score' => max(1, min(10, (int)($result['lead_score'] ?? 5))),
                'lead_quality' => in_array($result['lead_quality'] ?? '', ['hot', 'warm', 'cold', 'not_interested'])
                    ? $result['lead_quality']
                    : 'cold',
                'key_intents' => is_array($result['key_intents'] ?? null) 
                    ? array_slice($result['key_intents'], 0, 10)  // Max 10 intents
                    : [],
                'summary' => substr($result['summary'] ?? 'No summary available', 0, 500),
                'follow_up' => substr($result['follow_up'] ?? '', 0, 255),
            ];
            
        } catch (\Exception $e) {
            Log::error('Sentiment analysis failed', [
                'error' => $e->getMessage(),
                'transcript_length' => strlen($transcript),
            ]);
            
            throw $e;
        }
    }

    /**
     * Build system prompt with campaign context
     */
    private function buildSystemPrompt(array $variables): string
    {
        $variableList = '';
        
        if (!empty($variables['contact'])) {
            $variableList .= "\nContact variables (different per contact): " . implode(', ', array_map(fn($v) => "{{{$v}}}", $variables['contact']));
        }

        if (!empty($variables['campaign'])) {
            $campaignVars = [];
            foreach ($variables['campaign'] as $key => $value) {
                $campaignVars[] = "{{{$key}}} (value: \"{$value}\")";
            }
            $variableList .= "\nCampaign variables (same for all): " . implode(', ', $campaignVars);
        }

        return <<<PROMPT
You are a professional campaign message writer. Create engaging, clear, and effective campaign messages for phone calls.

STRICT RULES:
1. Keep message under 250 characters (this is critical)
2. Use provided variables in the format {{variable_name}}
3. Variables MUST remain exactly as {{variable_name}} - do not modify them
4. Write in a friendly, professional tone
5. Make it conversational for phone calls
6. Include a clear call-to-action when appropriate
7. Be concise and direct

Available variables:{$variableList}

Return ONLY the message text, nothing else.
PROMPT;
    }

    /**
     * Call OpenRouter API without truncation (for structured JSON responses)
     */
    private function callApiRaw(string $systemPrompt, string $userPrompt): string
    {
        // Get list of models to try (fallback chain)
        $modelsToTry = !empty($this->fallbackModels) ? $this->fallbackModels : [$this->model];
        
        $lastException = null;
        
        // Try each model in sequence until one succeeds
        foreach ($modelsToTry as $index => $modelName) {
            try {
                Log::info('Attempting OpenRouter API call', [
                    'model' => $modelName,
                    'attempt' => $index + 1,
                    'total_models' => count($modelsToTry),
                ]);

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'HTTP-Referer' => $this->siteUrl,
                    'X-Title' => $this->appName,
                    'Content-Type' => 'application/json',
                ])->timeout(45)->post($this->apiUrl, [
                    'model' => $modelName,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $systemPrompt,
                        ],
                        [
                            'role' => 'user',
                            'content' => $userPrompt,
                        ],
                    ],
                    'max_tokens' => 2000,  // Higher token limit for JSON responses with multiple variants
                    'temperature' => $this->temperature,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $message = $data['choices'][0]['message']['content'] ?? '';

                    // Return raw message without truncation
                    $message = trim($message);

                    Log::info('OpenRouter API success (raw)', [
                        'model' => $modelName,
                        'attempt' => $index + 1,
                        'message_length' => strlen($message),
                    ]);

                    return $message;
                }

                // API returned error status, log and try next model
                Log::warning('OpenRouter API failed for model', [
                    'model' => $modelName,
                    'attempt' => $index + 1,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                
                $lastException = new \Exception("Model {$modelName} failed: " . $response->status());

            } catch (\Exception $e) {
                Log::warning('OpenRouter API exception for model', [
                    'model' => $modelName,
                    'attempt' => $index + 1,
                    'error' => $e->getMessage(),
                ]);
                
                $lastException = $e;
                
                // Continue to next model
                continue;
            }
        }

        // All models failed
        Log::error('All OpenRouter models failed', [
            'models_tried' => $modelsToTry,
            'last_error' => $lastException?->getMessage(),
        ]);

        throw new \Exception('AI service is currently unavailable.');
    }

    /**
     * Call OpenRouter API with retry logic and better error handling
     */
    private function callApi(string $systemPrompt, string $userPrompt): string
    {
        // Get list of models to try (fallback chain)
        $modelsToTry = !empty($this->fallbackModels) ? $this->fallbackModels : [$this->model];

        $lastException = null;
        $lastErrorType = 'unknown';

        // Try each model in sequence until one succeeds
        foreach ($modelsToTry as $index => $modelName) {
            // Retry each model up to 2 times with exponential backoff
            $maxRetries = 2;

            for ($retry = 0; $retry < $maxRetries; $retry++) {
                try {
                    // Exponential backoff: 0s, 2s, 4s
                    if ($retry > 0) {
                        $waitTime = $retry * 2;
                        Log::info("Retry attempt {$retry} after {$waitTime}s delay", ['model' => $modelName]);
                        sleep($waitTime);
                    }

                    Log::info('Attempting OpenRouter API call', [
                        'model' => $modelName,
                        'attempt' => $index + 1,
                        'retry' => $retry + 1,
                        'total_models' => count($modelsToTry),
                    ]);

                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $this->apiKey,
                        'HTTP-Referer' => $this->siteUrl,
                        'X-Title' => $this->appName,
                        'Content-Type' => 'application/json',
                    ])->timeout(45)->post($this->apiUrl, [
                        'model' => $modelName,
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => $systemPrompt,
                            ],
                            [
                                'role' => 'user',
                                'content' => $userPrompt,
                            ],
                        ],
                        'max_tokens' => $this->maxTokens,
                        'temperature' => $this->temperature,
                    ]);

                    if ($response->successful()) {
                        $data = $response->json();
                        $message = $data['choices'][0]['message']['content'] ?? '';

                        // Clean up the message
                        $message = trim($message);
                        $message = str_replace(['"', '"', '"'], '"', $message); // Normalize quotes

                        // Validate message is not empty
                        if (empty($message)) {
                            Log::warning('AI returned empty message, retrying', [
                                'model' => $modelName,
                                'attempt' => $index + 1,
                                'retry' => $retry + 1,
                            ]);

                            // Retry if we have retries left
                            if ($retry < $maxRetries - 1) {
                                continue;
                            } else {
                                throw new \Exception('AI returned empty message');
                            }
                        }

                        // Smart truncation - avoid breaking variables
                        if (strlen($message) > 250) {
                            $message = $this->smartTruncate($message, 250);
                        }

                        Log::info('OpenRouter API success', [
                            'model' => $modelName,
                            'attempt' => $index + 1,
                            'retry' => $retry + 1,
                            'message_length' => strlen($message),
                        ]);

                        return $message;
                    }

                    // API returned error status
                    $statusCode = $response->status();
                    $errorBody = $response->json();

                    // Determine error type
                    if ($statusCode === 429) {
                        $lastErrorType = 'rate_limit';
                    } elseif ($statusCode >= 500) {
                        $lastErrorType = 'server_error';
                    } else {
                        $lastErrorType = 'client_error';
                    }

                    Log::warning('OpenRouter API failed', [
                        'model' => $modelName,
                        'attempt' => $index + 1,
                        'retry' => $retry + 1,
                        'status' => $statusCode,
                        'error' => $errorBody['error']['message'] ?? $response->body(),
                    ]);

                    $lastException = new \Exception($errorBody['error']['message'] ?? "Model {$modelName} failed: HTTP {$statusCode}");

                    // If rate limited or server error, retry; otherwise move to next model
                    if (in_array($lastErrorType, ['rate_limit', 'server_error']) && $retry < $maxRetries - 1) {
                        continue; // Retry same model
                    } else {
                        break; // Try next model
                    }

                } catch (\Exception $e) {
                    Log::warning('OpenRouter API exception', [
                        'model' => $modelName,
                        'attempt' => $index + 1,
                        'retry' => $retry + 1,
                        'error' => $e->getMessage(),
                    ]);

                    $lastException = $e;
                    $lastErrorType = 'network_error';

                    // Retry on network errors
                    if ($retry < $maxRetries - 1) {
                        continue;
                    } else {
                        break; // Try next model after all retries
                    }
                }
            }
        }

        // All models and retries failed
        Log::error('All OpenRouter models failed', [
            'models_tried' => $modelsToTry,
            'error_type' => $lastErrorType,
            'last_error' => $lastException?->getMessage(),
        ]);

        // Return user-friendly error based on error type
        $userMessage = $this->getUserFriendlyError($lastErrorType, $lastException);
        throw new \Exception($userMessage);
    }

    /**
     * Get user-friendly error message based on error type
     */
    private function getUserFriendlyError(string $errorType, ?\Exception $exception): string
    {
        switch ($errorType) {
            case 'rate_limit':
                return 'AI service rate limit reached. Please wait 1 minute and try again, or write your message manually.';

            case 'server_error':
                return 'AI service is temporarily down. Please try again in a few moments or write your message manually.';

            case 'network_error':
                return 'Network connection issue. Please check your internet and try again.';

            case 'client_error':
                $message = $exception?->getMessage() ?? '';
                if (str_contains($message, 'api key') || str_contains($message, 'authentication')) {
                    return 'AI service configuration error. Please contact support.';
                }
                return 'Unable to process AI request. Please try a different prompt or write your message manually.';

            default:
                return 'AI service is currently unavailable. Please try again later or write your message manually.';
        }
    }

    /**
     * Smart truncation - avoid breaking variables
     */
    private function smartTruncate(string $message, int $maxLength): string
    {
        if (strlen($message) <= $maxLength) {
            return $message;
        }

        // Truncate to max length - 3 (for ...)
        $truncated = substr($message, 0, $maxLength - 3);

        // If we're in middle of {{variable}}, backtrack to before {{
        if (preg_match('/\{\{[^}]*$/', $truncated)) {
            $truncated = preg_replace('/\{\{[^}]*$/', '', $truncated);
        }

        return trim($truncated) . '...';
    }

    /**
     * Check if service is available
     */
    public function isAvailable(): bool
    {
        return !empty($this->apiKey) && !empty($this->apiUrl);
    }

    /**
     * Generate message variants for A/B testing
     */
    public function generateMessageVariants(string $baseMessage, string $description, array $variables = []): array
    {
        $variableList = '';
        if (!empty($variables)) {
            $variableList = "\n\nAvailable variables: " . implode(', ', array_map(fn($v) => "{{{$v}}}", $variables));
        }

        $systemPrompt = <<<PROMPT
You are an expert copywriter specializing in telemarketing and campaign messaging.

Generate 5 different message variants for A/B testing, each with a distinct tone and approach.

STRICT RULES:
1. Each message must be under 250 characters
2. Preserve all {{variables}} exactly as they appear
3. Create genuinely different approaches (not just minor word changes)
4. Make messages suitable for phone calls (conversational, natural)
5. Return ONLY valid JSON, nothing else

Tone variations to use:
- Professional: Formal, business-like
- Friendly: Warm, approachable, conversational
- Urgent: Time-sensitive, creates FOMO
- Educational: Informative, helpful
- Direct: Straightforward, no-nonsense
PROMPT;

        $userPrompt = <<<PROMPT
Base message/idea: {$baseMessage}

Description: {$description}{$variableList}

Generate 5 variants in this exact JSON format:
{
    "variants": [
        {
            "label": "A",
            "name": "Professional",
            "message": "Your message here with {{variables}}",
            "tone_description": "Brief explanation of this variant's approach"
        }
    ]
}

Ensure all variants are under 250 characters and preserve {{variables}}.
PROMPT;

        try {
            $jsonResponse = $this->callApiRaw($systemPrompt, $userPrompt);
            
            // Clean response
            $jsonResponse = preg_replace('/```json\s*/', '', $jsonResponse);
            $jsonResponse = preg_replace('/```\s*$/', '', $jsonResponse);
            $jsonResponse = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/', '', $jsonResponse);
            $jsonResponse = mb_convert_encoding($jsonResponse, 'UTF-8', 'UTF-8');
            $jsonResponse = str_replace("\xEF\xBB\xBF", '', $jsonResponse);
            $jsonResponse = trim($jsonResponse);
            
            $result = json_decode($jsonResponse, true, 512, JSON_INVALID_UTF8_IGNORE);
            
            if (json_last_error() !== JSON_ERROR_NONE || !isset($result['variants'])) {
                Log::warning('Message variants JSON decode failed', [
                    'error' => json_last_error_msg(),
                    'response' => substr($jsonResponse, 0, 500),
                ]);
                
                // Return single variant fallback
                return [
                    [
                        'label' => 'A',
                        'name' => 'Original',
                        'message' => substr($baseMessage, 0, 250),
                        'tone_description' => 'Original message',
                    ],
                ];
            }
            
            // Validate and return variants
            return array_map(function($variant) use ($baseMessage) {
                return [
                    'label' => $variant['label'] ?? 'A',
                    'name' => $variant['name'] ?? 'Variant',
                    'message' => substr($variant['message'] ?? $baseMessage, 0, 250),
                    'tone_description' => $variant['tone_description'] ?? '',
                ];
            }, array_slice($result['variants'], 0, 5));
            
        } catch (\Exception $e) {
            Log::error('Message variant generation failed', [
                'error' => $e->getMessage(),
            ]);
            
            // Return original as fallback
            return [
                [
                    'label' => 'A',
                    'name' => 'Original',
                    'message' => substr($baseMessage, 0, 250),
                    'tone_description' => 'Original message',
                ],
            ];
        }
    }

    /**
     * Clean and validate contact data
     */
    public function cleanContactData(array $contact): array
    {
        $systemPrompt = <<<PROMPT
You are a data quality expert specializing in contact information validation and cleaning.

Analyze contact data and return validation results with suggestions for corrections.

STRICT RULES:
1. Return ONLY valid JSON, nothing else
2. Be specific about issues found
3. Provide actionable suggestions
4. Calculate quality score (0-100)
5. Detect potential duplicates
PROMPT;

        $contactJson = json_encode($contact, JSON_PRETTY_PRINT);
        
        $userPrompt = <<<PROMPT
Analyze this contact data:

{$contactJson}

Return analysis in this exact JSON format:
{
    "quality_score": 85,
    "issues": [
        {"field": "phone_number", "issue": "Missing country code", "severity": "medium"},
        {"field": "name", "issue": "All lowercase", "severity": "low"}
    ],
    "suggestions": {
        "phone_number": "+1234567890",
        "first_name": "John",
        "last_name": "Doe",
        "company": "Acme Corp"
    },
    "duplicate_risk": "low|medium|high",
    "is_valid": true
}

Check for:
- Phone number format (add country code if missing, validate format)
- Name capitalization (proper case)
- Company name formatting
- Email validity
- Missing required fields
- Data consistency

Quality score calculation:
- 100: Perfect data
- 80-99: Minor issues
- 60-79: Several issues
- 40-59: Major issues
- 0-39: Invalid/unusable data
PROMPT;

        try {
            $jsonResponse = $this->callApiRaw($systemPrompt, $userPrompt);
            
            // Clean response
            $jsonResponse = preg_replace('/```json\s*/', '', $jsonResponse);
            $jsonResponse = preg_replace('/```\s*$/', '', $jsonResponse);
            $jsonResponse = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/', '', $jsonResponse);
            $jsonResponse = mb_convert_encoding($jsonResponse, 'UTF-8', 'UTF-8');
            $jsonResponse = str_replace("\xEF\xBB\xBF", '', $jsonResponse);
            $jsonResponse = trim($jsonResponse);
            
            $result = json_decode($jsonResponse, true, 512, JSON_INVALID_UTF8_IGNORE);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Contact cleaning JSON decode failed', [
                    'error' => json_last_error_msg(),
                    'response' => substr($jsonResponse, 0, 500),
                ]);
                
                // Return default validation
                return [
                    'quality_score' => 100,
                    'issues' => [],
                    'suggestions' => [],
                    'duplicate_risk' => 'low',
                    'is_valid' => true,
                ];
            }
            
            // Validate and normalize
            return [
                'quality_score' => max(0, min(100, (int)($result['quality_score'] ?? 100))),
                'issues' => is_array($result['issues'] ?? null) ? array_slice($result['issues'], 0, 10) : [],
                'suggestions' => is_array($result['suggestions'] ?? null) ? $result['suggestions'] : [],
                'duplicate_risk' => in_array($result['duplicate_risk'] ?? '', ['low', 'medium', 'high']) 
                    ? $result['duplicate_risk'] 
                    : 'low',
                'is_valid' => (bool)($result['is_valid'] ?? true),
            ];
            
        } catch (\Exception $e) {
            Log::error('Contact data cleaning failed', [
                'error' => $e->getMessage(),
            ]);
            
            // Return default validation
            return [
                'quality_score' => 100,
                'issues' => [],
                'suggestions' => [],
                'duplicate_risk' => 'low',
                'is_valid' => true,
            ];
        }
    }
}
