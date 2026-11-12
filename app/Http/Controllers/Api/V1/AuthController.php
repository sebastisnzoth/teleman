<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Authentication",
 *     description="API authentication and token management endpoints"
 * )
 */
class AuthController extends BaseApiController
{
    /**
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     summary="Login and get API token",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="admin@teleman.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password"),
     *             @OA\Property(property="token_name", type="string", example="mobile-app"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful login",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="token", type="string"),
     *                 @OA\Property(property="token_type", type="string", example="Bearer"),
     *                 @OA\Property(property="user", ref="#/components/schemas/User")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Invalid credentials")
     * )
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'token_name' => 'nullable|string|max:255',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if user is active
        if ($user->status !== 'active') {
            return $this->errorResponse('Your account is not active. Please contact support.', 403);
        }

        $tokenName = $request->token_name ?? 'api-token';
        
        // Create token with abilities based on user role
        $abilities = $this->getTokenAbilities($user);
        $token = $user->createToken($tokenName, $abilities);

        return $this->successResponse([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'abilities' => $abilities,
            'user' => new UserResource($user->load('roles')),
        ], 'Login successful');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/register",
     *     summary="Register a new user",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123"),
     *         )
     *     ),
     *     @OA\Response(response=201, description="User registered successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status' => 'active',
        ]);

        // Assign default customer role
        $user->assignRole('customer');

        $token = $user->createToken('api-token')->plainTextToken;

        return $this->createdResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user->load('roles')),
        ], 'Registration successful');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/auth/user",
     *     summary="Get authenticated user",
     *     tags={"Authentication"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Current user data")
     * )
     */
    public function user(Request $request): JsonResponse
    {
        return $this->successResponse(
            new UserResource($request->user()->load('roles'))
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/logout",
     *     summary="Logout (revoke current token)",
     *     tags={"Authentication"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Logged out successfully")
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Logged out successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/tokens",
     *     summary="Create a new API token",
     *     tags={"Authentication"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="CRM Integration"),
     *             @OA\Property(property="abilities", type="array", @OA\Items(type="string"), example={"campaigns:read", "contacts:write"})
     *         )
     *     ),
     *     @OA\Response(response=201, description="Token created successfully")
     * )
     */
    public function createToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'abilities' => 'nullable|array',
            'abilities.*' => 'string',
        ]);

        $abilities = $validated['abilities'] ?? $this->getTokenAbilities($request->user());
        $token = $request->user()->createToken($validated['name'], $abilities);

        return $this->createdResponse([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'name' => $validated['name'],
            'abilities' => $abilities,
        ], 'API token created successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/auth/tokens",
     *     summary="List all user tokens",
     *     tags={"Authentication"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="List of tokens")
     * )
     */
    public function listTokens(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens->map(function ($token) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at->toIso8601String(),
            ];
        });

        return $this->successResponse($tokens);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/auth/tokens/{tokenId}",
     *     summary="Revoke a specific token",
     *     tags={"Authentication"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="tokenId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Token revoked successfully")
     * )
     */
    public function revokeToken(Request $request, int $tokenId): JsonResponse
    {
        $token = $request->user()->tokens()->find($tokenId);

        if (!$token) {
            return $this->notFoundResponse('Token not found');
        }

        $token->delete();

        return $this->successResponse(null, 'Token revoked successfully');
    }

    /**
     * Get token abilities based on user role
     */
    private function getTokenAbilities(User $user): array
    {
        if ($user->hasRole('admin')) {
            return ['*']; // Full access
        }

        // Default abilities for customers/agents
        return [
            'campaigns:read',
            'campaigns:write',
            'contacts:read',
            'contacts:write',
            'calls:read',
        ];
    }
}
