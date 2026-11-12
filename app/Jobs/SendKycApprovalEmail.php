<?php

namespace App\Jobs;

use App\Models\UserKycVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendKycApprovalEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public UserKycVerification $kyc
    ) {}

    public function handle(): void
    {
        $user = $this->kyc->user;
        $tier = $this->kyc->kyc_tier;

        $tierLabels = [
            'basic' => 'Basic (Tier 1)',
            'business' => 'Business (Tier 2)',
        ];

        $tierLabel = $tierLabels[$tier] ?? $tier;

        Mail::send('emails.kyc-approval', [
            'user' => $user,
            'kyc' => $this->kyc,
            'tier_label' => $tierLabel,
        ], function ($message) use ($user, $tierLabel) {
            $message->to($user->email, $user->name)
                ->subject("KYC Verification Approved - {$tierLabel}");
        });

        \Log::info('KYC approval email sent', [
            'user_id' => $user->id,
            'tier' => $tier,
        ]);
    }
}
