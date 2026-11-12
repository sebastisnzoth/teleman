<?php

declare(strict_types=1);

namespace App\Services\Telephony\Plivo;

use Illuminate\Http\Request;

/**
 * The one place Plivo's documented X-Plivo-Signature-V3 webhook algorithm
 * is implemented: https://www.plivo.com/docs/voice/concepts/signature-validation
 *
 * Used by ValidatePlivoSignature, PlivoVoiceProvider::validateWebhook()
 * and PlivoSMSProvider::validateWebhook() — extracted here after those
 * three each carried their own copy and one of them (and, separately,
 * the middleware) built the signed URL from
 * Illuminate\Http\Request::fullUrl(), which reconstructs the query
 * string through Symfony's getQueryString(): that re-sorts parameters
 * and re-encodes values (a space-turned-"+" becomes "%20"), producing a
 * different string than Plivo actually signed for any callback URL
 * carrying two or more query parameters. A correction to that — or to
 * anything else about this algorithm — belongs in exactly one place.
 */
final class PlivoSignature
{
    /**
     * Full URL, then ".", then the given parameters sorted alphabetically
     * by name (Unix-style case-sensitive sort) each rendered as name
     * immediately followed by value with no separator between pairs,
     * then ".", then the nonce — HMAC-SHA256 with the auth token,
     * base64-encoded.
     */
    public static function compute(string $url, array $params, string $nonce, string $authToken): string
    {
        ksort($params, SORT_STRING);

        $sortedParams = '';
        foreach ($params as $name => $value) {
            $sortedParams .= $name.self::stringify($value);
        }

        $base = $url.'.'.$sortedParams.'.'.$nonce;

        return base64_encode(hash_hmac('sha256', $base, $authToken, true));
    }

    /**
     * An account with more than one active auth token sends a
     * comma-separated list of signatures in the header; accept a match
     * against any of them. Constant-time per candidate.
     */
    public static function matches(string $expected, string $headerValue): bool
    {
        foreach (explode(',', $headerValue) as $candidate) {
            if (hash_equals($expected, trim($candidate))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The exact URL Plivo actually requested — built from the raw,
     * unmodified request-target (getRequestUri()) rather than fullUrl(),
     * which reorders and re-encodes the query string (see class
     * docblock). TrustProxies already makes getSchemeAndHttpHost()
     * correct behind a reverse proxy.
     */
    public static function requestedUrl(Request $request): string
    {
        return $request->getSchemeAndHttpHost().$request->getRequestUri();
    }

    /**
     * Plivo's own parameters are always scalar; this guards against a
     * plain string concatenation emitting a PHP "Array to string
     * conversion" warning (rather than a clean 403) if a caller ever
     * passes an array-valued field through untouched (e.g. a raw
     * $request->post() containing a `foo[]=` submission).
     */
    private static function stringify(mixed $value): string
    {
        if (is_array($value)) {
            $flattened = '';
            foreach ($value as $item) {
                $flattened .= self::stringify($item);
            }

            return $flattened;
        }

        return (string) $value;
    }
}
