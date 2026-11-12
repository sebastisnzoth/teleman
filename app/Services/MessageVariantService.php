<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Campaign;
use App\Models\MessageVariant;
use Illuminate\Support\Facades\Log;

class MessageVariantService
{
    public function __construct(
        private OpenRouterService $aiService
    ) {}

    /**
     * Generate A/B test variants for a campaign message
     */
    public function generateVariants(Campaign $campaign, string $baseMessage = null, string $description = null): array
    {
        $baseMessage = $baseMessage ?? $campaign->message ?? '';
        $description = $description ?? "Campaign: {$campaign->name}. Type: {$campaign->type}";
        
        // Get expected variables from campaign
        $variables = $campaign->expected_variables ?? [];
        
        Log::info('Generating message variants', [
            'campaign_id' => $campaign->id,
            'base_message_length' => strlen($baseMessage),
            'variables_count' => count($variables),
        ]);

        // Generate variants using AI
        $variants = $this->aiService->generateMessageVariants($baseMessage, $description, $variables);
        
        // Save variants to database
        $savedVariants = [];
        foreach ($variants as $variantData) {
            $variant = MessageVariant::create([
                'campaign_id' => $campaign->id,
                'variant_label' => $variantData['label'],
                'variant_name' => $variantData['name'],
                'message_text' => $variantData['message'],
                'tone_description' => $variantData['tone_description'],
            ]);
            
            $savedVariants[] = $variant;
        }

        Log::info('Message variants created', [
            'campaign_id' => $campaign->id,
            'variants_count' => count($savedVariants),
        ]);

        return $savedVariants;
    }

    /**
     * Get active variant for a campaign (random selection for A/B testing)
     */
    public function getActiveVariant(Campaign $campaign): ?MessageVariant
    {
        $variants = MessageVariant::forCampaign($campaign->id)
            ->active()
            ->get();

        if ($variants->isEmpty()) {
            return null;
        }

        // Random selection for A/B testing
        return $variants->random();
    }

    /**
     * Get best performing variant
     */
    public function getBestVariant(Campaign $campaign): ?MessageVariant
    {
        return MessageVariant::forCampaign($campaign->id)
            ->active()
            ->orderByPerformance()
            ->first();
    }

    /**
     * Mark the best variant as winner
     */
    public function selectWinner(Campaign $campaign, int $minimumSample = 50): ?MessageVariant
    {
        $variants = MessageVariant::forCampaign($campaign->id)
            ->active()
            ->get();

        // Need minimum sample size
        $hasMinimumSample = $variants->filter(fn($v) => $v->sent_count >= $minimumSample)->count() >= 2;
        
        if (!$hasMinimumSample) {
            Log::info('Not enough data to select winner', [
                'campaign_id' => $campaign->id,
                'minimum_required' => $minimumSample,
            ]);
            return null;
        }

        // Find best performer
        $winner = $variants->sortByDesc('effectiveness_score')->first();
        
        if ($winner) {
            // Mark as winner
            MessageVariant::forCampaign($campaign->id)->update(['is_winner' => false]);
            $winner->update(['is_winner' => true]);
            
            // Deactivate others
            MessageVariant::forCampaign($campaign->id)
                ->where('id', '!=', $winner->id)
                ->update(['is_active' => false]);

            Log::info('Winner selected', [
                'campaign_id' => $campaign->id,
                'winner_id' => $winner->id,
                'winner_label' => $winner->variant_label,
                'effectiveness_score' => $winner->effectiveness_score,
            ]);
        }

        return $winner;
    }

    /**
     * Get performance summary for all variants
     */
    public function getPerformanceSummary(Campaign $campaign): array
    {
        $variants = MessageVariant::forCampaign($campaign->id)->get();

        return [
            'total_variants' => $variants->count(),
            'active_variants' => $variants->where('is_active', true)->count(),
            'total_sent' => $variants->sum('sent_count'),
            'total_answered' => $variants->sum('answered_count'),
            'total_completed' => $variants->sum('completed_count'),
            'overall_answer_rate' => $variants->sum('sent_count') > 0 
                ? ($variants->sum('answered_count') / $variants->sum('sent_count')) * 100 
                : 0,
            'overall_completion_rate' => $variants->sum('sent_count') > 0 
                ? ($variants->sum('completed_count') / $variants->sum('sent_count')) * 100 
                : 0,
            'winner' => $variants->where('is_winner', true)->first(),
            'variants' => $variants->sortByDesc('effectiveness_score')->values(),
        ];
    }

    /**
     * Record variant usage and outcome
     */
    public function recordVariantUsage(MessageVariant $variant, string $outcome): void
    {
        $variant->recordUsage();

        match ($outcome) {
            'answered' => $variant->recordAnswer(),
            'completed' => $variant->recordCompletion(),
            'positive' => $variant->recordPositiveResponse(),
            default => null,
        };
    }

    /**
     * Delete all variants for a campaign
     */
    public function deleteVariants(Campaign $campaign): int
    {
        return MessageVariant::forCampaign($campaign->id)->delete();
    }
}
