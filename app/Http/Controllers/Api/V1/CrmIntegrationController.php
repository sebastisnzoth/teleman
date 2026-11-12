<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CrmIntegration;
use App\Models\CrmWebhookLog;
use App\Services\CrmWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CrmIntegrationController extends Controller
{
    public function __construct(
        private CrmWebhookService $webhookService
    ) {
    }

    /**
     * List all CRM integrations for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $integrations = CrmIntegration::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $integrations,
        ]);
    }

    /**
     * Get a specific CRM integration
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $integration = CrmIntegration::where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $integration,
        ]);
    }

    /**
     * Create a new CRM integration
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'webhook_url' => 'required|url|max:500',
            'events' => 'required|array|min:1',
            'events.*' => Rule::in([
                'call_completed',
                'lead_qualified',
                'contact_added',
                'contact_updated',
                'campaign_started',
                'dtmf_response',
            ]),
            'auth_type' => 'required|in:none,bearer,api_key,hmac',
            'auth_credentials' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $integration = CrmIntegration::create([
            'user_id' => $request->user()->id,
            'name' => $request->name,
            'webhook_url' => $request->webhook_url,
            'events' => $request->events,
            'auth_type' => $request->auth_type,
            'auth_credentials' => $request->auth_credentials,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'success' => true,
            'data' => $integration,
            'message' => 'CRM integration created successfully',
        ], 201);
    }

    /**
     * Update an existing CRM integration
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $integration = CrmIntegration::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:100',
            'webhook_url' => 'sometimes|required|url|max:500',
            'events' => 'sometimes|required|array|min:1',
            'events.*' => Rule::in([
                'call_completed',
                'lead_qualified',
                'contact_added',
                'contact_updated',
                'campaign_started',
                'dtmf_response',
            ]),
            'auth_type' => 'sometimes|required|in:none,bearer,api_key,hmac',
            'auth_credentials' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $integration->update($request->only([
            'name',
            'webhook_url',
            'events',
            'auth_type',
            'auth_credentials',
            'is_active',
        ]));

        return response()->json([
            'success' => true,
            'data' => $integration->fresh(),
            'message' => 'CRM integration updated successfully',
        ]);
    }

    /**
     * Delete a CRM integration
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $integration = CrmIntegration::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $integration->delete();

        return response()->json([
            'success' => true,
            'message' => 'CRM integration deleted successfully',
        ]);
    }

    /**
     * Test webhook with sample payload
     */
    public function test(Request $request, int $id): JsonResponse
    {
        $integration = CrmIntegration::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $result = $this->webhookService->testWebhook($integration);

        return response()->json([
            'success' => $result['success'],
            'data' => $result,
        ], $result['success'] ? 200 : 400);
    }

    /**
     * Get webhook logs for an integration
     */
    public function logs(Request $request, int $id): JsonResponse
    {
        $integration = CrmIntegration::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $logs = CrmWebhookLog::where('crm_integration_id', $id)
            ->orderBy('triggered_at', 'desc')
            ->paginate($request->per_page ?? 50);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Get all webhook logs for the user
     */
    public function allLogs(Request $request): JsonResponse
    {
        $query = CrmWebhookLog::where('user_id', $request->user()->id)
            ->with('crmIntegration:id,name');

        // Filter by event type
        if ($request->has('event_type')) {
            $query->where('event_type', $request->event_type);
        }

        // Filter by integration
        if ($request->has('integration_id')) {
            $query->where('crm_integration_id', $request->integration_id);
        }

        // Filter by status
        if ($request->has('status')) {
            if ($request->status === 'success') {
                $query->successful();
            } elseif ($request->status === 'failed') {
                $query->failed();
            }
        }

        $logs = $query->orderBy('triggered_at', 'desc')
            ->paginate($request->per_page ?? 50);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Retry a failed webhook
     */
    public function retry(Request $request, int $logId): JsonResponse
    {
        $log = CrmWebhookLog::where('user_id', $request->user()->id)
            ->findOrFail($logId);

        $success = $this->webhookService->retryWebhook($log);

        return response()->json([
            'success' => $success,
            'message' => $success 
                ? 'Webhook retried successfully' 
                : 'Failed to retry webhook',
        ]);
    }

    /**
     * Get statistics for CRM integrations
     */
    public function stats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $stats = [
            'total_integrations' => CrmIntegration::where('user_id', $userId)->count(),
            'active_integrations' => CrmIntegration::where('user_id', $userId)->where('is_active', true)->count(),
            'total_webhooks_sent' => CrmWebhookLog::where('user_id', $userId)->count(),
            'successful_webhooks' => CrmWebhookLog::where('user_id', $userId)->successful()->count(),
            'failed_webhooks' => CrmWebhookLog::where('user_id', $userId)->failed()->count(),
            'webhooks_last_24h' => CrmWebhookLog::where('user_id', $userId)
                ->where('triggered_at', '>=', now()->subDay())
                ->count(),
        ];

        $stats['success_rate'] = $stats['total_webhooks_sent'] > 0 
            ? round(($stats['successful_webhooks'] / $stats['total_webhooks_sent']) * 100, 2)
            : 0;

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
