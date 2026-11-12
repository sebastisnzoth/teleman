<?php

namespace App\Jobs;

use App\Models\UserKycVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendKycRejectionEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public UserKycVerification $kyc
    ) {}

    public function handle(): void
    {
        $user = $this->kyc->user;

        Mail::send('emails.kyc-rejection', [
            'user' => $user,
            'kyc' => $this->kyc,
            'rejection_reason' => $this->kyc->rejection_reason,
        ], function ($message) use ($user) {
            $message->to($user->email, $user->name)
                ->subject('KYC Verification - Action Required');
        });

        \Log::info('KYC rejection email sent', [
            'user_id' => $user->id,
        ]);
    }
}
