<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\UserKycVerification;
use App\Services\Telephony\TelephonyManager;
use App\Services\Telephony\VerificationProviderFactory;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * Sends a KYC phone verification code through whichever telephony
 * provider this installation is locked to.
 *
 * How the code travels is the provider's business — see
 * TwilioVerificationProvider and PlivoVerificationProvider — and whose
 * account it travels on is VerificationProviderFactory's. What stays here
 * is what the controller relies on: any failure is logged and rethrown,
 * so the customer who asked for the code is told it did not go.
 */
class SendPhoneVerificationSms
{
    use Dispatchable;

    public function __construct(
        public UserKycVerification $kyc
    ) {}

    public function handle(TelephonyManager $telephony): void
    {
        try {
            VerificationProviderFactory::forSending($telephony)->start($this->kyc);
        } catch (\Exception $e) {
            Log::error('Failed to send phone verification SMS', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $this->kyc->user_id,
                'phone_number' => $this->kyc->phone_number ?? 'N/A',
            ]);

            throw $e;
        }
    }
}
