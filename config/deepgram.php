<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Deepgram API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Deepgram Speech-to-Text API
    | Sign up at https://deepgram.com to get your API key
    |
    */

    'api_key' => env('DEEPGRAM_API_KEY'),
    
    'api_url' => env('DEEPGRAM_API_URL', 'https://api.deepgram.com/v1'),
    
    /*
    |--------------------------------------------------------------------------
    | Streaming Configuration
    |--------------------------------------------------------------------------
    */
    
    'streaming' => [
        'model' => env('DEEPGRAM_MODEL', 'nova-2'),
        'language' => env('DEEPGRAM_LANGUAGE', 'en-US'),
        'encoding' => env('DEEPGRAM_ENCODING', 'mulaw'),
        'sample_rate' => env('DEEPGRAM_SAMPLE_RATE', 8000),
        'channels' => env('DEEPGRAM_CHANNELS', 1),
        'interim_results' => env('DEEPGRAM_INTERIM_RESULTS', false),
        'punctuate' => env('DEEPGRAM_PUNCTUATE', true),
        'smart_format' => env('DEEPGRAM_SMART_FORMAT', true),
        'vad_events' => env('DEEPGRAM_VAD_EVENTS', true), // Voice Activity Detection
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    
    'features' => [
        'diarize' => false, // Speaker diarization (who is speaking)
        'ner' => false, // Named Entity Recognition
        'sentiment' => false, // Sentiment analysis
        'summarize' => false, // Conversation summarization
    ],
];
