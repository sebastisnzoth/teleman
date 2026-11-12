<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\TwilioGlobalConfig;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Twilio\Security\RequestValidator;

/**
 * Validates incoming Twilio webhook requests by verifying the X-Twilio-Signature header.
 *
 * This middleware ensures that webhook requests genuinely originate from Twilio,
 * preventing attackers from spoofing call status updates, recordings, or DTMF responses.
 */
class ValidateTwilioSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        // Allow explicit override via config (useful for testing signature validation)
        $skipValidation = config('twilio.skip_signature_validation');
        if ($skipValidation === true || ($skipValidation === null && app()->environment('testing'))) {
            return $next($request);
        }

        $signature = $request->header('X-Twilio-Signature');

        if (empty($signature)) {
            Log::warning('Twilio webhook request missing X-Twilio-Signature header', [
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Missing Twilio signature'], 403);
        }

        $config = TwilioGlobalConfig::active();

        if (!$config) {
            Log::error('Twilio webhook received but no active Twilio config found');
            return response()->json(['error' => 'Twilio not configured'], 500);
        }

        $authToken = $config->getDecryptedAuthToken();

        if (empty($authToken)) {
            Log::error('Twilio auth token is empty — cannot validate webhook signature');
            return response()->json(['error' => 'Configuration error'], 500);
        }

        $validator = new RequestValidator($authToken);

        // Build the full URL that Twilio used to send the request.
        // Use the original URL (before any proxying) to match what Twilio signed.
        $url = $request->fullUrl();

        // If behind a reverse proxy, the scheme might be wrong. Twilio signs the HTTPS URL.
        if ($request->isSecure() || $request->header('X-Forwarded-Proto') === 'https') {
            $url = preg_replace('/^http:/', 'https:', $url);
        }

        $params = $request->isMethod('POST') ? $request->post() : [];

        $isValid = $validator->validate($signature, $url, $params);

        if (!$isValid) {
            Log::warning('Invalid Twilio webhook signature', [
                'url' => $url,
                'ip' => $request->ip(),
                'signature' => substr($signature, 0, 20) . '...',
            ]);

            return response()->json(['error' => 'Invalid signature'], 403);
        }

        return $next($request);
    }
}
