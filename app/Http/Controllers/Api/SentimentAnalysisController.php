<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Campaign;
use App\Services\CallSentimentAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SentimentAnalysisController extends Controller
{
    public function __construct(
        private CallSentimentAnalyzer $analyzer
    ) {}

    /**
     * Analyze a single call
     * POST /api/v1/calls/{call}/analyze-sentiment
     */
    public function analyzeCall(Call $call): JsonResponse
    {
        $this->authorize('view', $call);

        $result = $this->analyzer->analyzeCall($call);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Analysis failed',
            ], 422);
        }

        // Refresh call to get updated data
        $call->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Call analyzed successfully',
            'data' => [
                'sentiment' => $call->sentiment,
                'sentiment_confidence' => $call->sentiment_confidence,
                'lead_score' => $call->lead_score,
                'lead_quality' => $call->lead_quality,
                'summary' => $call->ai_summary,
                'key_intents' => $call->key_intents,
                'analyzed_at' => $call->sentiment_analyzed_at,
            ],
        ]);
    }

    /**
     * Batch analyze campaign calls
     * POST /api/v1/campaigns/{campaign}/analyze-sentiment
     */
    public function analyzeCampaign(Campaign $campaign, Request $request): JsonResponse
    {
        $this->authorize('view', $campaign);

        $validated = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:500',
            'reanalyze' => 'sometimes|boolean',
        ]);

        $limit = $validated['limit'] ?? 100;
        $reanalyze = $validated['reanalyze'] ?? false;

        // Get calls to analyze
        $query = Call::where('campaign_id', $campaign->id)
            ->whereNotNull('transcript_text')
            ->where('transcript_text', '!=', '');

        if (!$reanalyze) {
            // Only unanalyzed calls
            $query->whereNull('sentiment_analyzed_at');
        }

        $callIds = $query->orderByDesc('ended_at')
            ->limit($limit)
            ->pluck('id')
            ->toArray();

        if (empty($callIds)) {
            return response()->json([
                'success' => true,
                'message' => 'No calls to analyze',
                'data' => [
                    'analyzed' => 0,
                    'skipped' => 0,
                    'failed' => 0,
                ],
            ]);
        }

        // Dispatch batch analysis
        $results = $this->analyzer->batchAnalyzeCalls($callIds);

        return response()->json([
            'success' => true,
            'message' => "Analyzed {$results['success']} calls",
            'data' => [
                'analyzed' => $results['success'],
                'failed' => $results['failed'],
                'skipped' => $results['skipped'],
                'errors' => $results['errors'],
            ],
        ]);
    }

    /**
     * Get hot leads for campaign
     * GET /api/v1/campaigns/{campaign}/hot-leads
     */
    public function getHotLeads(Campaign $campaign, Request $request): JsonResponse
    {
        $this->authorize('view', $campaign);

        $validated = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:200',
        ]);

        $limit = $validated['limit'] ?? 50;

        $hotLeads = $this->analyzer->getHotLeads($campaign->id, $limit);

        return response()->json([
            'success' => true,
            'data' => [
                'hot_leads' => $hotLeads,
                'count' => count($hotLeads),
            ],
        ]);
    }

    /**
     * Get sentiment statistics for campaign
     * GET /api/v1/campaigns/{campaign}/sentiment-stats
     */
    public function getSentimentStats(Campaign $campaign): JsonResponse
    {
        $this->authorize('view', $campaign);

        $stats = $this->analyzer->getCampaignSentimentStats($campaign->id);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get intent analysis for campaign
     * GET /api/v1/campaigns/{campaign}/intent-analysis
     */
    public function getIntentAnalysis(Campaign $campaign): JsonResponse
    {
        $this->authorize('view', $campaign);

        $intents = $this->analyzer->getIntentAnalysis($campaign->id);

        return response()->json([
            'success' => true,
            'data' => [
                'intents' => $intents,
            ],
        ]);
    }

    /**
     * Get all hot leads across user's campaigns
     * GET /api/v1/hot-leads
     */
    public function getAllHotLeads(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:200',
        ]);

        $limit = $validated['limit'] ?? 50;

        // Get user's campaign IDs
        $campaignIds = Campaign::where('user_id', $user->id)->pluck('id');

        // Get hot leads across all campaigns
        $hotLeads = Call::whereIn('campaign_id', $campaignIds)
            ->whereNotNull('sentiment_analyzed_at')
            ->where('lead_quality', 'hot')
            ->orderByDesc('lead_score')
            ->orderByDesc('ended_at')
            ->limit($limit)
            ->with(['campaign', 'campaignContact'])
            ->get()
            ->map(function ($call) {
                return [
                    'call_id' => $call->id,
                    'campaign_id' => $call->campaign_id,
                    'campaign_name' => $call->campaign?->name,
                    'contact_id' => $call->campaign_contact_id,
                    'contact_name' => $call->campaignContact?->first_name . ' ' . $call->campaignContact?->last_name,
                    'phone_number' => $call->to_number,
                    'lead_score' => $call->lead_score,
                    'sentiment' => $call->sentiment,
                    'summary' => $call->ai_summary,
                    'key_intents' => $call->key_intents,
                    'called_at' => $call->ended_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'hot_leads' => $hotLeads,
                'count' => $hotLeads->count(),
            ],
        ]);
    }

    /**
     * Get sentiment trends over time
     * GET /api/v1/campaigns/{campaign}/sentiment-trends
     */
    public function getSentimentTrends(Campaign $campaign, Request $request): JsonResponse
    {
        $this->authorize('view', $campaign);

        $validated = $request->validate([
            'period' => 'sometimes|in:day,week,month',
        ]);

        $period = $validated['period'] ?? 'day';

        // Group by date
        $groupBy = match($period) {
            'day' => 'DATE(ended_at)',
            'week' => 'YEARWEEK(ended_at)',
            'month' => 'DATE_FORMAT(ended_at, "%Y-%m")',
        };

        $trends = Call::where('campaign_id', $campaign->id)
            ->whereNotNull('sentiment_analyzed_at')
            ->selectRaw("{$groupBy} as period, sentiment, COUNT(*) as count, AVG(lead_score) as avg_score")
            ->groupBy('period', 'sentiment')
            ->orderBy('period')
            ->get()
            ->groupBy('period')
            ->map(function ($items) {
                return [
                    'positive' => $items->where('sentiment', 'positive')->sum('count'),
                    'neutral' => $items->where('sentiment', 'neutral')->sum('count'),
                    'negative' => $items->where('sentiment', 'negative')->sum('count'),
                    'avg_score' => round($items->avg('avg_score'), 1),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'trends' => $trends,
                'period' => $period,
            ],
        ]);
    }
}
