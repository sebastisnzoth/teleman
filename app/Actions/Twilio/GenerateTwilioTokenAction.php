<?php

declare(strict_types=1);

namespace App\Actions\Twilio;

use App\Models\TwilioGlobalConfig;
use App\Models\User;
use Exception;
use Twilio\Jwt\AccessToken;
use Twilio\Jwt\Grants\VoiceGrant;

class GenerateTwilioTokenAction
{
    /**
     * Generate Twilio access token for WebRTC using global configuration
     */
    public function execute(User $user): string
    {
        $config = TwilioGlobalConfig::active();

        if (!$config) {
            throw new Exception('Global Twilio configuration not found. Run: php artisan twilio:configure');
        }

        // Create unique identity for this user
        $identity = 'user_' . $user->id;

        // Create access token using global API Key credentials
        $token = new AccessToken(
            $config->account_sid,                    // Account SID
            $config->api_key_sid,                    // API Key SID
            $config->getDecryptedApiKeySecret(),     // API Key Secret (decrypted)
            3600,                                    // Token valid for 1 hour
            $identity                                // Unique identity for this user
        );

        // Create Voice grant for calling
        $voiceGrant = new VoiceGrant();
        $voiceGrant->setOutgoingApplicationSid($config->twiml_app_sid);

        // Optionally allow incoming calls to this identity
        $voiceGrant->setIncomingAllow(true);

        // Add the grant to the token
        $token->addGrant($voiceGrant);

        return $token->toJWT();
    }
}
