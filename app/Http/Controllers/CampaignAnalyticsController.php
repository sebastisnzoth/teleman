<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignContact;
use App\Models\Call;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CampaignAnalyticsController extends Controller
{
    /**
     * Get comprehensive analytics for a campaign
     */
    public function show(Campaign $campaign): JsonResponse
    {
        return response()->json([
            'overview' => $this->getOverview($campaign),
            'dtmf_analysis' => $this->getDtmfAnalysis($campaign),
            'time_analysis' => $this->getTimeAnalysis($campaign),
            'status_breakdown' => $this->getStatusBreakdown($campaign),
            'performance_metrics' => $this->getPerformanceMetrics($campaign),
            'cost_analysis' => $this->getCostAnalysis($campaign),
            'real_time' => $this->getRealTimeData($campaign),
        ]);
    }

    /**
     * Get campaign overview statistics
     */
    private function getOverview(Campaign $campaign): array
    {
        return [
            'total_contacts' => $campaign->total_contacts,
            'total_called' => $campaign->total_called,
            'total_answered' => $campaign->total_answered,
            'total_failed' => $campaign->total_failed,
            'in_progress' => $campaign->campaignContacts()->whereIn('status', ['pending', 'queued', 'calling'])->count(),
            'completed' => $campaign->campaignContacts()->where('status', 'completed')->count(),
            'opted_out' => $campaign->campaignContacts()->where('opted_out', true)->count(),
            
            // Rates
            'answer_rate' => $campaign->total_called > 0 
                ? round(($campaign->total_answered / $campaign->total_called) * 100, 2) 
                : 0,
            'completion_rate' => $campaign->total_contacts > 0 
                ? round(($campaign->total_answered / $campaign->total_contacts) * 100, 2) 
                : 0,
            'failure_rate' => $campaign->total_called > 0 
                ? round(($campaign->total_failed / $campaign->total_called) * 100, 2) 
                : 0,
            'opt_out_rate' => $campaign->total_contacts > 0 
                ? round(($campaign->campaignContacts()->where('opted_out', true)->count() / $campaign->total_contacts) * 100, 2) 
                : 0,
        ];
    }

    /**
     * Get DTMF response analysis
     */
    private function getDtmfAnalysis(Campaign $campaign): array
    {
        if (!$campaign->enable_dtmf) {
            return ['enabled' => false];
        }

        $responses = DB::table('campaign_contacts')
            ->select('dtmf_response', DB::raw('COUNT(*) as count'))
            ->where('campaign_id', $campaign->id)
            ->whereNotNull('dtmf_response')
            ->groupBy('dtmf_response')
            ->orderByDesc('count')
            ->get();

        $totalResponses = $responses->sum('count');

        return [
            'enabled' => true,
            'total_responses' => $totalResponses,
            'response_rate' => $campaign->total_answered > 0 
                ? round(($totalResponses / $campaign->total_answered) * 100, 2) 
                : 0,
            'breakdown' => $responses->map(function ($item) use ($totalResponses) {
                return [
                    'key' => $item->dtmf_response,
                    'count' => $item->count,
                    'percentage' => $totalResponses > 0 
                        ? round(($item->count / $totalResponses) * 100, 2) 
                        : 0,
                ];
            })->toArray(),
        ];
    }

    /**
     * Get time-based analysis
     */
    private function getTimeAnalysis(Campaign $campaign): array
    {
        // Calls by hour
        $callsByHour = DB::table('calls')
            ->select(
                DB::raw('HOUR(started_at) as hour'),
                DB::raw('COUNT(*) as count'),
                DB::raw('AVG(duration) as avg_duration')
            )
            ->where('campaign_id', $campaign->id)
            ->whereNotNull('started_at')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->map(function ($item) {
                return [
                    'hour' => (int) $item->hour,
                    'count' => (int) $item->count,
                    'avg_duration' => round((float) $item->avg_duration, 2),
                ];
            })
            ->toArray();

        // Average call duration
        $avgDuration = DB::table('calls')
            ->where('campaign_id', $campaign->id)
            ->whereNotNull('duration_seconds')
            ->avg('duration_seconds');

        // Total duration
        $totalDuration = DB::table('calls')
            ->where('campaign_id', $campaign->id)
            ->whereNotNull('duration_seconds')
            ->sum('duration_seconds');

        // Campaign duration
        $campaignDuration = null;
        if ($campaign->started_at && $campaign->completed_at) {
            $campaignDuration = $campaign->started_at->diffInMinutes($campaign->completed_at);
        } elseif ($campaign->started_at) {
            $campaignDuration = $campaign->started_at->diffInMinutes(now());
        }

        return [
            'calls_by_hour' => $callsByHour,
            'avg_call_duration' => round($avgDuration ?? 0, 2),
            'total_duration_seconds' => round($totalDuration ?? 0, 2),
            'total_duration_minutes' => round(($totalDuration ?? 0) / 60, 2),
            'campaign_duration_minutes' => $campaignDuration,
            'calls_per_minute' => $campaignDuration > 0 
                ? round($campaign->total_called / $campaignDuration, 2) 
                : 0,
        ];
    }

    /**
     * Get status breakdown
     */
    private function getStatusBreakdown(Campaign $campaign): array
    {
        $statuses = DB::table('campaign_contacts')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->where('campaign_id', $campaign->id)
            ->groupBy('status')
            ->get();

        $totalContacts = $campaign->total_contacts;

        return $statuses->map(function ($item) use ($totalContacts) {
            return [
                'status' => $item->status,
                'count' => $item->count,
                'percentage' => $totalContacts > 0 
                    ? round(($item->count / $totalContacts) * 100, 2) 
                    : 0,
            ];
        })->toArray();
    }

    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics(Campaign $campaign): array
    {
        // Call status distribution
        $callStatuses = DB::table('calls')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->where('campaign_id', $campaign->id)
            ->groupBy('status')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->status => $item->count];
            })
            ->toArray();

        // Average attempts before success
        $avgAttempts = DB::table('campaign_contacts')
            ->where('campaign_id', $campaign->id)
            ->where('status', 'completed')
            ->avg('call_attempts');

        // First call success rate
        $firstCallSuccess = DB::table('campaign_contacts')
            ->where('campaign_id', $campaign->id)
            ->where('status', 'completed')
            ->where('call_attempts', 1)
            ->count();

        $totalCompleted = $campaign->campaignContacts()->where('status', 'completed')->count();

        return [
            'call_statuses' => $callStatuses,
            'avg_attempts_to_complete' => round($avgAttempts ?? 0, 2),
            'first_call_success_count' => $firstCallSuccess,
            'first_call_success_rate' => $totalCompleted > 0 
                ? round(($firstCallSuccess / $totalCompleted) * 100, 2) 
                : 0,
            'retry_effectiveness' => $totalCompleted > 0 && $avgAttempts > 1
                ? round((($totalCompleted - $firstCallSuccess) / $totalCompleted) * 100, 2)
                : 0,
        ];
    }

    /**
     * Get cost analysis
     */
    private function getCostAnalysis(Campaign $campaign): array
    {
        // Twilio pricing (approximate)
        $costPerMinute = 0.0130; // $0.013 per minute for outbound calls (average)
        $costPerCall = 0.0085; // Base cost per call

        $totalDuration = DB::table('calls')
            ->where('campaign_id', $campaign->id)
            ->whereNotNull('duration_seconds')
            ->sum('duration_seconds');

        $totalCalls = $campaign->total_called;
        $durationMinutes = ($totalDuration ?? 0) / 60;

        $estimatedCost = ($totalCalls * $costPerCall) + ($durationMinutes * $costPerMinute);

        return [
            'estimated_total_cost' => round($estimatedCost, 2),
            'cost_per_completed_call' => $campaign->total_answered > 0 
                ? round($estimatedCost / $campaign->total_answered, 2) 
                : 0,
            'cost_per_contact' => $campaign->total_contacts > 0 
                ? round($estimatedCost / $campaign->total_contacts, 2) 
                : 0,
            'total_duration_minutes' => round($durationMinutes, 2),
            'note' => 'Estimated cost based on average Twilio rates',
        ];
    }

    /**
     * Get real-time data
     */
    private function getRealTimeData(Campaign $campaign): array
    {
        return [
            'active_calls' => Call::where('campaign_id', $campaign->id)
                ->whereIn('status', ['initiated', 'ringing', 'in-progress'])
                ->count(),
            'queued_contacts' => $campaign->campaignContacts()
                ->where('status', 'queued')
                ->count(),
            'pending_contacts' => $campaign->campaignContacts()
                ->where('status', 'pending')
                ->count(),
            'calling_contacts' => $campaign->campaignContacts()
                ->where('status', 'calling')
                ->count(),
            'queue_depth' => DB::table('jobs')
                ->where('queue', 'campaign-calls')
                ->count(),
            'is_active' => $campaign->status === 'active',
            'progress_percentage' => $campaign->total_contacts > 0 
                ? round((($campaign->total_called) / $campaign->total_contacts) * 100, 2) 
                : 0,
        ];
    }

    /**
     * Get analytics summary for all user campaigns
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id ?? 1;

        $campaigns = Campaign::where('user_id', $userId)
            ->with('campaignContacts')
            ->latest()
            ->get();

        $summary = [
            'total_campaigns' => $campaigns->count(),
            'active_campaigns' => $campaigns->where('status', 'active')->count(),
            'total_contacts_processed' => $campaigns->sum('total_called'),
            'total_successful_calls' => $campaigns->sum('total_answered'),
            'overall_success_rate' => $campaigns->sum('total_called') > 0 
                ? round(($campaigns->sum('total_answered') / $campaigns->sum('total_called')) * 100, 2) 
                : 0,
            'campaigns_by_status' => $campaigns->groupBy('status')->map->count(),
            'recent_campaigns' => $campaigns->take(5)->map(function ($campaign) {
                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'status' => $campaign->status,
                    'total_contacts' => $campaign->total_contacts,
                    'total_called' => $campaign->total_called,
                    'total_answered' => $campaign->total_answered,
                    'answer_rate' => $campaign->total_called > 0 
                        ? round(($campaign->total_answered / $campaign->total_called) * 100, 2) 
                        : 0,
                    'started_at' => $campaign->started_at?->toISOString(),
                    'completed_at' => $campaign->completed_at?->toISOString(),
                ];
            }),
        ];

        return response()->json($summary);
    }

    /**
     * Export campaign analytics to CSV
     */
    public function export(Campaign $campaign)
    {
        $contacts = $campaign->campaignContacts()
            ->with('call')
            ->get();

        $filename = "campaign-{$campaign->id}-export-" . now()->format('Y-m-d-His') . ".csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($contacts) {
            $file = fopen('php://output', 'w');

            // Header row
            fputcsv($file, [
                'Phone Number',
                'First Name',
                'Last Name',
                'Email',
                'Company',
                'Status',
                'Call Attempts',
                'DTMF Response',
                'Last Call At',
                'Call Duration (seconds)',
                'Call Status',
                'Opted Out',
            ]);

            // Data rows
            foreach ($contacts as $contact) {
                fputcsv($file, [
                    $contact->phone_number,
                    $contact->first_name,
                    $contact->last_name,
                    $contact->email,
                    $contact->company,
                    $contact->status,
                    $contact->call_attempts,
                    $contact->dtmf_response,
                    $contact->last_call_at?->toDateTimeString(),
                    $contact->call?->duration,
                    $contact->call?->status,
                    $contact->opted_out ? 'Yes' : 'No',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
