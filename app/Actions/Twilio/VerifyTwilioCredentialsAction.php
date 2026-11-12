<?php

declare(strict_types=1);

namespace App\Actions\Twilio;

use App\Services\TwilioService;

class VerifyTwilioCredentialsAction
{
    public function __construct(
        private TwilioService $twilioService
    ) {}

    /**
     * Verify Twilio credentials without storing them
     */
    public function execute(string $accountSid, string $authToken): array
    {
        return $this->twilioService->verifyCredentials($accountSid, $authToken);
    }
}
