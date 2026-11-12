<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Call;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /**
     * Get comprehensive analytics for a campaign
     */
    public function getCampaignAnalytics(Campaign $campaign): array
    {
        return [
            'overview' => $this->getCampaignOverview($campaign),
            'status_distribution' => $this->getStatusDistribution($campaign),
            'hourly_stats' => $this->getHourlyStats($campaign),
            'daily_stats' => $this->getDailyStats($campaign),
            'performance_metrics' => $this->getPerformanceMetrics($campaign),
        ];
    }

    /**
     * Get campaign overview statistics
     */
    public function getCampaignOverview(Campaign $campaign): array
    {
        $calls = $campaign->calls();

        $totalCalls = $calls->count();
        $answeredCalls = $calls->where('status', 'completed')->count();
        $failedCalls = $calls->whereIn('status', ['failed', 'busy', 'no-answer'])->count();
        $inProgressCalls = $calls->whereIn('status', ['initiated', 'ringing', 'in-progress'])->count();

        $avgDuration = $calls->where('status', 'completed')
            ->whereNotNull('duration_seconds')
            ->avg('duration_seconds') ?? 0;

        $totalDuration = $calls->where('status', 'completed')
            ->whereNotNull('duration_seconds')
            ->sum('duration_seconds') ?? 0;

        $answerRate = $totalCalls > 0 ? round(($answeredCalls / $totalCalls) * 100, 2) : 0;
        $completionRate = $campaign->total_contacts > 0 
            ? round(($campaign->total_called / $campaign->total_contacts) * 100, 2) 
            : 0;

        return [
            'total_contacts' => $campaign->total_contacts,
            'total_calls' => $totalCalls,
            'answered_calls' => $answeredCalls,
            'failed_calls' => $failedCalls,
            'in_progress_calls' => $inProgressCalls,
            'answer_rate' => $answerRate,
            'completion_rate' => $completionRate,
            'average_duration' => round($avgDuration, 2),
            'total_duration' => $totalDuration,
            'total_duration_formatted' => $this->formatDuration($totalDuration),
        ];
    }

    /**
     * Get call status distribution
     */
    public function getStatusDistribution(Campaign $campaign): array
    {
        $distribution = $campaign->calls()
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(function ($item) {
                return [
                    'status' => $item->status,
                    'count' => $item->count,
                    'label' => ucfirst(str_replace('-', ' ', $item->status)),
                ];
            })
            ->toArray();

        return $distribution;
    }

    /**
     * Get hourly call statistics
     */
    public function getHourlyStats(Campaign $campaign): array
    {
        $stats = $campaign->calls()
            ->select(
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('count(*) as total_calls'),
                DB::raw('sum(case when status = "completed" then 1 else 0 end) as answered_calls'),
                DB::raw('avg(case when status = "completed" then duration_seconds else null end) as avg_duration')
            )
            ->groupBy(DB::raw('HOUR(created_at)'))
            ->orderBy('hour')
            ->get()
            ->map(function ($item) {
                return [
                    'hour' => $item->hour,
                    'label' => sprintf('%02d:00', $item->hour),
                    'total_calls' => $item->total_calls,
                    'answered_calls' => $item->answered_calls,
                    'answer_rate' => $item->total_calls > 0 
                        ? round(($item->answered_calls / $item->total_calls) * 100, 2) 
                        : 0,
                    'avg_duration' => round($item->avg_duration ?? 0, 2),
                ];
            })
            ->toArray();

        return $stats;
    }

    /**
     * Get daily call statistics
     */
    public function getDailyStats(Campaign $campaign): array
    {
        $stats = $campaign->calls()
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('count(*) as total_calls'),
                DB::raw('sum(case when status = "completed" then 1 else 0 end) as answered_calls'),
                DB::raw('avg(case when status = "completed" then duration_seconds else null end) as avg_duration')
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'total_calls' => $item->total_calls,
                    'answered_calls' => $item->answered_calls,
                    'answer_rate' => $item->total_calls > 0 
                        ? round(($item->answered_calls / $item->total_calls) * 100, 2) 
                        : 0,
                    'avg_duration' => round($item->avg_duration ?? 0, 2),
                ];
            })
            ->toArray();

        return $stats;
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics(Campaign $campaign): array
    {
        $calls = $campaign->calls();

        // Peak calling hour
        $peakHour = $campaign->calls()
            ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('count(*) as count'))
            ->groupBy(DB::raw('HOUR(created_at)'))
            ->orderByDesc('count')
            ->first();

        // Best performing hour (highest answer rate)
        $bestHour = $campaign->calls()
            ->select(
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('count(*) as total'),
                DB::raw('sum(case when status = "completed" then 1 else 0 end) as answered')
            )
            ->groupBy(DB::raw('HOUR(created_at)'))
            ->having('total', '>=', 5) // Minimum 5 calls for statistical relevance
            ->get()
            ->map(function ($item) {
                return [
                    'hour' => $item->hour,
                    'answer_rate' => ($item->answered / $item->total) * 100,
                ];
            })
            ->sortByDesc('answer_rate')
            ->first();

        // Retry statistics
        $retryStats = $campaign->campaignContacts()
            ->select(
                DB::raw('avg(call_attempts) as avg_attempts'),
                DB::raw('max(call_attempts) as max_attempts'),
                DB::raw('sum(case when call_attempts > 1 then 1 else 0 end) as contacts_with_retries')
            )
            ->first();

        // DTMF response statistics
        $dtmfStats = $campaign->campaignContacts()
            ->select(
                'dtmf_response',
                DB::raw('count(*) as count')
            )
            ->whereNotNull('dtmf_response')
            ->groupBy('dtmf_response')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->dtmf_response => $item->count];
            })
            ->toArray();

        return [
            'peak_hour' => $peakHour ? sprintf('%02d:00', $peakHour->hour) : null,
            'peak_hour_calls' => $peakHour->count ?? 0,
            'best_hour' => $bestHour ? sprintf('%02d:00', $bestHour['hour']) : null,
            'best_hour_answer_rate' => $bestHour ? round($bestHour['answer_rate'], 2) : 0,
            'avg_retry_attempts' => round($retryStats->avg_attempts ?? 0, 2),
            'max_retry_attempts' => $retryStats->max_attempts ?? 0,
            'contacts_with_retries' => $retryStats->contacts_with_retries ?? 0,
            'dtmf_responses' => $dtmfStats,
        ];
    }

    /**
     * Get user-wide analytics
     */
    public function getUserAnalytics(int $userId): array
    {
        $campaigns = Campaign::where('user_id', $userId);

        $totalCampaigns = $campaigns->count();
        $activeCampaigns = $campaigns->where('status', 'running')->count();
        $completedCampaigns = $campaigns->where('status', 'completed')->count();

        $totalCalls = Call::where('user_id', $userId)->count();
        $totalAnswered = Call::where('user_id', $userId)->where('status', 'completed')->count();

        $avgAnswerRate = $totalCalls > 0 ? round(($totalAnswered / $totalCalls) * 100, 2) : 0;

        $totalDuration = Call::where('user_id', $userId)
            ->where('status', 'completed')
            ->sum('duration_seconds') ?? 0;

        // Recent activity
        $recentCalls = Call::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return [
            'total_campaigns' => $totalCampaigns,
            'active_campaigns' => $activeCampaigns,
            'completed_campaigns' => $completedCampaigns,
            'total_calls' => $totalCalls,
            'total_answered' => $totalAnswered,
            'average_answer_rate' => $avgAnswerRate,
            'total_duration' => $totalDuration,
            'total_duration_formatted' => $this->formatDuration($totalDuration),
            'recent_calls' => $recentCalls,
        ];
    }

    /**
     * Export campaign data to CSV format
     */
    public function exportCampaignCalls(Campaign $campaign): string
    {
        $calls = $campaign->calls()
            ->with('campaignContact')
            ->orderBy('created_at', 'desc')
            ->get();

        $csv = "Call ID,Contact Name,Phone Number,Status,Duration,Started At,Ended At,Recording URL,DTMF Response\n";

        foreach ($calls as $call) {
            $contactName = $call->campaignContact 
                ? trim(($call->campaignContact->first_name ?? '') . ' ' . ($call->campaignContact->last_name ?? ''))
                : 'N/A';

            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $call->id,
                $this->escapeCsv($contactName),
                $this->escapeCsv($call->to_number),
                $this->escapeCsv($call->status),
                $call->duration_seconds ?? 0,
                $call->started_at ? $call->started_at->toDateTimeString() : '',
                $call->ended_at ? $call->ended_at->toDateTimeString() : '',
                $this->escapeCsv($call->recording_url ?? ''),
                $this->escapeCsv($call->campaignContact->dtmf_response ?? '')
            );
        }

        return $csv;
    }

    /**
     * Export campaign contacts to CSV format
     */
    public function exportCampaignContacts(Campaign $campaign): string
    {
        $contacts = $campaign->campaignContacts()
            ->orderBy('created_at', 'desc')
            ->get();

        $csv = "ID,First Name,Last Name,Phone Number,Status,Call Attempts,Last Call At,DTMF Response,Opted Out\n";

        foreach ($contacts as $contact) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $contact->id,
                $this->escapeCsv($contact->first_name ?? ''),
                $this->escapeCsv($contact->last_name ?? ''),
                $this->escapeCsv($contact->phone_number),
                $this->escapeCsv($contact->status),
                $contact->call_attempts,
                $contact->last_call_at ? $contact->last_call_at->toDateTimeString() : '',
                $this->escapeCsv($contact->dtmf_response ?? ''),
                $contact->opted_out ? 'Yes' : 'No'
            );
        }

        return $csv;
    }

    /**
     * Format duration in seconds to human-readable format
     */
    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's';
        } elseif ($seconds < 3600) {
            $mins = floor($seconds / 60);
            $secs = $seconds % 60;
            return $mins . 'm ' . $secs . 's';
        } else {
            $hours = floor($seconds / 3600);
            $mins = floor(($seconds % 3600) / 60);
            return $hours . 'h ' . $mins . 'm';
        }
    }

    /**
     * Escape CSV field
     */
    private function escapeCsv(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }
}
