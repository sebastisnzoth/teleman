<?php

declare(strict_types=1);

namespace App\Actions\Twilio;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteTwilioCredentialsAction
{
    /**
     * Delete Twilio credentials for a user
     */
    public function execute(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->twilioCredentials()->delete();
            $user->markTwilioAsNotConfigured();
        });
    }
}
