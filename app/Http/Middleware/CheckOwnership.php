<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckOwnership
{
    /**
     * Handle an incoming request.
     *
     * Ensure agents can only access their parent's data
     * Admins can access everything, Customers can access their own + agents, Agents can only access own data
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return redirect()->route('login')->with('error', 'Please login to access this page.');
        }

        $user = $request->user();

        // Admins bypass ownership checks
        if ($user->isAdmin()) {
            return $next($request);
        }

        // If checking a specific user (e.g., /users/{user})
        if ($request->route('user')) {
            $targetUser = $request->route('user');
            
            if (!$user->canAccessUserData($targetUser)) {
                abort(403, 'You do not have permission to access this user\'s data.');
            }
        }

        // Add user_id filter to query for non-admins (will be used in controllers)
        // Customers see their own data + their agents' data
        // Agents see only their own data
        $request->merge(['_ownership_filter' => true]);

        return $next($request);
    }
}

