<?php

namespace App\Jobs;

use App\Models\UserKycVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendKycExpiryReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public UserKycVerification $kyc
    ) {}

    public function handle(): void
    {
        $user = $this->kyc->user;
        $daysRemaining = $this->kyc->expires_at?->diffInDays(now());

        Mail::send('emails.kyc-expiry-reminder', [
            'user' => $user,
            'kyc' => $this->kyc,
            'days_remaining' => $daysRemaining,
            'expires_at' => $this->kyc->expires_at,
        ], function ($message) use ($user, $daysRemaining) {
            $message->to($user->email, $user->name)
                ->subject("KYC Verification Expires in {$daysRemaining} Days");
        });

        \Log::info('KYC expiry reminder email sent', [
            'user_id' => $user->id,
            'days_remaining' => $daysRemaining,
        ]);
    }
}
