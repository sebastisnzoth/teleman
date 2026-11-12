<?php

declare(strict_types=1);

namespace App\Services\Telephony\Twilio;

use App\Contracts\Telephony\WebRTCTokenProviderInterface;
use Exception;
use Twilio\Jwt\AccessToken;
use Twilio\Jwt\Grants\VoiceGrant;

/**
 * Twilio WebRTC token generator.
 *
 * Creates JWT access tokens with a VoiceGrant for browser calling — the
 * same token shape App\Actions\Twilio\GenerateTwilioTokenAction builds
 * today, just behind the provider-agnostic interface.
 */
class TwilioWebRTCTokenProvider implements WebRTCTokenProviderInterface
{
    private array $credentials = [];

    public function initialize(array $credentials): static
    {
        $this->credentials = $credentials;

        return $this;
    }

    public function generateToken(string $identity, array $options = []): array
    {
        $accountSid = $this->credentials['account_sid']
            ?? throw new Exception('Twilio account_sid not configured.');

        $apiKeySid = $options['api_key_sid']
            ?? $this->credentials['api_key_sid']
            ?? throw new Exception('Twilio api_key_sid not configured.');

        $apiKeySecret = $options['api_key_secret']
            ?? $this->credentials['api_key_secret']
            ?? throw new Exception('Twilio api_key_secret not configured.');

        $twimlAppSid = $options['twiml_app_sid']
            ?? $this->credentials['twiml_app_sid']
            ?? throw new Exception('Twilio twiml_app_sid not configured.');

        $ttl = $options['ttl'] ?? 3600;

        $token = new AccessToken(
            $accountSid,
            $apiKeySid,
            $apiKeySecret,
            $ttl,
            $identity
        );

        $voiceGrant = new VoiceGrant;
        $voiceGrant->setOutgoingApplicationSid($twimlAppSid);
        $voiceGrant->setIncomingAllow(true);

        $token->addGrant($voiceGrant);

        return [
            'token' => $token->toJWT(),
            'identity' => $identity,
            'provider' => 'twilio',
            'expires_in' => $ttl,
            'extra' => [
                'type' => 'jwt',
            ],
        ];
    }

    public function getName(): string
    {
        return 'twilio';
    }
}
