<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Models\Contact;
use Illuminate\Support\Collection;

class CalculateEngagementScoreAction
{
    /**
     * Calculate engagement score for a single contact
     *
     * Score factors:
     * - Success rate (60% weight): completed calls / total calls
     * - Recency (20% weight): how recently contacted
     * - Activity (20% weight): number of campaigns participated in
     *
     * @param Contact $contact
     * @return float Score between 0-100
     */
    public function calculate(Contact $contact): float
    {
        if ($contact->total_calls === 0) {
            return 0.00;
        }

        // 1. Success Rate Score (60% weight)
        $successRate = ($contact->successful_calls / $contact->total_calls) * 60;

        // 2. Recency Score (20% weight)
        $recencyScore = $this->calculateRecencyScore($contact);

        // 3. Activity Score (20% weight)
        $activityScore = $this->calculateActivityScore($contact);

        $totalScore = $successRate + $recencyScore + $activityScore;

        // Update the contact's engagement score
        $contact->update(['engagement_score' => round($totalScore, 2)]);

        return round($totalScore, 2);
    }

    /**
     * Calculate recency score based on last contact date
     */
    private function calculateRecencyScore(Contact $contact): float
    {
        if (!$contact->last_contacted_at) {
            return 0.00;
        }

        $daysSinceContact = $contact->last_contacted_at->diffInDays(now());

        // Score based on recency
        if ($daysSinceContact <= 7) {
            return 20.00; // Very recent
        } elseif ($daysSinceContact <= 30) {
            return 15.00; // Recent
        } elseif ($daysSinceContact <= 90) {
            return 10.00; // Somewhat recent
        } elseif ($daysSinceContact <= 180) {
            return 5.00; // Not very recent
        } else {
            return 2.00; // Old
        }
    }

    /**
     * Calculate activity score based on campaign participation
     */
    private function calculateActivityScore(Contact $contact): float
    {
        // More campaigns = higher engagement (max 20 points)
        // 5+ campaigns = perfect score
        return min(($contact->total_campaigns / 5) * 20, 20.00);
    }

    /**
     * Recalculate scores for multiple contacts
     *
     * @param Collection|array $contacts
     * @return array{processed: int, average_score: float}
     */
    public function recalculateMany($contacts): array
    {
        $processed = 0;
        $totalScore = 0.00;

        foreach ($contacts as $contact) {
            $score = $this->calculate($contact);
            $totalScore += $score;
            $processed++;
        }

        $averageScore = $processed > 0 ? $totalScore / $processed : 0.00;

        return [
            'processed' => $processed,
            'average_score' => round($averageScore, 2),
        ];
    }

    /**
     * Recalculate scores for all active contacts of a user
     *
     * @param int $userId
     * @return array{processed: int, average_score: float}
     */
    public function recalculateForUser(int $userId): array
    {
        $contacts = Contact::where('user_id', $userId)
            ->active()
            ->where('total_calls', '>', 0)
            ->get();

        return $this->recalculateMany($contacts);
    }
}
