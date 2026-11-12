<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Call;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * AI-powered sentiment analysis and lead scoring for calls
 * Analyzes call transcripts to determine sentiment, lead quality, and key intents
 */
class CallSentimentAnalyzer
{
    private string $apiKey;
    private string $apiUrl;
    private array $fallbackModels;
    private int $maxRetries = 3;

    public function __construct()
    {
        $this->apiKey = config('openrouter.api_key');
        $this->apiUrl = config('openrouter.api_url');
        $this->fallbackModels = config('openrouter.fallback_models', []);
    }

    /**
     * Analyze call transcript and update call with sentiment data
     */
    public function analyzeCall(Call $call): array
    {
        // Check if call has transcript
        if (empty($call->transcript_text)) {
            return [
                'success' => false,
                'error' => 'No transcript available for analysis',
            ];
        }

        // Check if already analyzed recently
        if ($call->sentiment_analyzed_at && $call->sentiment_analyzed_at->gt(now()->subHours(1))) {
            return [
                'success' => true,
                'cached' => true,
                'message' => 'Using cached analysis',
            ];
        }

        try {
            // Perform AI analysis
            $analysis = $this->performSentimentAnalysis($call->transcript_text);

            if (!$analysis['success']) {
                return $analysis;
            }

            // Update call with analysis results
            $call->update([
                'sentiment' => $analysis['sentiment'],
                'sentiment_confidence' => $analysis['sentiment_confidence'],
                'lead_score' => $analysis['lead_score'],
                'lead_quality' => $analysis['lead_quality'],
                'ai_summary' => $analysis['summary'],
                'key_intents' => $analysis['key_intents'],
                'sentiment_analyzed_at' => now(),
            ]);

            Log::info('Call sentiment analyzed', [
                'call_id' => $call->id,
                'sentiment' => $analysis['sentiment'],
                'lead_quality' => $analysis['lead_quality'],
            ]);

            return [
                'success' => true,
                'analysis' => $analysis,
            ];
        } catch (\Exception $e) {
            Log::error('Call sentiment analysis failed', [
                'call_id' => $call->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Perform AI sentiment analysis on transcript
     */
    private function performSentimentAnalysis(string $transcript): array
    {
        $prompt = $this->buildAnalysisPrompt($transcript);

        // Try each model in fallback chain
        foreach ($this->fallbackModels as $model) {
            try {
                $response = $this->callOpenRouter($prompt, $model);

                if ($response['success']) {
                    return $this->parseAnalysisResponse($response['content']);
                }
            } catch (\Exception $e) {
                Log::warning("Model {$model} failed, trying next", [
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }

        throw new \Exception('All AI models failed');
    }

    /**
     * Build analysis prompt
     */
    private function buildAnalysisPrompt(string $transcript): string
    {
        return <<<PROMPT
Analyze this telemarketing call transcript and provide a JSON response with the following structure:

{
  "sentiment": "positive|neutral|negative",
  "sentiment_confidence": 85,
  "lead_score": 75,
  "lead_quality": "hot|warm|cold",
  "summary": "Brief 1-2 sentence summary of the call",
  "key_intents": ["interested", "callback", "not_interested", "price_concern", "competitor_mention", "decision_maker", "needs_info", "do_not_call"]
}

**Analysis Guidelines:**

**Sentiment** (positive/neutral/negative):
- Positive: Engaged, interested, friendly, asking questions
- Neutral: Polite but not enthusiastic, listening
- Negative: Hostile, annoyed, dismissive, rude

**Sentiment Confidence** (0-100):
How confident you are in the sentiment classification

**Lead Score** (0-100):
- 80-100: Hot lead - Very interested, ready to buy/schedule
- 60-79: Warm lead - Interested, needs nurturing
- 40-59: Cold lead - Low interest, but possible
- 0-39: Very cold - Not interested, wrong fit

**Lead Quality** (hot/warm/cold):
- Hot: Showing buying signals, asking about next steps, pricing
- Warm: Interested but not ready, needs follow-up
- Cold: Minimal interest or not qualified

**Summary**:
1-2 sentences capturing the essence of the conversation

**Key Intents** (select all that apply):
- interested: Shows interest in product/service
- callback: Requested callback or follow-up
- not_interested: Explicitly not interested
- price_concern: Mentioned price as concern
- competitor_mention: Mentioned competitors
- decision_maker: Is or mentioned the decision maker
- needs_info: Needs more information
- do_not_call: Requested to be removed from list

**Call Transcript:**
{$transcript}

Respond ONLY with valid JSON, no additional text.
PROMPT;
    }

    /**
     * Call OpenRouter API
     */
    private function callOpenRouter(string $prompt, string $model): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'HTTP-Referer' => config('openrouter.site_url'),
            'X-Title' => config('openrouter.app_name'),
            'Content-Type' => 'application/json',
        ])->timeout(30)->post($this->apiUrl, [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => 0.3, // Lower temperature for more consistent analysis
            'max_tokens' => 500,
        ]);

        if (!$response->successful()) {
            throw new \Exception("API request failed: " . $response->body());
        }

        $data = $response->json();

        return [
            'success' => true,
            'content' => $data['choices'][0]['message']['content'] ?? '',
            'model' => $model,
        ];
    }

    /**
     * Parse AI response
     */
    private function parseAnalysisResponse(string $content): array
    {
        // Extract JSON from response (in case there's extra text)
        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $content = $matches[0];
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Failed to parse AI response: ' . json_last_error_msg());
        }

        // Validate and normalize response
        return [
            'success' => true,
            'sentiment' => strtolower($data['sentiment'] ?? 'neutral'),
            'sentiment_confidence' => (int) ($data['sentiment_confidence'] ?? 50),
            'lead_score' => (int) ($data['lead_score'] ?? 50),
            'lead_quality' => strtolower($data['lead_quality'] ?? 'cold'),
            'summary' => $data['summary'] ?? 'No summary available',
            'key_intents' => $data['key_intents'] ?? [],
        ];
    }

    /**
     * Batch analyze multiple calls
     */
    public function batchAnalyzeCalls(array $callIds): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($callIds as $callId) {
            $call = Call::find($callId);

            if (!$call) {
                $results['skipped']++;
                continue;
            }

            $result = $this->analyzeCall($call);

            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = [
                    'call_id' => $callId,
                    'error' => $result['error'] ?? 'Unknown error',
                ];
            }
        }

        return $results;
    }

    /**
     * Get hot leads from campaign
     */
    public function getHotLeads(int $campaignId, int $limit = 50): array
    {
        return Call::where('campaign_id', $campaignId)
            ->whereNotNull('sentiment_analyzed_at')
            ->where('lead_quality', 'hot')
            ->orderByDesc('lead_score')
            ->orderByDesc('ended_at')
            ->limit($limit)
            ->with(['campaignContact'])
            ->get()
            ->map(function ($call) {
                return [
                    'call_id' => $call->id,
                    'contact_id' => $call->campaign_contact_id,
                    'contact_name' => $call->campaignContact?->first_name . ' ' . $call->campaignContact?->last_name,
                    'phone_number' => $call->to_number,
                    'lead_score' => $call->lead_score,
                    'sentiment' => $call->sentiment,
                    'summary' => $call->ai_summary,
                    'key_intents' => $call->key_intents,
                    'called_at' => $call->ended_at,
                ];
            })
            ->toArray();
    }

    /**
     * Get sentiment statistics for campaign
     */
    public function getCampaignSentimentStats(int $campaignId): array
    {
        $calls = Call::where('campaign_id', $campaignId)
            ->whereNotNull('sentiment_analyzed_at')
            ->get();

        if ($calls->isEmpty()) {
            return [
                'total_analyzed' => 0,
                'sentiment_breakdown' => [
                    'positive' => 0,
                    'neutral' => 0,
                    'negative' => 0,
                ],
                'lead_quality_breakdown' => [
                    'hot' => 0,
                    'warm' => 0,
                    'cold' => 0,
                ],
                'average_lead_score' => 0,
                'top_intents' => [],
            ];
        }

        // Sentiment breakdown
        $sentimentBreakdown = $calls->groupBy('sentiment')->map->count();

        // Lead quality breakdown
        $leadQualityBreakdown = $calls->groupBy('lead_quality')->map->count();

        // Average lead score
        $avgLeadScore = $calls->avg('lead_score');

        // Top intents
        $allIntents = $calls->pluck('key_intents')->flatten()->filter();
        $topIntents = $allIntents->countBy()->sortDesc()->take(5)->toArray();

        return [
            'total_analyzed' => $calls->count(),
            'sentiment_breakdown' => [
                'positive' => $sentimentBreakdown->get('positive', 0),
                'neutral' => $sentimentBreakdown->get('neutral', 0),
                'negative' => $sentimentBreakdown->get('negative', 0),
            ],
            'lead_quality_breakdown' => [
                'hot' => $leadQualityBreakdown->get('hot', 0),
                'warm' => $leadQualityBreakdown->get('warm', 0),
                'cold' => $leadQualityBreakdown->get('cold', 0),
            ],
            'average_lead_score' => round($avgLeadScore, 1),
            'top_intents' => $topIntents,
        ];
    }

    /**
     * Get intent distribution across campaign
     */
    public function getIntentAnalysis(int $campaignId): array
    {
        $calls = Call::where('campaign_id', $campaignId)
            ->whereNotNull('key_intents')
            ->get();

        $intents = $calls->pluck('key_intents')->flatten()->filter();

        return $intents->countBy()->map(function ($count) use ($calls) {
            return [
                'count' => $count,
                'percentage' => round(($count / $calls->count()) * 100, 1),
            ];
        })->sortDesc()->toArray();
    }
}
