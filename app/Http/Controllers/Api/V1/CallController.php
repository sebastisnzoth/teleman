<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\CallResource;
use App\Models\Call;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * @OA\Tag(
 *     name="Calls",
 *     description="Call history and recordings endpoints (read-only)"
 * )
 */
class CallController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/api/v1/calls",
     *     summary="List all calls",
     *     tags={"Calls"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="filter[status]", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="filter[direction]", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="filter[campaign_id]", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="sort", in="query", @OA\Schema(type="string", example="-started_at")),
     *     @OA\Response(response=200, description="Calls list")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);

        $calls = QueryBuilder::for(Call::class)
            ->allowedFilters([
                'status',
                'direction',
                'call_type',
                AllowedFilter::exact('campaign_id'),
                AllowedFilter::scope('date_range'),
            ])
            ->allowedSorts(['started_at', 'duration_seconds', 'created_at'])
            ->where('user_id', $request->user()->id)
            ->with(['campaign', 'campaignContact'])
            ->latest('started_at')
            ->paginate($perPage);

        return $this->paginatedResponse($calls, CallResource::class);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/calls/{id}",
     *     summary="Get call details",
     *     tags={"Calls"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Call details")
     * )
     */
    public function show(Request $request, Call $call): JsonResponse
    {
        // Check ownership
        if ($call->user_id !== $request->user()->id && !$request->user()->hasRole('admin')) {
            return $this->forbiddenResponse('You do not have permission to access this call');
        }

        return $this->successResponse(
            new CallResource($call->load(['campaign', 'campaignContact']))
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/calls/{id}/recording",
     *     summary="Get call recording URL",
     *     tags={"Calls"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Recording URL",
     *         @OA\JsonContent(
     *             @OA\Property(property="recording_url", type="string"),
     *             @OA\Property(property="duration", type="integer"),
     *             @OA\Property(property="sid", type="string")
     *         )
     *     )
     * )
     */
    public function recording(Request $request, Call $call): JsonResponse
    {
        // Check ownership
        if ($call->user_id !== $request->user()->id && !$request->user()->hasRole('admin')) {
            return $this->forbiddenResponse();
        }

        if (!$call->recording_url) {
            return $this->notFoundResponse('No recording available for this call');
        }

        return $this->successResponse([
            'recording_url' => $call->recording_url,
            'duration' => $call->recording_duration,
            'sid' => $call->recording_sid,
            'call_id' => $call->id,
            'started_at' => $call->started_at?->toIso8601String(),
        ]);
    }
}
