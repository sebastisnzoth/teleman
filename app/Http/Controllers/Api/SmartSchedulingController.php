<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\CampaignContact;
use App\Services\CallSchedulingOptimizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Smart Call Scheduling API
 * Provides AI-powered optimal call time recommendations
 */
class SmartSchedulingController extends Controller
{
    public function __construct(
        private CallSchedulingOptimizer $scheduler
    ) {}

    /**
     * Get optimal call time for a contact
     * 
     * GET /api/v1/contacts/{contact}/optimal-time
     */
    public function getContactOptimalTime(Contact $contact): JsonResponse
    {
        Gate::authorize('view', $contact);

        try {
            $recommendation = $this->scheduler->getOptimalCallTime($contact);

            // Update contact with recommendation
            $contact->update([
                'optimal_call_time' => $recommendation['optimal_time'],
                'optimal_call_confidence' => $recommendation['confidence'],
                'timezone' => $recommendation['timezone'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $recommendation,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get optimal call time', [
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate optimal call time',
            ], 500);
        }
    }

    /**
     * Get campaign optimal times analysis
     * 
     * GET /api/v1/campaigns/{campaign}/optimal-times
     */
    public function getCampaignOptimalTimes(Campaign $campaign): JsonResponse
    {
        Gate::authorize('view', $campaign);

        try {
            $analysis = $this->scheduler->getCampaignOptimalTimes($campaign->id);

            return response()->json([
                'success' => true,
                'data' => $analysis,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get campaign optimal times', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to analyze campaign timing',
            ], 500);
        }
    }

    /**
     * Optimize schedule for all campaign contacts
     * 
     * POST /api/v1/campaigns/{campaign}/optimize-schedule
     */
    public function optimizeCampaignSchedule(Request $request, Campaign $campaign): JsonResponse
    {
        Gate::authorize('update', $campaign);

        $request->validate([
            'apply' => 'boolean',
            'limit' => 'integer|min:1|max:1000',
        ]);

        try {
            $contacts = $campaign->campaignContacts()
                ->limit($request->input('limit', 100))
                ->get();

            $optimizations = [];
            $updated = 0;

            foreach ($contacts as $contact) {
                $recommendation = $this->scheduler->getOptimalCallTime($contact);
                
                $optimizations[] = [
                    'contact_id' => $contact->id,
                    'phone_number' => $contact->phone_number,
                    'optimal_time' => $recommendation['optimal_time'],
                    'confidence' => $recommendation['confidence'],
                    'reasoning' => $recommendation['reasoning'],
                ];

                // Apply if requested
                if ($request->input('apply', false)) {
                    $contact->update([
                        'scheduled_call_time' => $recommendation['optimal_time'],
                        'timezone' => $recommendation['timezone'],
                    ]);
                    $updated++;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'optimizations' => $optimizations,
                    'total_analyzed' => count($optimizations),
                    'total_updated' => $updated,
                ],
                'message' => $request->input('apply')
                    ? "Optimized schedule for {$updated} contacts"
                    : "Generated {$updated} recommendations (not applied)",
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to optimize campaign schedule', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to optimize campaign schedule',
            ], 500);
        }
    }

    /**
     * Get timezone-adjusted schedule for campaign
     * 
     * GET /api/v1/campaigns/{campaign}/timezone-schedule
     */
    public function getTimezoneSchedule(Campaign $campaign): JsonResponse
    {
        Gate::authorize('view', $campaign);

        try {
            $schedule = $this->scheduler->getTimezoneAdjustedSchedule($campaign->id);

            return response()->json([
                'success' => true,
                'data' => $schedule,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get timezone schedule', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate timezone schedule',
            ], 500);
        }
    }

    /**
     * Batch optimize multiple contacts
     * 
     * POST /api/v1/contacts/batch-optimize
     */
    public function batchOptimizeContacts(Request $request): JsonResponse
    {
        $request->validate([
            'contact_ids' => 'required|array|max:100',
            'contact_ids.*' => 'integer|exists:contacts,id',
        ]);

        try {
            $contactIds = $request->input('contact_ids');
            
            // Verify all contacts belong to the user
            $contacts = Contact::whereIn('id', $contactIds)
                ->where('user_id', auth()->id())
                ->pluck('id')
                ->toArray();

            if (count($contacts) !== count($contactIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some contacts do not belong to you',
                ], 403);
            }

            $results = $this->scheduler->batchOptimizeContacts($contacts);

            return response()->json([
                'success' => true,
                'data' => $results,
                'total_optimized' => count($results),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to batch optimize contacts', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to optimize contacts',
            ], 500);
        }
    }

    /**
     * Get answer rate patterns for analytics
     * 
     * GET /api/v1/analytics/answer-rate-patterns
     */
    public function getAnswerRatePatterns(Request $request): JsonResponse
    {
        $request->validate([
            'campaign_id' => 'nullable|integer|exists:campaigns,id',
        ]);

        try {
            $campaignId = $request->input('campaign_id');
            
            // Authorize if campaign specified
            if ($campaignId) {
                $campaign = Campaign::findOrFail($campaignId);
                Gate::authorize('view', $campaign);
                $analysis = $this->scheduler->getCampaignOptimalTimes($campaignId);
            } else {
                // Get patterns across all user's campaigns
                $campaigns = Campaign::where('user_id', auth()->id())->pluck('id');
                $allPatterns = [];
                
                foreach ($campaigns as $cid) {
                    $pattern = $this->scheduler->getCampaignOptimalTimes($cid);
                    $allPatterns[] = $pattern;
                }
                
                $analysis = $this->aggregatePatterns($allPatterns);
            }

            return response()->json([
                'success' => true,
                'data' => $analysis,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get answer rate patterns', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to analyze answer rate patterns',
            ], 500);
        }
    }

    /**
     * Enable smart scheduling for a campaign
     * 
     * POST /api/v1/campaigns/{campaign}/enable-smart-scheduling
     */
    public function enableSmartScheduling(Campaign $campaign): JsonResponse
    {
        Gate::authorize('update', $campaign);

        try {
            $campaign->update(['use_smart_scheduling' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Smart scheduling enabled for campaign',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to enable smart scheduling', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to enable smart scheduling',
            ], 500);
        }
    }

    /**
     * Disable smart scheduling for a campaign
     * 
     * POST /api/v1/campaigns/{campaign}/disable-smart-scheduling
     */
    public function disableSmartScheduling(Campaign $campaign): JsonResponse
    {
        Gate::authorize('update', $campaign);

        try {
            $campaign->update(['use_smart_scheduling' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Smart scheduling disabled for campaign',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to disable smart scheduling', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to disable smart scheduling',
            ], 500);
        }
    }

    /**
     * Aggregate patterns from multiple campaigns
     */
    private function aggregatePatterns(array $patterns): array
    {
        if (empty($patterns)) {
            return [
                'best_hours' => [],
                'best_days' => [],
                'recommendations' => [],
            ];
        }

        // Combine all hour statistics
        $allHours = [];
        foreach ($patterns as $pattern) {
            foreach ($pattern['best_hours'] ?? [] as $hour) {
                $h = $hour['hour'];
                if (!isset($allHours[$h])) {
                    $allHours[$h] = [
                        'hour' => $h,
                        'hour_12' => $hour['hour_12'],
                        'total_calls' => 0,
                        'answered_calls' => 0,
                    ];
                }
                $allHours[$h]['total_calls'] += $hour['total_calls'];
                $allHours[$h]['answered_calls'] += $hour['answered_calls'];
            }
        }

        // Calculate aggregate answer rates
        foreach ($allHours as &$hour) {
            $hour['answer_rate'] = $hour['total_calls'] > 0
                ? round(($hour['answered_calls'] / $hour['total_calls']) * 100, 1)
                : 0;
        }

        // Sort by answer rate
        usort($allHours, fn($a, $b) => $b['answer_rate'] <=> $a['answer_rate']);

        // Combine day statistics similarly
        $allDays = [];
        foreach ($patterns as $pattern) {
            foreach ($pattern['best_days'] ?? [] as $day) {
                $d = $day['day'];
                if (!isset($allDays[$d])) {
                    $allDays[$d] = [
                        'day' => $d,
                        'day_name' => $day['day_name'],
                        'total_calls' => 0,
                        'answered_calls' => 0,
                    ];
                }
                $allDays[$d]['total_calls'] += $day['total_calls'];
                $allDays[$d]['answered_calls'] += $day['answered_calls'];
            }
        }

        foreach ($allDays as &$day) {
            $day['answer_rate'] = $day['total_calls'] > 0
                ? round(($day['answered_calls'] / $day['total_calls']) * 100, 1)
                : 0;
        }

        usort($allDays, fn($a, $b) => $b['answer_rate'] <=> $a['answer_rate']);

        return [
            'best_hours' => array_slice($allHours, 0, 5),
            'best_days' => array_slice($allDays, 0, 3),
            'recommendations' => [
                [
                    'type' => 'aggregate',
                    'priority' => 'high',
                    'message' => 'Aggregated patterns across all campaigns',
                    'data' => [
                        'total_campaigns' => count($patterns),
                        'best_hour' => $allHours[0] ?? null,
                        'best_day' => $allDays[0] ?? null,
                    ],
                ],
            ],
        ];
    }
}
