<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Agent Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for AI Agent calling features
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    */
    
    'default_model' => env('AI_AGENT_DEFAULT_MODEL', 'openrouter/polaris-alpha'),
    
    'default_voice' => env('AI_AGENT_DEFAULT_VOICE', 'joanna'),
    
    'max_tokens' => env('AI_AGENT_MAX_TOKENS', 250),

    // Max characters for LLM response (shorter = faster TTS via Twilio <Say>; 0 = no cap)
    // 200 was too low — responses were being cut mid-sentence. 800 ≈ 2-3 natural
    // conversational sentences, which plays in ~12-15s — acceptable for voice.
    'max_response_chars' => env('AI_AGENT_MAX_RESPONSE_CHARS', 800),

    // Max characters of knowledge base included in system prompt (0 = no cap; reduces latency)
    'max_knowledge_base_chars' => env('AI_AGENT_MAX_KNOWLEDGE_BASE_CHARS', 4000),
    
    'temperature' => env('AI_AGENT_TEMPERATURE', 0.7),
    
    'streaming' => env('AI_AGENT_STREAMING', true),
    
    /*
    |--------------------------------------------------------------------------
    | Voice AI Base Instruction File
    |--------------------------------------------------------------------------
    |
    | Path to the markdown file containing the voice AI base instruction.
    | This is ALWAYS prepended to the user's custom system prompt.
    | You can edit: public/voice-ai-base-instruction.md
    |
    */
    
    'voice_ai_base_instruction_file' => public_path('voice-ai-base-instruction.md'),
    
    /*
    |--------------------------------------------------------------------------
    | Available Models (via OpenRouter)
    |--------------------------------------------------------------------------
    */
    
    'openrouter_models' => [
        // Sorted by speed: fastest first (best for voice AI low-latency)
        [
            'id' => 'openrouter/polaris-alpha',
            'name' => 'Polaris Alpha',
            'cost' => 'FREE',
            'cost_per_1k_tokens' => 0,
            'speed' => 'Ultra Fast (~1s)',
            'context' => '32K tokens',
        ],
        [
            'id' => 'openai/gpt-oss-20b:free',
            'name' => 'GPT OSS 20B',
            'cost' => 'FREE',
            'cost_per_1k_tokens' => 0,
            'speed' => 'Very Fast (~1-2s)',
            'context' => '8K tokens',
        ],
        [
            'id' => 'deepseek/deepseek-chat-v3.1:free',
            'name' => 'DeepSeek Chat v3.1',
            'cost' => 'FREE',
            'cost_per_1k_tokens' => 0,
            'speed' => 'Fast (~2-3s)',
            'context' => '64K tokens',
        ],
        [
            'id' => 'deepseek/deepseek-chat-v3-0324:free',
            'name' => 'DeepSeek Chat v3-0324',
            'cost' => 'FREE',
            'cost_per_1k_tokens' => 0,
            'speed' => 'Fast (~2-3s)',
            'context' => '64K tokens',
        ],
        [
            'id' => 'z-ai/glm-4.5-air:free',
            'name' => 'GLM 4.5 Air',
            'cost' => 'FREE',
            'cost_per_1k_tokens' => 0,
            'speed' => 'Fast (~2-4s)',
            'context' => '128K tokens',
        ],
        [
            'id' => 'moonshotai/kimi-k2:free',
            'name' => 'Kimi K2',
            'cost' => 'FREE',
            'cost_per_1k_tokens' => 0,
            'speed' => 'Fast (~2-4s)',
            'context' => '128K tokens',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy Models (kept for backward compatibility)
    |--------------------------------------------------------------------------
    */
    
    'models' => [
        [
            'id' => 'google/gemini-flash-1.5',
            'name' => 'Gemini Flash 1.5',
            'cost' => 'FREE',
            'cost_per_1k_tokens' => 0,
            'speed' => 'Very Fast',
            'context' => '1M tokens',
        ],
        [
            'id' => 'google/gemini-flash-2.0',
            'name' => 'Gemini Flash 2.0',
            'cost' => 'FREE',
            'cost_per_1k_tokens' => 0,
            'speed' => 'Ultra Fast',
            'context' => '1M tokens',
        ],
        [
            'id' => 'meta-llama/llama-3.1-8b-instruct',
            'name' => 'Llama 3.1 8B',
            'cost' => 'FREE',
            'cost_per_1k_tokens' => 0,
            'speed' => 'Fast',
            'context' => '128K tokens',
        ],
        [
            'id' => 'meta-llama/llama-3.1-70b-instruct',
            'name' => 'Llama 3.1 70B',
            'cost' => 'FREE',
            'cost_per_1k_tokens' => 0,
            'speed' => 'Medium',
            'context' => '128K tokens',
        ],
        [
            'id' => 'openai/gpt-4o-mini',
            'name' => 'GPT-4o Mini',
            'cost' => '$0.15/1M input, $0.60/1M output',
            'cost_per_1k_tokens' => 0.15,
            'speed' => 'Very Fast',
            'context' => '128K tokens',
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Available Voices (Twilio <Say> — Polly Generative & Google Chirp3-HD)
    |--------------------------------------------------------------------------
    */
    
    'voices' => [
        // Amazon Polly Generative (most human-sounding)
        [
            'id' => 'joanna',
            'name' => 'Joanna',
            'description' => 'Warm & natural, best for support calls',
            'gender' => 'Female',
            'engine' => 'Polly Generative',
        ],
        [
            'id' => 'matthew',
            'name' => 'Matthew',
            'description' => 'Confident & clear, great for sales',
            'gender' => 'Male',
            'engine' => 'Polly Generative',
        ],
        [
            'id' => 'ruth',
            'name' => 'Ruth',
            'description' => 'Professional & composed',
            'gender' => 'Female',
            'engine' => 'Polly Generative',
        ],
        [
            'id' => 'stephen',
            'name' => 'Stephen',
            'description' => 'Authoritative & trustworthy',
            'gender' => 'Male',
            'engine' => 'Polly Generative',
        ],
        // Google Chirp3-HD (expressive, natural)
        [
            'id' => 'aoede',
            'name' => 'Aoede',
            'description' => 'Bright & expressive',
            'gender' => 'Female',
            'engine' => 'Google Chirp3-HD',
        ],
        [
            'id' => 'charon',
            'name' => 'Charon',
            'description' => 'Deep & composed',
            'gender' => 'Male',
            'engine' => 'Google Chirp3-HD',
        ],
        [
            'id' => 'kore',
            'name' => 'Kore',
            'description' => 'Warm & friendly',
            'gender' => 'Female',
            'engine' => 'Google Chirp3-HD',
        ],
        [
            'id' => 'fenrir',
            'name' => 'Fenrir',
            'description' => 'Energetic & dynamic',
            'gender' => 'Male',
            'engine' => 'Google Chirp3-HD',
        ],
        [
            'id' => 'puck',
            'name' => 'Puck',
            'description' => 'Playful & engaging',
            'gender' => 'Male',
            'engine' => 'Google Chirp3-HD',
        ],
        [
            'id' => 'leda',
            'name' => 'Leda',
            'description' => 'Calm & soothing',
            'gender' => 'Female',
            'engine' => 'Google Chirp3-HD',
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Prompt Templates
    |--------------------------------------------------------------------------
    */
    
    'templates' => [
        [
            'id' => 'support',
            'name' => 'Customer Support',
            'type' => 'inbound',
            'prompt' => 'You are a helpful customer support AI assistant for Teleman AI. Be friendly, professional, and concise. Answer questions about our automated calling platform. If you can\'t help, offer to transfer to a human agent. Keep responses under 3 sentences.',
        ],
        [
            'id' => 'sales',
            'name' => 'Sales Agent',
            'type' => 'both',
            'prompt' => 'You are a sales AI assistant for Teleman AI. Qualify leads by asking about their business needs, team size, and call volume. Be enthusiastic but not pushy. Schedule demos with qualified leads. Keep responses conversational and under 3 sentences.',
        ],
        [
            'id' => 'survey',
            'name' => 'Survey Bot',
            'type' => 'outbound',
            'prompt' => 'You are conducting a quick customer satisfaction survey. Be polite and brief. Ask the survey questions one at a time and thank them for each response. If they want to stop, thank them and end the call gracefully.',
        ],
        [
            'id' => 'lead_qualification',
            'name' => 'Lead Qualification',
            'type' => 'outbound',
            'prompt' => 'You are reaching out to qualify potential leads. Introduce yourself briefly, confirm their interest, and ask qualifying questions about their business needs. Be respectful of their time. If they\'re not interested, thank them politely and end the call.',
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Conversation Settings
    |--------------------------------------------------------------------------
    */
    
    'conversation' => [
        'max_turns' => 50, // Maximum conversation turns
        'max_duration' => 600, // Maximum call duration (seconds)
        'silence_timeout' => 10, // Seconds of silence before ending call
        'interrupt_threshold' => 0.5, // Threshold for detecting interruptions
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Default System Prompts
    |--------------------------------------------------------------------------
    */
    
    'prompts' => [
        'inbound_support' => "You are a helpful customer support AI assistant for Teleman AI. Be friendly, professional, and concise. Answer questions about our automated calling platform. If you can't help, offer to transfer to a human agent. Keep responses under 3 sentences.",
        
        'inbound_sales' => "You are a sales AI assistant for Teleman AI. Qualify leads by asking about their business needs, team size, and call volume. Be enthusiastic but not pushy. Schedule demos with qualified leads. Keep responses conversational and under 3 sentences.",
        
        'outbound_survey' => "You are conducting a quick customer satisfaction survey. Be polite and brief. Ask the survey questions one at a time and thank them for each response. If they want to stop, thank them and end the call gracefully.",
        
        'outbound_lead_qualification' => "You are reaching out to qualify potential leads. Introduce yourself briefly, confirm their interest, and ask qualifying questions about their business needs. Be respectful of their time. If they're not interested, thank them politely and end the call.",
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Transfer Settings
    |--------------------------------------------------------------------------
    */
    
    'transfer' => [
        'enabled' => true,
        'keywords' => ['human', 'agent', 'person', 'representative', 'manager', 'speak to someone'],
        'confirmation' => "I'll transfer you to a human agent right away. Please hold.",
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Recording Settings
    |--------------------------------------------------------------------------
    */
    
    'recording' => [
        'enabled' => true,
        'format' => 'mp3',
        'transcribe' => true,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | WebSocket Server Configuration
    |--------------------------------------------------------------------------
    */
    
    'websocket' => [
        'url' => env('WEBSOCKET_URL', 'ws://localhost:8090'),
        'host' => env('WEBSOCKET_HOST', '0.0.0.0'),
        'port' => env('WEBSOCKET_PORT', 8090),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Cost Tracking
    |--------------------------------------------------------------------------
    */
    
    'cost_tracking' => [
        'enabled' => true,
        'deepgram_cost_per_min' => 0.0043,
        'twilio_cost_per_min' => 0.0085,
    ],
];
