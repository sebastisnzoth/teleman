<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckCreditBalance
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $minBalance = null): Response
    {
        $user = $request->user();

        // Skip check if user is not authenticated
        if (!$user) {
            return $next($request);
        }

        // Skip check for admins (optional)
        // if ($user->hasRole('admin')) {
        //     return $next($request);
        // }

        $requiredBalance = $minBalance ? (float) $minBalance : 0.01;

        // Check if user has sufficient balance
        if ($user->credit_balance < $requiredBalance) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Insufficient credit balance',
                    'current_balance' => $user->credit_balance,
                    'required_balance' => $requiredBalance,
                    'currency' => $user->preferred_currency ?? 'USD',
                ], 402); // 402 Payment Required
            }

            return redirect()->route('credit.top-up')
                ->with('error', 'Insufficient credit balance. Please top up your account to continue.');
        }

        return $next($request);
    }
}

