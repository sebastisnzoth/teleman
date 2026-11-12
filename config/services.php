<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'twilio' => [
        'sid' => env('TWILIO_ACCOUNT_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'phone_number' => env('TWILIO_PHONE_NUMBER'),
        'verify_service_sid' => env('TWILIO_VERIFY_SERVICE_SID'), // For Twilio Verify API
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET_KEY'),
        'public' => env('STRIPE_PUBLIC_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'test_mode' => env('STRIPE_TEST_MODE', true),
        'fee_percentage' => env('STRIPE_FEE_PERCENTAGE', 2.9),
        'fee_fixed' => env('STRIPE_FEE_FIXED', 0.30),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'use_direct' => env('AI_AGENT_USE_OPENAI', true),
        'tts_model' => env('OPENAI_TTS_MODEL', 'tts-1'),
        'tts_voice' => env('OPENAI_TTS_VOICE', 'alloy'),
    ],

    'razorpay' => [
        'api_key' => env('RAZORPAY_API_KEY'),
        'secret_key' => env('RAZORPAY_SECRET_KEY'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
        'test_mode' => env('RAZORPAY_TEST_MODE', true),
        'fee_percentage' => env('RAZORPAY_FEE_PERCENTAGE', 2.0),
        'fee_fixed' => env('RAZORPAY_FEE_FIXED', 0.0),
    ],

    'paypal' => [
        'client_id'     => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'webhook_id'    => env('PAYPAL_WEBHOOK_ID'),
        'test_mode'     => env('PAYPAL_TEST_MODE', true),
        'fee_percentage'=> env('PAYPAL_FEE_PERCENTAGE', 3.49),
        'fee_fixed'     => env('PAYPAL_FEE_FIXED', 0.49),
    ],

    'paystack' => [
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'test_mode' => env('PAYSTACK_TEST_MODE', true),
        'fee_percentage' => env('PAYSTACK_FEE_PERCENTAGE', 1.5),
        'fee_fixed' => env('PAYSTACK_FEE_FIXED', 0.0),
        'supported_currencies' => env('PAYSTACK_SUPPORTED_CURRENCIES') ? array_filter(explode(',', env('PAYSTACK_SUPPORTED_CURRENCIES'))) : null,
    ],

    'base_currency' => env('BASE_CURRENCY', 'USD'),
    'exchange_rate_mode' => env('EXCHANGE_RATE_MODE', 'auto'), // 'auto' or 'manual'

    'flutterwave' => [
        'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
        'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
        'encryption_key' => env('FLUTTERWAVE_ENCRYPTION_KEY'),
        'secret_hash' => env('FLUTTERWAVE_SECRET_HASH'),
        'test_mode' => env('FLUTTERWAVE_TEST_MODE', true),
        'fee_percentage' => env('FLUTTERWAVE_FEE_PERCENTAGE', 1.4),
        'fee_fixed' => env('FLUTTERWAVE_FEE_FIXED', 0.0),
    ],

    'cryptomus' => [
        'merchant_uuid' => env('CRYPTOMUS_MERCHANT_UUID'),
        'payment_key' => env('CRYPTOMUS_PAYMENT_KEY'),
        'test_mode' => env('CRYPTOMUS_TEST_MODE', true),
    ],

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'enabled' => env('TURNSTILE_ENABLED', false),
    ],

];
