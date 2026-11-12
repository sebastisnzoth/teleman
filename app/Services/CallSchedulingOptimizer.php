<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Call;
use App\Models\Contact;
use App\Models\CampaignContact;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * AI-powered call scheduling optimizer
 * Analyzes historical data to predict best times to call
 */
class CallSchedulingOptimizer
{
    // Industry-specific best times (default patterns)
    private const INDUSTRY_PATTERNS = [
        'b2b' => [
            'best_days' => ['tuesday', 'wednesday', 'thursday'], // Mid-week best
            'best_hours' => [9, 10, 11, 14, 15, 16], // Morning and mid-afternoon
            'avoid_hours' => [12, 13, 17, 18, 19], // Lunch and end of day
        ],
        'b2c' => [
            'best_days' => ['saturday', 'sunday', 'wednesday'], // Weekends + mid-week
            'best_hours' => [10, 11, 18, 19, 20], // Late morning and evening
            'avoid_hours' => [9, 14, 15, 16], // Work hours
        ],
        'retail' => [
            'best_days' => ['saturday', 'sunday'], // Weekends
            'best_hours' => [11, 12, 13, 14], // Midday
            'avoid_hours' => [8, 9, 20, 21], // Early morning, late evening
        ],
    ];

    /**
     * Get optimal call time for a contact
     */
    public function getOptimalCallTime(Contact|CampaignContact $contact, ?string $timezone = null): array
    {
        // Get timezone
        $timezone = $timezone ?? $this->detectTimezone($contact->phone_number);
        
        // Analyze historical performance for this contact
        $historicalBest = $this->analyzeContactHistory($contact);
        
        // Analyze similar contacts
        $similarPatterns = $this->analyzeSimilarContacts($contact);
        
        // Get industry patterns
        $industryPattern = $this->getIndustryPattern($contact->company);
        
        // Combine insights
        $recommendation = $this->combineRecommendations(
            $historicalBest,
            $similarPatterns,
            $industryPattern,
            $timezone
        );

        return [
            'optimal_time' => $recommendation['time'],
            'confidence' => $recommendation['confidence'],
            'timezone' => $timezone,
            'day_of_week' => $recommendation['day_of_week'],
            'reasoning' => $recommendation['reasoning'],
            'alternative_times' => $recommendation['alternatives'],
        ];
    }

    /**
     * Get best times for a campaign
     */
    public function getCampaignOptimalTimes(int $campaignId, int $limit = 100): array
    {
        $cacheKey = "campaign_optimal_times_{$campaignId}";
        
        return Cache::remember($cacheKey, 3600, function () use ($campaignId, $limit) {
            // Analyze campaign's historical performance
            $hourlyStats = $this->analyzeCampaignHourlyPerformance($campaignId);
            $dailyStats = $this->analyzeCampaignDailyPerformance($campaignId);
            
            return [
                'best_hours' => array_slice($hourlyStats, 0, 5),
                'best_days' => array_slice($dailyStats, 0, 3),
                'worst_hours' => array_slice(array_reverse($hourlyStats), 0, 3),
                'peak_answer_rate_hour' => $hourlyStats[0] ?? null,
                'recommendations' => $this->generateCampaignRecommendations($hourlyStats, $dailyStats),
            ];
        });
    }

    /**
     * Detect timezone from phone number
     */
    private function detectTimezone(string $phoneNumber): string
    {
        // Remove non-digits
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // US timezone detection by area code (simplified)
        $areaCode = substr($cleaned, 1, 3);
        
        $timezoneMap = [
            // Pacific (GMT-8)
            'pacific' => ['206', '253', '360', '425', '509', // WA
                          '503', '971', // OR
                          '208', // ID
                          '702', '725', // NV
                          '213', '310', '323', '408', '415', '510', '562', '619', '626', '650', '661', '707', '714', '760', '805', '818', '831', '858', '909', '916', '925', '949', '951'], // CA
            
            // Mountain (GMT-7)
            'mountain' => ['303', '719', '720', '970', // CO
                           '480', '520', '602', '623', '928', // AZ
                           '385', '435', '801', // UT
                           '406', // MT
                           '307', // WY
                           '505', '575'], // NM
            
            // Central (GMT-6)
            'central' => ['214', '254', '281', '325', '361', '409', '430', '432', '469', '512', '682', '713', '737', '806', '817', '830', '832', '903', '915', '936', '940', '956', '972', '979', // TX
                          '312', '217', '224', '309', '312', '618', '630', '708', '773', '815', '847', '872', // IL
                          '314', '417', '573', '636', '660', '816', // MO
                          '501', // AR
                          '504', '225', // LA
                          '612', '218', '320', '507', '651', '763', '952', // MN
                          '414', '262', '608', '715', '920', // WI
                          '316', '620', '785', '913'], // KS
            
            // Eastern (GMT-5) - Most populous, use as default
            'eastern' => ['212', '347', '646', '718', '917', '929', // NYC
                          '305', '321', '352', '386', '407', '561', '727', '754', '772', '786', '813', '850', '863', '904', '941', '954', // FL
                          '404', '470', '678', '706', '762', '770', '912', // GA
                          '617', '339', '351', '413', '508', '774', '781', '857', '978'], // MA
        ];
        
        foreach ($timezoneMap as $timezone => $codes) {
            if (in_array($areaCode, $codes)) {
                return $timezone;
            }
        }
        
        return 'eastern'; // Default
    }

    /**
     * Analyze contact's historical call performance
     */
    private function analyzeContactHistory(Contact|CampaignContact $contact): ?array
    {
        $phoneNumber = $contact->phone_number ?? $contact->contact?->phone_number;
        
        if (!$phoneNumber) {
            return null;
        }

        // Get calls to this contact
        $calls = Call::where('to_number', $phoneNumber)
            ->whereNotNull('ended_at')
            ->get();

        if ($calls->isEmpty()) {
            return null;
        }

        // Group by hour and analyze answer rates
        $hourlyPerformance = $calls->groupBy(function ($call) {
            return Carbon::parse($call->ended_at)->hour;
        })->map(function ($hourCalls) {
            $answered = $hourCalls->where('status', 'completed')->count();
            $total = $hourCalls->count();
            return [
                'answer_rate' => $total > 0 ? ($answered / $total) * 100 : 0,
                'total_calls' => $total,
            ];
        })->sortByDesc('answer_rate');

        $bestHour = $hourlyPerformance->keys()->first();

        return [
            'best_hour' => $bestHour,
            'answer_rate' => $hourlyPerformance[$bestHour]['answer_rate'] ?? 0,
            'sample_size' => $hourlyPerformance[$bestHour]['total_calls'] ?? 0,
        ];
    }

    /**
     * Analyze similar contacts (same area code, similar company type)
     */
    private function analyzeSimilarContacts(Contact|CampaignContact $contact): array
    {
        $phoneNumber = $contact->phone_number ?? $contact->contact?->phone_number;
        $areaCode = substr(preg_replace('/[^0-9]/', '', $phoneNumber), 1, 3);

        // Get calls to similar area codes
        $similarCalls = Call::where('to_number', 'LIKE', "+1{$areaCode}%")
            ->where('status', 'completed')
            ->whereNotNull('ended_at')
            ->limit(1000)
            ->get();

        if ($similarCalls->isEmpty()) {
            return $this->getDefaultPattern();
        }

        // Analyze by hour of day
        $hourlyStats = $similarCalls->groupBy(function ($call) {
            return Carbon::parse($call->ended_at)->hour;
        })->map->count()->sortDesc();

        // Analyze by day of week
        $dailyStats = $similarCalls->groupBy(function ($call) {
            return Carbon::parse($call->ended_at)->dayOfWeek;
        })->map->count()->sortDesc();

        return [
            'best_hours' => $hourlyStats->keys()->take(3)->toArray(),
            'best_days' => $dailyStats->keys()->take(3)->toArray(),
            'sample_size' => $similarCalls->count(),
        ];
    }

    /**
     * Get industry-specific patterns
     */
    private function getIndustryPattern(?string $company): array
    {
        // Determine if B2B or B2C based on company presence
        $type = !empty($company) ? 'b2b' : 'b2c';
        
        return self::INDUSTRY_PATTERNS[$type] ?? self::INDUSTRY_PATTERNS['b2b'];
    }

    /**
     * Combine all recommendations into optimal time
     */
    private function combineRecommendations(
        ?array $historical,
        array $similar,
        array $industry,
        string $timezone
    ): array {
        $reasoning = [];
        $confidence = 50; // Base confidence

        // Prefer historical data if available
        if ($historical && $historical['sample_size'] >= 3) {
            $bestHour = $historical['best_hour'];
            $confidence = min(90, 50 + ($historical['sample_size'] * 5));
            $reasoning[] = "Based on {$historical['sample_size']} previous calls with {$historical['answer_rate']}% answer rate";
        } 
        // Use similar contacts
        elseif ($similar['sample_size'] > 10) {
            $bestHour = $similar['best_hours'][0] ?? 10;
            $confidence = 70;
            $reasoning[] = "Based on {$similar['sample_size']} calls to similar contacts";
        }
        // Fall back to industry patterns
        else {
            $bestHour = $industry['best_hours'][0] ?? 10;
            $confidence = 60;
            $reasoning[] = "Based on industry best practices";
        }

        // Determine best day
        $bestDay = $similar['best_days'][0] ?? 2; // Tuesday default
        $dayName = Carbon::now()->dayOfWeek($bestDay)->format('l');

        // Calculate next optimal time
        $now = Carbon::now($timezone);
        $nextOptimal = $now->copy()->next($bestDay)->setHour($bestHour)->setMinute(0);
        
        // If that time has passed this week, add a week
        if ($nextOptimal->isPast()) {
            $nextOptimal->addWeek();
        }

        // Generate alternative times
        $alternatives = [];
        for ($i = 1; $i <= 3; $i++) {
            $altHour = ($bestHour + $i) % 24;
            if (in_array($altHour, $industry['avoid_hours'] ?? [])) {
                continue;
            }
            $altTime = $now->copy()->next($bestDay)->setHour($altHour)->setMinute(0);
            if ($altTime->isPast()) {
                $altTime->addWeek();
            }
            $alternatives[] = [
                'time' => $altTime->toIso8601String(),
                'local_time' => $altTime->format('g:i A'),
                'day' => $altTime->format('l'),
            ];
        }

        return [
            'time' => $nextOptimal->toIso8601String(),
            'local_time' => $nextOptimal->format('g:i A'),
            'day_of_week' => $dayName,
            'confidence' => $confidence,
            'reasoning' => $reasoning,
            'alternatives' => $alternatives,
        ];
    }

    /**
     * Analyze campaign's hourly performance
     */
    private function analyzeCampaignHourlyPerformance(int $campaignId): array
    {
        $calls = Call::where('campaign_id', $campaignId)
            ->whereNotNull('ended_at')
            ->get();

        if ($calls->isEmpty()) {
            return [];
        }

        $hourlyStats = $calls->groupBy(function ($call) {
            return Carbon::parse($call->ended_at)->hour;
        })->map(function ($hourCalls, $hour) {
            $answered = $hourCalls->where('status', 'completed')->count();
            $total = $hourCalls->count();
            $answerRate = $total > 0 ? ($answered / $total) * 100 : 0;

            return [
                'hour' => $hour,
                'hour_12' => Carbon::today()->setHour($hour)->format('g A'),
                'answer_rate' => round($answerRate, 1),
                'total_calls' => $total,
                'answered_calls' => $answered,
            ];
        })->sortByDesc('answer_rate')->values()->toArray();

        return $hourlyStats;
    }

    /**
     * Analyze campaign's daily performance
     */
    private function analyzeCampaignDailyPerformance(int $campaignId): array
    {
        $calls = Call::where('campaign_id', $campaignId)
            ->whereNotNull('ended_at')
            ->get();

        if ($calls->isEmpty()) {
            return [];
        }

        $dailyStats = $calls->groupBy(function ($call) {
            return Carbon::parse($call->ended_at)->dayOfWeek;
        })->map(function ($dayCalls, $day) {
            $answered = $dayCalls->where('status', 'completed')->count();
            $total = $dayCalls->count();
            $answerRate = $total > 0 ? ($answered / $total) * 100 : 0;

            return [
                'day' => $day,
                'day_name' => Carbon::now()->dayOfWeek($day)->format('l'),
                'answer_rate' => round($answerRate, 1),
                'total_calls' => $total,
                'answered_calls' => $answered,
            ];
        })->sortByDesc('answer_rate')->values()->toArray();

        return $dailyStats;
    }

    /**
     * Generate campaign recommendations
     */
    private function generateCampaignRecommendations(array $hourlyStats, array $dailyStats): array
    {
        $recommendations = [];

        if (!empty($hourlyStats)) {
            $best = $hourlyStats[0];
            $worst = end($hourlyStats);

            $recommendations[] = [
                'type' => 'timing',
                'priority' => 'high',
                'message' => "Call between {$best['hour_12']} for {$best['answer_rate']}% answer rate",
                'data' => $best,
            ];

            if ($worst['answer_rate'] < 20) {
                $recommendations[] = [
                    'type' => 'avoid',
                    'priority' => 'medium',
                    'message' => "Avoid calling at {$worst['hour_12']} (only {$worst['answer_rate']}% answer rate)",
                    'data' => $worst,
                ];
            }
        }

        if (!empty($dailyStats)) {
            $bestDay = $dailyStats[0];
            $recommendations[] = [
                'type' => 'day',
                'priority' => 'medium',
                'message' => "{$bestDay['day_name']} has the highest answer rate at {$bestDay['answer_rate']}%",
                'data' => $bestDay,
            ];
        }

        return $recommendations;
    }

    /**
     * Get default pattern when no data available
     */
    private function getDefaultPattern(): array
    {
        return [
            'best_hours' => [10, 11, 14, 15],
            'best_days' => [1, 2, 3], // Mon, Tue, Wed
            'sample_size' => 0,
        ];
    }

    /**
     * Batch optimize call times for contacts
     */
    public function batchOptimizeContacts(array $contactIds): array
    {
        $results = [];

        foreach ($contactIds as $contactId) {
            $contact = Contact::find($contactId);
            if ($contact) {
                $results[$contactId] = $this->getOptimalCallTime($contact);
            }
        }

        return $results;
    }

    /**
     * Get timezone-adjusted schedule for campaign
     */
    public function getTimezoneAdjustedSchedule(int $campaignId): array
    {
        $campaign = \App\Models\Campaign::find($campaignId);
        
        if (!$campaign) {
            return [];
        }

        // Group contacts by timezone
        $contactsByTimezone = $campaign->campaignContacts()
            ->get()
            ->groupBy(function ($contact) {
                return $this->detectTimezone($contact->phone_number);
            });

        $schedule = [];
        foreach ($contactsByTimezone as $timezone => $contacts) {
            $optimal = $this->getCampaignOptimalTimes($campaignId);
            $schedule[$timezone] = [
                'contact_count' => $contacts->count(),
                'best_hours' => $optimal['best_hours'] ?? [],
                'recommended_start' => Carbon::now($timezone)->next(2)->setHour($optimal['peak_answer_rate_hour']['hour'] ?? 10),
            ];
        }

        return $schedule;
    }
}
