<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KYC Verification Approved</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f4f4f4; font-family: Arial, sans-serif; }
        .wrapper { width: 100%; padding: 40px 20px; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background-color: #16a34a; padding: 32px 40px; text-align: center; }
        .header h1 { margin: 0; color: #ffffff; font-size: 24px; font-weight: 700; }
        .body { padding: 40px; color: #374151; }
        .body p { margin: 0 0 16px; font-size: 15px; line-height: 1.6; }
        .badge { display: inline-block; background-color: #dcfce7; color: #15803d; padding: 8px 20px; border-radius: 20px; font-size: 14px; font-weight: 600; margin: 8px 0 24px; }
        .info-box { background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 20px 24px; margin: 24px 0; }
        .info-box p { margin: 0; font-size: 14px; color: #166534; }
        .footer { background-color: #f9fafb; padding: 24px 40px; text-align: center; border-top: 1px solid #e5e7eb; }
        .footer p { margin: 0; font-size: 13px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <h1>KYC Verification Approved</h1>
            </div>
            <div class="body">
                <p>Hello {{ $user->name }},</p>
                <p>We're pleased to inform you that your KYC (Know Your Customer) verification has been <strong>approved</strong>.</p>

                <div style="text-align: center;">
                    <span class="badge">&#10003; {{ $tier_label }}</span>
                </div>

                <p>Your account is now fully verified and you have access to all features associated with your verification tier.</p>

                <div class="info-box">
                    <p><strong>Verification Details</strong></p>
                    <p style="margin-top: 8px;">Tier: {{ $tier_label }}</p>
                    @if($kyc->verified_at)
                    <p>Verified on: {{ $kyc->verified_at->format('F j, Y') }}</p>
                    @endif
                    @if($kyc->expires_at)
                    <p>Valid until: {{ $kyc->expires_at->format('F j, Y') }}</p>
                    @endif
                </div>

                <p>If you have any questions, please don't hesitate to contact our support team.</p>
                <p>Thank you for completing the verification process.</p>
            </div>
            <div class="footer">
                <p>This is an automated message. Please do not reply to this email.</p>
                <p style="margin-top: 8px;">&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
