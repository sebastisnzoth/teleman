<?php

declare(strict_types=1);

namespace App\Actions\Twilio;

use App\Models\TwilioCredential;
use App\Models\User;
use App\Services\TwilioService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreTwilioCredentialsAction
{
    public function __construct(
        private TwilioService $twilioService
    ) {}

    /**
     * Store or update Twilio credentials for a user
     */
    public function execute(User $user, array $data): TwilioCredential
    {
        return DB::transaction(function () use ($user, $data) {
            // First, verify the credentials are valid
            $verification = $this->twilioService->verifyCredentials(
                $data['account_sid'],
                $data['auth_token']
            );

            if (!$verification['valid']) {
                throw new Exception('Invalid Twilio credentials: ' . ($verification['error'] ?? 'Unknown error'));
            }

            // Deactivate any existing credentials
            $user->twilioCredentials()->update(['is_active' => false]);

            // Create or update credential
            $credential = $user->twilioCredentials()->updateOrCreate(
                ['account_sid' => $data['account_sid']],
                [
                    'auth_token' => $data['auth_token'],
                    'phone_number' => $verification['phone_number'] ?? $data['phone_number'] ?? null,
                    'is_active' => true,
                    'verified_at' => now(),
                ]
            );

            // Mark user as having Twilio configured
            $user->markTwilioAsConfigured();

            Log::info('Twilio credentials stored', [
                'user_id' => $user->id,
                'account_sid' => $data['account_sid'],
            ]);

            return $credential;
        });
    }
}
