<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Campaign;
use App\Models\Call;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Analytics",
 *     description="Analytics and statistics endpoints"
 * )
 */
class AnalyticsController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/api/v1/analytics/overview",
     *     summary="Get dashboard overview statistics",
     *     tags={"Analytics"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Overview statistics")
     * )
     */
    public function overview(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $stats = [
            'campaigns' => [
                'total' => Campaign::where('user_id', $userId)->count(),
                'active' => Campaign::where('user_id', $userId)->where('status', 'running')->count(),
                'completed' => Campaign::where('user_id', $userId)->where('status', 'completed')->count(),
            ],
            'contacts' => [
                'total' => Contact::where('user_id', $userId)->count(),
                'active' => Contact::where('user_id', $userId)->where('status', 'active')->count(),
                'opted_out' => Contact::where('user_id', $userId)->where('opted_out', true)->count(),
            ],
            'calls' => [
                'total' => Call::where('user_id', $userId)->count(),
                'today' => Call::where('user_id', $userId)
                    ->whereDate('started_at', today())
                    ->count(),
                'this_month' => Call::where('user_id', $userId)
                    ->whereMonth('started_at', now()->month)
                    ->whereYear('started_at', now()->year)
                    ->count(),
                'success_rate' => $this->getSuccessRate($userId),
            ],
            'costs' => [
                'total' => Call::where('user_id', $userId)->sum('price'),
                'this_month' => Call::where('user_id', $userId)
                    ->whereMonth('started_at', now()->month)
                    ->whereYear('started_at', now()->year)
                    ->sum('price'),
            ],
            'account' => [
                'credit_balance' => $request->user()->credit_balance,
                'preferred_currency' => $request->user()->preferred_currency ?? 'USD',
            ],
        ];

        return $this->successResponse($stats);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/analytics/campaigns",
     *     summary="Get campaign analytics",
     *     tags={"Analytics"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="days", in="query", @OA\Schema(type="integer", example=30)),
     *     @OA\Response(response=200, description="Campaign analytics")
     * )
     */
    public function campaigns(Request $request): JsonResponse
    {
        $days = $request->get('days', 30);
        $userId = $request->user()->id;

        $campaigns = Campaign::where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays($days))
            ->select([
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed'),
                DB::raw('SUM(total_calls) as total_calls'),
                DB::raw('SUM(completed_calls) as completed_calls'),
            ])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $this->successResponse([
            'period_days' => $days,
            'data' => $campaigns,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/analytics/calls",
     *     summary="Get call analytics",
     *     tags={"Analytics"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="days", in="query", @OA\Schema(type="integer", example=30)),
     *     @OA\Response(response=200, description="Call analytics")
     * )
     */
    public function calls(Request $request): JsonResponse
    {
        $days = $request->get('days', 30);
        $userId = $request->user()->id;

        $calls = Call::where('user_id', $userId)
            ->where('started_at', '>=', now()->subDays($days))
            ->select([
                DB::raw('DATE(started_at) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed'),
                DB::raw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed'),
                DB::raw('AVG(duration_seconds) as avg_duration'),
                DB::raw('SUM(price) as total_cost'),
            ])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $this->successResponse([
            'period_days' => $days,
            'data' => $calls,
        ]);
    }

    /**
     * Calculate success rate for calls
     */
    private function getSuccessRate(int $userId): float
    {
        $total = Call::where('user_id', $userId)->count();
        
        if ($total === 0) {
            return 0;
        }

        $completed = Call::where('user_id', $userId)
            ->where('status', 'completed')
            ->count();

        return round(($completed / $total) * 100, 2);
    }
}
