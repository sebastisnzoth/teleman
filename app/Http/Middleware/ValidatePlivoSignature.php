<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\TelephonyProviderConfig;
use App\Services\Telephony\Plivo\PlivoSignature;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates incoming Plivo webhook requests by verifying the
 * X-Plivo-Signature-V3 header, mirroring ValidateTwilioSignature's shape
 * and failure behaviour.
 *
 * Credential lookup is READ-ONLY: TelephonyProviderConfig::forProvider()
 * is a firstOrCreate() and must never be used here — an unauthenticated
 * webhook request must never create a telephony_providers row. This uses
 * a plain ::where('name', 'plivo')->first() instead (see
 * tests/Feature/Security/PlivoSignatureTest.php for the test proving a
 * request that reaches this lookup with no configured provider creates
 * no row).
 *
 * The V3 algorithm (PlivoSignature) is hand-rolled rather than delegated
 * to the installed plivo/plivo-php SDK's Plivo\Util\v3SignatureValidation:
 * checked it first, and found two problems. (1) For a request URL with no
 * query string — the common shape for a webhook route — that SDK's
 * internal constructGetUrl()/constructPostUrl() builds the base string as
 * "url?params.nonce" (a bare "?", no dot) instead of the documented
 * "url.params.nonce", which would reject a genuinely-signed request;
 * verified against the SDK's own worked example from the docs (which does
 * have a query string) — there the SDK matches the documented output
 * exactly, so the divergence is specific to the no-query-string case.
 * (2) it calls the deprecated utf8_encode() on every invocation (five
 * deprecation notices per call). PlivoSignature implements the documented
 * algorithm directly instead:
 * https://www.plivo.com/docs/voice/concepts/signature-validation
 */
class ValidatePlivoSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        // Allow explicit override via config (useful for testing signature validation) —
        // mirrors ValidateTwilioSignature's own skip-flag handling exactly.
        $skipValidation = config('telephony.providers.plivo.skip_signature_validation');
        if ($skipValidation === true || ($skipValidation === null && app()->environment('testing'))) {
            return $next($request);
        }

        $signatureHeader = $request->header('X-Plivo-Signature-V3');

        if (empty($signatureHeader)) {
            // The signed URL, never fullUrl(): Request::fullUrl() rebuilds
            // the query string through Symfony's getQueryString(), which
            // sorts parameters and re-encodes values, so it can differ
            // from the string the signature was computed over. Logging it
            // would hand an operator debugging a live 403 a URL they
            // cannot rebuild the base string from. See PlivoSignature.
            Log::warning('Plivo webhook request missing X-Plivo-Signature-V3 header', [
                'url' => PlivoSignature::requestedUrl($request),
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Missing Plivo signature'], 403);
        }

        $config = TelephonyProviderConfig::where('name', 'plivo')->first();
        $authToken = $config?->credential('auth_token');

        if (empty($authToken)) {
            Log::error('Plivo webhook received but no Plivo auth token is configured');

            return response()->json(['error' => 'Plivo not configured'], 500);
        }

        $nonce = (string) $request->header('X-Plivo-Signature-V3-Nonce');
        $params = $request->isMethod('POST') ? $request->post() : [];
        $signedUrl = PlivoSignature::requestedUrl($request);
        $expected = PlivoSignature::compute($signedUrl, $params, $nonce, $authToken);

        if (! PlivoSignature::matches($expected, (string) $signatureHeader)) {
            // $signedUrl, not fullUrl() — this is the exact string the
            // base string was built from, so an operator can reproduce
            // the computation from what this line prints.
            Log::warning('Invalid Plivo webhook signature', [
                'url' => $signedUrl,
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 403);
        }

        return $next($request);
    }
}
