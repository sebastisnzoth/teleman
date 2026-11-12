<?php

declare(strict_types=1);

namespace App\Contracts\Telephony;

/**
 * Provider-agnostic interface for generating browser/WebRTC tokens.
 *
 * Twilio: JWT AccessToken with VoiceGrant
 * Plivo: Endpoint credentials for browser login
 */
interface WebRTCTokenProviderInterface
{
    /**
     * Initialize with provider credentials.
     */
    public function initialize(array $credentials): static;

    /**
     * Generate a browser calling token/credentials for a user.
     *
     * @param  string  $identity  Unique identity for this user session
     * @param  array  $options  Provider-specific options (appSid, apiKey, etc.)
     * @return array{token: string, identity: string, provider: string, expires_in: int, extra: array}
     */
    public function generateToken(string $identity, array $options = []): array;

    /**
     * Get the provider name.
     */
    public function getName(): string;
}
