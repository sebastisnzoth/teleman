<?php

declare(strict_types=1);

namespace App\Services\Telephony\Plivo;

use App\Contracts\Telephony\WebRTCTokenProviderInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Issues the short-lived access token the Plivo Browser SDK registers with
 * through loginWithAccessToken().
 *
 * Plivo's Browser SDK can also log in with an Endpoint's own username and
 * password, but Plivo documents access tokens as the recommended approach
 * for production, because they authenticate a browser session without the
 * Endpoint's password ever reaching the browser. A password handed to a
 * browser can be read by whoever uses it, never expires, and keeps working
 * after that person is removed from the product. A token expires. This is
 * also the model a Twilio installation's softphone already uses.
 *
 * Plivo requires these tokens to come from its own API — a JWT signed
 * locally, even with the account's auth token, is rejected — so this makes
 * one request per token.
 *
 * It deliberately does NOT use the installed plivo/plivo-php SDK's
 * TokenInterface::create(). That method contains a leftover print_r() of its
 * optional request arguments, which writes them into the output buffer ahead
 * of whatever response this application sends, corrupting the JSON the
 * browser parses. (Those arguments are the subject, lifetimes, permissions
 * and application id — not the account credentials, which the SDK keeps in a
 * separate array.) The SDK's shared response helper echoes on any non-2xx for
 * the same reason. It also drops a false permission flag through !empty() and
 * never checks the response for an error. The documented REST endpoint is
 * called directly instead, which is how Plivo's own examples do it.
 */
class PlivoWebRTCTokenProvider implements WebRTCTokenProviderInterface
{
    /**
     * How far "not before" is backdated. A server whose clock runs slightly
     * ahead of Plivo's — common on shared hosting — would otherwise mint a
     * token Plivo considers not yet valid, and registration would fail at
     * random. The backdating comes out of the token's lifetime, never on top
     * of it, so the 24-hour maximum still holds.
     */
    private const CLOCK_SKEW_SECONDS = 30;

    /**
     * Plivo documents a token's validity as 3 minutes to 24 hours. One second
     * short of the maximum, so a request cannot sit exactly on a boundary
     * Plivo may compare strictly.
     */
    private const MAX_TTL_SECONDS = 86399;

    /**
     * Plivo's documented minimum is 3 minutes from creation, and `nbf` is
     * backdated for clock skew — so the floor carries that back, leaving a
     * token that is still valid for a full 3 minutes from now.
     */
    private const MIN_TTL_SECONDS = 180 + self::CLOCK_SKEW_SECONDS;

    private const DEFAULT_TTL_SECONDS = 3600;

    private const REQUEST_TIMEOUT_SECONDS = 10;

    private array $credentials = [];

    public function initialize(array $credentials): static
    {
        $this->credentials = $credentials;

        return $this;
    }

    /**
     * @param  array  $options  endpoint_username (required) — the Endpoint the
     *                          browser registers as; app_id — the Application
     *                          its calls route through; ttl — seconds, capped
     *                          at Plivo's 24-hour maximum.
     * @return array{token: string, identity: string, provider: string, expires_in: int, extra: array}
     *
     * @throws RuntimeException
     */
    public function generateToken(string $identity, array $options = []): array
    {
        $authId = $this->credentials['auth_id'] ?? null;
        $authToken = $this->credentials['auth_token'] ?? null;

        if (! is_string($authId) || $authId === '' || ! is_string($authToken) || $authToken === '') {
            throw new RuntimeException('Plivo account credentials are not configured.');
        }

        $endpointUsername = $options['endpoint_username'] ?? null;

        if (! is_string($endpointUsername) || $endpointUsername === '') {
            throw new RuntimeException('Plivo endpoint username is required to issue a browser token.');
        }

        $ttl = min(max((int) ($options['ttl'] ?? self::DEFAULT_TTL_SECONDS), self::MIN_TTL_SECONDS), self::MAX_TTL_SECONDS);
        $now = now()->getTimestamp();
        $notBefore = $now - self::CLOCK_SKEW_SECONDS;
        $expires = $notBefore + $ttl;

        $body = [
            'iss' => $authId,
            'sub' => $endpointUsername,
            'nbf' => $notBefore,
            'exp' => $expires,
            'per' => ['voice' => ['incoming_allow' => true, 'outgoing_allow' => true]],
        ];

        if (is_string($options['app_id'] ?? null) && $options['app_id'] !== '') {
            $body['app'] = $options['app_id'];
        }

        try {
            $response = Http::withBasicAuth($authId, $authToken)
                ->acceptJson()
                ->asJson()
                ->timeout(self::REQUEST_TIMEOUT_SECONDS)
                ->post('https://api.plivo.com/v1/Account/'.rawurlencode($authId).'/JWT/Token/', $body);
        } catch (Throwable $e) {
            throw new RuntimeException('Could not reach Plivo to issue a browser token.', 0, $e);
        }

        if (! $response->successful()) {
            // The status alone. The body is Plivo's and is not guaranteed free
            // of account detail, so it stays out of an exception message that
            // may be logged or shown.
            throw new RuntimeException('Plivo refused to issue a browser token (HTTP '.$response->status().').');
        }

        $token = $response->json('token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Plivo did not return a browser token.');
        }

        return [
            'token' => $token,
            'identity' => $identity,
            'provider' => 'plivo',
            'expires_in' => $expires - $now,
            'extra' => ['type' => 'access_token'],
        ];
    }

    public function getName(): string
    {
        return 'plivo';
    }
}
