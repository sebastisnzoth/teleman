<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OpenRouterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiMessageController extends Controller
{
    public function __construct(
        private OpenRouterService $openRouterService
    ) {}

    /**
     * Generate a new campaign message using AI
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:500'],
            'contact_variables' => ['nullable', 'array'],
            'contact_variables.*' => ['string'],
            'campaign_variables' => ['nullable', 'array'],
            // Campaign variables are key-value pairs (object in JSON)
        ]);

        try {
            $variables = [
                'contact' => $validated['contact_variables'] ?? [],
                'campaign' => $validated['campaign_variables'] ?? [],
            ];

            $message = $this->openRouterService->generateMessage(
                $validated['prompt'],
                $variables
            );

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Enhance an existing campaign message using AI
     */
    public function enhance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
            'contact_variables' => ['nullable', 'array'],
            'contact_variables.*' => ['string'],
            'campaign_variables' => ['nullable', 'array'],
            // Campaign variables are key-value pairs (object in JSON)
        ]);

        try {
            $variables = [
                'contact' => $validated['contact_variables'] ?? [],
                'campaign' => $validated['campaign_variables'] ?? [],
            ];

            $enhancedMessage = $this->openRouterService->enhanceMessage(
                $validated['message'],
                $variables
            );

            return response()->json([
                'success' => true,
                'message' => $enhancedMessage,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if AI service is available
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'available' => $this->openRouterService->isAvailable(),
        ]);
    }
}
