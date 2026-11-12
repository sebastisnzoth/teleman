<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * @OA\Tag(
 *     name="Users",
 *     description="User management endpoints (admin only)"
 * )
 */
class UserController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/api/v1/users",
     *     summary="List all users",
     *     tags={"Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Users list")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        
        $users = User::with('roles')
            ->latest()
            ->paginate($perPage);

        return $this->paginatedResponse($users, UserResource::class);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/users",
     *     summary="Create a new user",
     *     tags={"Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=201, description="User created")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'nullable|exists:roles,slug',
            'status' => 'nullable|in:active,inactive',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status' => $validated['status'] ?? 'active',
        ]);

        if (isset($validated['role'])) {
            $user->assignRole($validated['role']);
        } else {
            $user->assignRole('customer');
        }

        return $this->createdResponse(
            new UserResource($user->load('roles')),
            'User created successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/users/{id}",
     *     summary="Get user details",
     *     tags={"Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="User details")
     * )
     */
    public function show(User $user): JsonResponse
    {
        return $this->successResponse(
            new UserResource($user->load('roles'))
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/users/{id}",
     *     summary="Update user",
     *     tags={"Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="User updated")
     * )
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|min:8',
            'status' => 'sometimes|in:active,inactive',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return $this->successResponse(
            new UserResource($user->fresh('roles')),
            'User updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/users/{id}",
     *     summary="Delete user",
     *     tags={"Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="User deleted")
     * )
     */
    public function destroy(User $user): JsonResponse
    {
        // Prevent self-deletion
        if ($user->id === auth()->id()) {
            return $this->errorResponse('You cannot delete your own account', 400);
        }

        $user->delete();

        return $this->successResponse(null, 'User deleted successfully');
    }
}
