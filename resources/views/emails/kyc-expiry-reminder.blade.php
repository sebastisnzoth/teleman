<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KYC Verification Expiring Soon</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f4f4f4; font-family: Arial, sans-serif; }
        .wrapper { width: 100%; padding: 40px 20px; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background-color: #d97706; padding: 32px 40px; text-align: center; }
        .header h1 { margin: 0; color: #ffffff; font-size: 24px; font-weight: 700; }
        .body { padding: 40px; color: #374151; }
        .body p { margin: 0 0 16px; font-size: 15px; line-height: 1.6; }
        .countdown { text-align: center; background-color: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 24px; margin: 24px 0; }
        .countdown .days { font-size: 48px; font-weight: 700; color: #d97706; line-height: 1; }
        .countdown .label { font-size: 14px; color: #92400e; margin-top: 4px; }
        .info-box { background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 20px 24px; margin: 24px 0; }
        .info-box p { margin: 0 0 8px; font-size: 14px; color: #374151; }
        .info-box p:last-child { margin-bottom: 0; }
        .footer { background-color: #f9fafb; padding: 24px 40px; text-align: center; border-top: 1px solid #e5e7eb; }
        .footer p { margin: 0; font-size: 13px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <h1>KYC Verification Expiring Soon</h1>
            </div>
            <div class="body">
                <p>Hello {{ $user->name }},</p>
                <p>This is a reminder that your KYC (Know Your Customer) verification is expiring soon. Please renew it to avoid any disruption to your account access.</p>

                <div class="countdown">
                    <div class="days">{{ $days_remaining }}</div>
                    <div class="label">{{ $days_remaining == 1 ? 'day' : 'days' }} remaining</div>
                </div>

                <div class="info-box">
                    @if($expires_at)
                    <p><strong>Expiry date:</strong> {{ $expires_at->format('F j, Y') }}</p>
                    @endif
                    @if($kyc->kyc_tier)
                    <p><strong>Current tier:</strong> {{ ucfirst($kyc->kyc_tier) }}</p>
                    @endif
                </div>

                <p>To renew your KYC verification, please log in to your account and submit the required documents before the expiry date.</p>
                <p>If your verification expires, certain features may become unavailable until you re-verify. We strongly recommend renewing as soon as possible.</p>
                <p>If you have any questions, please contact our support team.</p>
            </div>
            <div class="footer">
                <p>This is an automated message. Please do not reply to this email.</p>
                <p style="margin-top: 8px;">&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
