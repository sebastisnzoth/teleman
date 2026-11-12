<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CallQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallQueueController extends Controller
{
    public function __construct(
        private CallQueueService $queueService
    ) {}

    /**
     * Get current queue status for the authenticated user.
     */
    public function status(Request $request): JsonResponse
    {
        $status = $this->queueService->getQueueStatus($request->user());

        return response()->json($status);
    }

    /**
     * Dequeue the next caller and connect to the user's browser.
     */
    public function dequeue(Request $request): JsonResponse
    {
        $entry = $this->queueService->triggerDequeue($request->user());

        if (!$entry) {
            return response()->json(['message' => 'No callers in queue'], 404);
        }

        return response()->json([
            'message' => 'Dequeue initiated',
            'entry' => [
                'id' => $entry->id,
                'caller_number' => $entry->caller_number,
                'status' => $entry->status,
            ],
        ]);
    }

    /**
     * Update queue settings for the authenticated user.
     * Only customers (not agents) can update settings.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $user = $request->user();

        // Agents cannot update queue settings — settings belong to the customer (parent)
        if ($user->isAgent()) {
            return response()->json(['message' => 'Agents cannot modify queue settings.'], 403);
        }

        $validated = $request->validate([
            'queue_enabled' => ['sometimes', 'boolean'],
            'max_queue_size' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'max_wait_seconds' => ['sometimes', 'integer', 'min:30', 'max:600'],
            'queue_greeting_message' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Queue settings updated',
            'settings' => [
                'queue_enabled' => $user->queue_enabled,
                'max_queue_size' => $user->max_queue_size,
                'max_wait_seconds' => $user->max_wait_seconds,
                'queue_greeting_message' => $user->queue_greeting_message,
            ],
        ]);
    }
}
