<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KYC Verification - Action Required</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f4f4f4; font-family: Arial, sans-serif; }
        .wrapper { width: 100%; padding: 40px 20px; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background-color: #dc2626; padding: 32px 40px; text-align: center; }
        .header h1 { margin: 0; color: #ffffff; font-size: 24px; font-weight: 700; }
        .body { padding: 40px; color: #374151; }
        .body p { margin: 0 0 16px; font-size: 15px; line-height: 1.6; }
        .reason-box { background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 20px 24px; margin: 24px 0; }
        .reason-box p { margin: 0; font-size: 14px; color: #991b1b; }
        .steps-box { background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 20px 24px; margin: 24px 0; }
        .steps-box p { margin: 0 0 8px; font-size: 14px; color: #374151; }
        .steps-box ul { margin: 8px 0 0; padding-left: 20px; }
        .steps-box li { font-size: 14px; color: #374151; margin-bottom: 6px; }
        .footer { background-color: #f9fafb; padding: 24px 40px; text-align: center; border-top: 1px solid #e5e7eb; }
        .footer p { margin: 0; font-size: 13px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <h1>KYC Verification - Action Required</h1>
            </div>
            <div class="body">
                <p>Hello {{ $user->name }},</p>
                <p>We have reviewed your KYC (Know Your Customer) verification submission and unfortunately we were unable to approve it at this time.</p>

                @if($rejection_reason)
                <div class="reason-box">
                    <p><strong>Reason for rejection:</strong></p>
                    <p style="margin-top: 8px;">{{ $rejection_reason }}</p>
                </div>
                @endif

                <div class="steps-box">
                    <p><strong>What you can do next:</strong></p>
                    <ul>
                        <li>Review the reason provided above carefully.</li>
                        <li>Gather the correct or updated documents.</li>
                        <li>Re-submit your KYC verification from your account dashboard.</li>
                    </ul>
                </div>

                <p>If you believe this decision was made in error or need further assistance, please contact our support team and we'll be happy to help.</p>
                <p>We appreciate your understanding and look forward to completing your verification.</p>
            </div>
            <div class="footer">
                <p>This is an automated message. Please do not reply to this email.</p>
                <p style="margin-top: 8px;">&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
