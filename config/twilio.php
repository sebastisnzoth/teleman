<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Twilio Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control how the application interacts with Twilio's API.
    | Users will provide their own Account SID and Auth Token through the UI.
    |
    */

    // Default voice settings for Text-to-Speech
    'tts' => [
        'default_voice' => env('TWILIO_DEFAULT_VOICE', 'Polly.Joanna'),
        'default_language' => env('TWILIO_DEFAULT_LANGUAGE', 'en-US'),
        'default_gender' => env('TWILIO_DEFAULT_GENDER', 'female'),
    ],

    // Call settings
    'calls' => [
        'timeout' => env('TWILIO_CALL_TIMEOUT', 60), // seconds
        'max_concurrent' => env('TWILIO_MAX_CONCURRENT_CALLS', 5),
        'retry_attempts' => env('TWILIO_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('TWILIO_RETRY_DELAY', 5), // minutes
    ],

    // Recording settings
    'recording' => [
        'max_length' => env('TWILIO_RECORDING_MAX_LENGTH', 30), // seconds
        'retention_days' => env('TWILIO_RECORDING_RETENTION_DAYS', 30),
    ],

    // DTMF settings
    'dtmf' => [
        'timeout' => env('TWILIO_DTMF_TIMEOUT', 5), // seconds
        'max_digits' => env('TWILIO_DTMF_MAX_DIGITS', 1),
    ],

    // Webhook settings
    'webhooks' => [
        'validate_signature' => env('TWILIO_VALIDATE_WEBHOOKS', true),
    ],

    // Pricing estimates (US rates - simplified)
    'pricing' => [
        // Standard Programmable Voice API (Campaign/API calls)
        'api_cost_per_minute' => 0.013, // $0.013 per minute
        
        // Voice SDK (Browser/WebRTC Softphone calls)
        'voice_sdk_cost_per_minute' => 0.0085, // $0.0085 voice + $0.005 SDK fee = $0.0135 total
        'voice_sdk_fee_per_minute' => 0.005,    // Additional Voice SDK usage fee
        'voice_sdk_total_per_minute' => 0.0135, // Total cost for browser calls
        
        // SIP Trunk (BYOT - Bring Your Own Trunk)
        'sip_trunk_cost_per_minute' => 0.0085, // 35% savings vs standard
        
        // Legacy field (use api_cost_per_minute instead)
        'cost_per_minute' => 0.013, // $0.013 per minute
        
        // Other costs
        'recording_cost_per_minute' => 0.0025, // $0.0025 per minute
        'tts_cost_per_million_chars' => 4.00, // $4 per 1M characters
    ],

    // Supported voices by language and gender
    'voices' => [
        'en-US' => [
            'male' => ['Polly.Matthew', 'Polly.Joey'],
            'female' => ['Polly.Joanna', 'Polly.Kendra', 'Polly.Kimberly', 'Polly.Salli'],
            'neutral' => ['Polly.Ivy'],
        ],
        'en-GB' => [
            'male' => ['Polly.Brian'],
            'female' => ['Polly.Amy', 'Polly.Emma'],
            'neutral' => ['Polly.Emma'],
        ],
        'es-ES' => [
            'male' => ['Polly.Enrique'],
            'female' => ['Polly.Conchita', 'Polly.Lucia'],
            'neutral' => ['Polly.Lucia'],
        ],
        'es-MX' => [
            'male' => ['Polly.Miguel'],
            'female' => ['Polly.Mia'],
            'neutral' => ['Polly.Mia'],
        ],
        'fr-FR' => [
            'male' => ['Polly.Mathieu'],
            'female' => ['Polly.Celine', 'Polly.Lea'],
            'neutral' => ['Polly.Lea'],
        ],
        'de-DE' => [
            'male' => ['Polly.Hans'],
            'female' => ['Polly.Marlene', 'Polly.Vicki'],
            'neutral' => ['Polly.Vicki'],
        ],
        'it-IT' => [
            'male' => ['Polly.Giorgio'],
            'female' => ['Polly.Carla', 'Polly.Bianca'],
            'neutral' => ['Polly.Bianca'],
        ],
        'pt-BR' => [
            'male' => ['Polly.Ricardo'],
            'female' => ['Polly.Vitoria', 'Polly.Camila'],
            'neutral' => ['Polly.Camila'],
        ],
    ],

    // Supported languages
    'languages' => [
        'en-US' => 'English (US)',
        'en-GB' => 'English (UK)',
        'es-ES' => 'Spanish (Spain)',
        'es-MX' => 'Spanish (Mexico)',
        'fr-FR' => 'French',
        'de-DE' => 'German',
        'it-IT' => 'Italian',
        'pt-BR' => 'Portuguese (Brazil)',
    ],

];
