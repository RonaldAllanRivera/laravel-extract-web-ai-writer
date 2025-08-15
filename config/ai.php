<?php

return [
    // Which provider to use for generation. Currently supports 'openai'.
    'provider' => env('AI_PROVIDER', 'openai'),

    // OpenAI-specific configuration
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('AI_MODEL', 'gpt-4o-mini'),
        'temperature' => (float) env('AI_TEMPERATURE', 0.7),
        // Max tokens for the model to generate in the response
        'max_output_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 2200),
        // Token budget for input/truncation of source text before sending to the model
        'input_token_budget' => (int) env('AI_INPUT_TOKEN_BUDGET', 8000),
    ],

    // Simple per-minute rate limit to avoid hitting provider limits
    'rate_limit_per_minute' => (int) env('AI_RATE_LIMIT_PER_MINUTE', 60),

    // Optional queue settings for generation jobs
    'queue' => [
        'enabled' => (bool) env('AI_QUEUE_ENABLED', false),
        'connection' => env('AI_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),
        'queue' => env('AI_QUEUE', 'default'),
    ],

    // Layout defaults; can be referenced by UI and generation service
    'layouts' => [
        'interstitial' => [
            'enabled' => (bool) env('AI_LAYOUT_INTERSTITIAL_ENABLED', true),
            'max_output_tokens' => (int) env('AI_LAYOUT_INTERSTITIAL_MAX_TOKENS', env('AI_MAX_OUTPUT_TOKENS', 2200)),
        ],
        'advertorial' => [
            'enabled' => (bool) env('AI_LAYOUT_ADVERTORIAL_ENABLED', true),
            'max_output_tokens' => (int) env('AI_LAYOUT_ADVERTORIAL_MAX_TOKENS', env('AI_MAX_OUTPUT_TOKENS', 2200)),
        ],
    ],
];
