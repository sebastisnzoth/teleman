<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleApiRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $tier = 'default'): Response
    {
        $key = $this->resolveRequestSignature($request);
        
        $limits = $this->getLimits($tier, $request);
        
        if (RateLimiter::tooManyAttempts($key, $limits['max'])) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Too many requests. Please try again later.',
                    'code' => 429,
                ],
                'meta' => [
                    'retry_after' => RateLimiter::availableIn($key),
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 429);
        }

        RateLimiter::hit($key, $limits['decay']);

        $response = $next($request);

        // Add rate limit headers. Through the header bag, which every response
        // has — a streamed CSV export has no withHeaders() and used to 500.
        $response->headers->add([
            'X-RateLimit-Limit' => $limits['max'],
            'X-RateLimit-Remaining' => RateLimiter::remaining($key, $limits['max']),
            'X-RateLimit-Reset' => RateLimiter::availableIn($key) + time(),
        ]);

        return $response;
    }

    /**
     * Resolve request signature for rate limiting
     */
    protected function resolveRequestSignature(Request $request): string
    {
        if ($user = $request->user('sanctum')) {
            return 'api_user_' . $user->id;
        }

        return 'api_ip_' . $request->ip();
    }

    /**
     * Get rate limits based on tier
     */
    protected function getLimits(string $tier, Request $request): array
    {
        $user = $request->user('sanctum');
        
        // Check user role for premium limits
        if ($user && $user->hasRole('admin')) {
            return [
                'max' => 10000,
                'decay' => 60, // per minute
            ];
        }

        return match($tier) {
            'auth' => [
                'max' => 5, // 5 login attempts
                'decay' => 60, // per minute
            ],
            'high' => [
                'max' => 1000, // 1000 requests
                'decay' => 60, // per minute
            ],
            'medium' => [
                'max' => 100, // 100 requests
                'decay' => 60, // per minute
            ],
            'low' => [
                'max' => 60, // 60 requests
                'decay' => 60, // per minute
            ],
            default => [
                'max' => 60, // 60 requests
                'decay' => 60, // per minute
            ],
        };
    }
}
