<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\MessageVariant;
use App\Services\MessageVariantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MessageVariantController extends Controller
{
    public function __construct(
        private MessageVariantService $variantService
    ) {}

    /**
     * Generate message variants for a campaign
     */
    public function generateVariants(Request $request, Campaign $campaign): JsonResponse
    {
        // Authorize
        if ($campaign->user_id !== $request->user()->id && !$request->user()->is_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'base_message' => 'required|string|max:500',
            'description' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $variants = $this->variantService->generateVariants(
                $campaign,
                $request->input('base_message'),
                $request->input('description')
            );

            return response()->json([
                'message' => 'Variants generated successfully',
                'variants' => $variants,
                'count' => count($variants),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate message variants', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to generate variants',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all variants for a campaign
     */
    public function getVariants(Campaign $campaign, Request $request): JsonResponse
    {
        // Authorize
        if ($campaign->user_id !== $request->user()->id && !$request->user()->is_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $variants = MessageVariant::where('campaign_id', $campaign->id)
            ->orderByPerformance()
            ->get();

        $summary = $this->variantService->getPerformanceSummary($campaign);

        return response()->json([
            'variants' => $variants,
            'summary' => $summary,
        ]);
    }

    /**
     * Select a winning variant
     */
    public function selectWinner(Campaign $campaign, MessageVariant $variant, Request $request): JsonResponse
    {
        // Authorize
        if ($campaign->user_id !== $request->user()->id && !$request->user()->is_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Verify variant belongs to campaign
        if ($variant->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Variant does not belong to this campaign'], 400);
        }

        $minimumSample = $request->input('minimum_sample', 50);

        try {
            $winner = $this->variantService->selectWinner($campaign, $minimumSample);

            if (!$winner) {
                return response()->json([
                    'message' => 'Insufficient data to select winner',
                    'required_samples' => $minimumSample,
                ], 400);
            }

            return response()->json([
                'message' => 'Winner selected successfully',
                'winner' => $winner,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to select winner', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to select winner',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete all variants for a campaign
     */
    public function deleteVariants(Campaign $campaign, Request $request): JsonResponse
    {
        // Authorize
        if ($campaign->user_id !== $request->user()->id && !$request->user()->is_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $this->variantService->deleteVariants($campaign);

            return response()->json([
                'message' => 'Variants deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete variants', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to delete variants',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle variant active status
     */
    public function toggleActive(Campaign $campaign, MessageVariant $variant, Request $request): JsonResponse
    {
        // Authorize
        if ($campaign->user_id !== $request->user()->id && !$request->user()->is_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Verify variant belongs to campaign
        if ($variant->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Variant does not belong to this campaign'], 400);
        }

        $variant->is_active = !$variant->is_active;
        $variant->save();

        return response()->json([
            'message' => 'Variant status updated',
            'variant' => $variant,
        ]);
    }
}
