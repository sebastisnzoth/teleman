<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Campaign Settings
    |--------------------------------------------------------------------------
    |
    | These values will be used as defaults when creating new campaigns.
    | Individual campaigns can override these settings.
    |
    */

    'defaults' => [
        'max_concurrent_calls' => env('DEFAULT_MAX_CONCURRENT_CALLS', 20),
        'max_call_attempts' => 3,
        'retry_delay_minutes' => 30,
        'call_timeout_seconds' => 300,
        'voice' => 'Polly.Joanna',
    ],

    /*
    |--------------------------------------------------------------------------
    | System Capacity Limits
    |--------------------------------------------------------------------------
    |
    | These are hard limits to prevent system overload.
    | Adjust based on your server resources and Twilio limits.
    |
    */

    'limits' => [
        // Maximum number of campaigns that can run simultaneously
        'max_campaigns_concurrent' => 20,
        
        // Maximum contacts per single campaign
        'max_contacts_per_campaign' => 100000,
        
        // Maximum concurrent calls per campaign (upper limit)
        'max_concurrent_calls_per_campaign' => 50,
        
        // System-wide maximum concurrent calls
        'system_wide_max_concurrent_calls' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Fine-tune campaign processing performance and resource usage.
    |
    */

    'performance' => [
        // Number of contacts to fetch per processing iteration
        'contact_batch_size' => 200,
        
        // Delay before retrying a campaign job when no slots available
        'job_retry_delay_seconds' => 10,
        
        // Rate limit for campaign processing (jobs per minute)
        'rate_limit_calls_per_minute' => 60,
        
        // Maximum execution time for campaign jobs (seconds)
        'max_execution_time' => 300,
        
        // Memory limit per job (MB)
        'memory_limit' => 256,
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Define queue names and priorities for campaign processing.
    |
    */

    'queues' => [
        'campaigns' => 'campaigns',
        'calls' => 'campaign-calls',
        'default' => 'default',
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring & Logging
    |--------------------------------------------------------------------------
    |
    | Control how campaign activity is logged and monitored.
    |
    */

    'monitoring' => [
        // Enable detailed logging for campaign processing
        'detailed_logging' => env('CAMPAIGN_DETAILED_LOGGING', true),
        
        // Log every N contacts processed (to avoid log spam)
        'log_every_n_contacts' => 100,
        
        // Alert thresholds
        'alerts' => [
            'high_failure_rate_percentage' => 25, // Alert if failure rate > 25%
            'queue_depth_warning' => 1000, // Warn if queue has 1000+ jobs
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | TwiML Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for TwiML generation and call handling.
    |
    */

    'twiml' => [
        // Available voices for TTS
        'available_voices' => [
            'man' => 'Polly.Matthew',
            'woman' => 'Polly.Joanna',
            'alice' => 'alice',
            'Polly.Joanna' => 'Polly.Joanna',
            'Polly.Matthew' => 'Polly.Matthew',
        ],
        
        // TTS settings
        'tts' => [
            'default_language' => 'en-US',
            'rate' => 'medium',
            'pitch' => 'medium',
        ],
        
        // Call recording
        'recording' => [
            'enabled' => env('CAMPAIGN_RECORDING_ENABLED', false),
            'storage_path' => 'recordings/campaigns',
        ],
    ],

];
