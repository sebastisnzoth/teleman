<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenRouter API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for OpenRouter AI service used for campaign message
    | generation and enhancement.
    |
    */

    'api_key' => env('OPENROUTER_API_KEY', ''),

    'api_url' => 'https://openrouter.ai/api/v1/chat/completions',

    // Default to Gemini Flash — the fastest free model (~800ms response time).
    // openrouter/free is an aggregator that routes to random available models,
    // which can be slow (3-6s). Direct model choice is MUCH faster.
    'model' => 'google/gemini-2.0-flash-exp:free',

    // Fallback models — tried in order if the agent's primary model fails.
    // Ordered fastest-first so voice responses stay low-latency.
    'fallback_models' => [
        'google/gemini-2.0-flash-exp:free',   // Fastest: ~0.8-1.5s
        'deepseek/deepseek-chat-v3-0324',     // Fast + reliable: ~2-3s
        'meta-llama/llama-3.1-8b-instruct:free', // Backup: ~2-4s
        'openrouter/free',                    // Last resort (aggregator, slow)
    ],

    'max_tokens' => 300,

    'temperature' => 0.7,

    'site_url' => env('APP_URL', 'http://localhost'),

    'app_name' => env('APP_NAME', 'Teleman AI'),

    /*
    |--------------------------------------------------------------------------
    | Model Pricing (cost per 1,000 tokens, USD)
    |--------------------------------------------------------------------------
    | Most OpenRouter models used here are free ($0). If paid models are added,
    | specify their input/output pricing here. Keys must be lowercase model IDs.
    */
    'model_pricing' => [
        // All current models are free via OpenRouter
        'openrouter/polaris-alpha'          => ['input' => 0.0, 'output' => 0.0],
        'openrouter/free'                   => ['input' => 0.0, 'output' => 0.0],
        'deepseek/deepseek-chat'            => ['input' => 0.0, 'output' => 0.0],
        'deepseek/deepseek-chat-v3-0324'   => ['input' => 0.0, 'output' => 0.0],
        'thudm/glm-4-5-air'                 => ['input' => 0.0, 'output' => 0.0],
        'moonshotai/kimi-k2'                => ['input' => 0.0, 'output' => 0.0],
    ],
];
