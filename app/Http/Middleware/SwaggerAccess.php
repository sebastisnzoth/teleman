<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SwaggerAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Allow access in local/development environment
        if (app()->environment(['local', 'development'])) {
            return $next($request);
        }

        // In production, require authentication
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'API documentation requires authentication in production.',
                    'code' => 401,
                ],
            ], 401);
        }

        // Optionally restrict to specific roles
        if (config('l5-swagger.security.role_required') && !$request->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Access to API documentation is restricted to administrators.',
                    'code' => 403,
                ],
            ], 403);
        }

        return $next($request);
    }
}
