<?php

namespace App\Http\Middleware;

use App\Models\KycSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckKycTier
{
    /**
     * Handle an incoming request.
     * Checks if user has approved KYC verification when KYC is enabled.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $minimumTier  Deprecated parameter (kept for backwards compatibility)
     */
    public function handle(Request $request, Closure $next, string $minimumTier = 'basic'): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Admins and agents bypass KYC requirements
        if ($user->hasRole(['admin', 'agent'])) {
            return $next($request);
        }

        // Only customers need KYC verification
        if (!$user->hasRole('customer')) {
            return $next($request);
        }

        // Check if KYC is enabled globally
        $kycEnabled = KycSetting::get('kyc_enabled', true);
        if (!$kycEnabled) {
            // KYC is disabled, allow access
            return $next($request);
        }

        // Check if user has KYC verification record
        if (!$user->kycVerification) {
            return redirect()->route('kyc.index')
                ->with('error', 'Please complete KYC verification to access this feature.');
        }

        $status = $user->kycVerification->status;

        // Only approved users can access
        if ($status !== 'approved') {
            if ($status === 'pending') {
                return redirect()->route('kyc.index')
                    ->with('info', 'Your KYC verification is pending review. You will be able to use this feature once approved.');
            }
            
            if ($status === 'rejected') {
                return redirect()->route('kyc.index')
                    ->with('error', 'Your KYC verification was rejected. Please resubmit to use this feature.');
            }

            return redirect()->route('kyc.index')
                ->with('error', 'Please complete KYC verification to access this feature.');
        }

        // Check if KYC is expired
        if ($user->kycVerification->isExpired()) {
            return redirect()->route('kyc.index')
                ->with('error', 'Your KYC verification has expired. Please renew to continue using this feature.');
        }

        return $next($request);
    }
}
