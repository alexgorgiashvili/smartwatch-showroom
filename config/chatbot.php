<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Supervisor Configuration
    |--------------------------------------------------------------------------
    */
    'supervisor' => [
        'enabled' => env('CHATBOT_SUPERVISOR_ENABLED', true),
        'model' => env('CHATBOT_SUPERVISOR_MODEL', 'gpt-4.1-mini'),
    ],

    // Only the website widget can enter this cohort. Zero leaves existing
    // widget and omnichannel behavior on the baseline model.
    'widget_v2' => [
        'rollout_percent' => env('CHATBOT_WIDGET_V2_PERCENT', 0),
        'model' => env('CHATBOT_WIDGET_V2_MODEL', 'gpt-6-luna'),
    ],

    // Standard API rates in USD per million text tokens, checked 2026-09-26.
    // Keep these estimates separate from the provider's billing records.
    'model_pricing_usd_per_million' => [
        'gpt-4.1-mini' => ['input' => 0.40, 'cached_input' => 0.10, 'cache_write' => 0.40, 'output' => 1.60],
        'gpt-4.1-nano' => ['input' => 0.10, 'cached_input' => 0.025, 'cache_write' => 0.10, 'output' => 0.40],
        'gpt-6-luna' => ['input' => 0.10, 'cached_input' => 0.01, 'cache_write' => 0.125, 'output' => 0.50],
    ],

    /*
    |--------------------------------------------------------------------------
    | Specialized Agents
    |--------------------------------------------------------------------------
    */
    'agents' => [
        'inventory' => \App\Services\Chatbot\Agents\InventoryAgent::class,
        'comparison' => \App\Services\Chatbot\Agents\ComparisonAgent::class,
        'general' => \App\Services\Chatbot\Agents\GeneralAgent::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Parallel Execution
    |--------------------------------------------------------------------------
    */
    'parallel_execution' => [
        'enabled' => env('CHATBOT_PARALLEL_EXECUTION_ENABLED', true),
        'timeout' => env('CHATBOT_PARALLEL_TASK_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Layer Caching
    |--------------------------------------------------------------------------
    */
    'caching' => [
        'enabled' => env('CHATBOT_CACHE_ENABLED', true),
        'prompt_version' => env('CHATBOT_PROMPT_VERSION', 'v1'),
        'catalog_version' => env('CHATBOT_CATALOG_VERSION', 'v1'),
        'layers' => [
            'embedding' => [
                'ttl' => env('CHATBOT_EMBEDDING_CACHE_TTL', 3600),
            ],
            'semantic' => [
                'ttl' => env('CHATBOT_SEMANTIC_CACHE_TTL', 1800),
                'threshold' => env('CHATBOT_SEMANTIC_CACHE_THRESHOLD', 0.95),
            ],
            'response' => [
                'ttl' => env('CHATBOT_RESPONSE_CACHE_TTL', 600),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker
    |--------------------------------------------------------------------------
    */
    'circuit_breaker' => [
        'enabled' => env('CHATBOT_CIRCUIT_BREAKER_ENABLED', true),
        'threshold' => env('CHATBOT_CIRCUIT_BREAKER_THRESHOLD', 5),
        'reset_timeout' => env('CHATBOT_CIRCUIT_BREAKER_RESET_TIMEOUT', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Conditional Reflection
    |--------------------------------------------------------------------------
    */
    'reflection' => [
        'enabled' => env('CHATBOT_REFLECTION_ENABLED', true),
        'max_retries' => env('CHATBOT_REFLECTION_MAX_RETRIES', 3),
        'confidence_threshold' => env('CHATBOT_REFLECTION_CONFIDENCE_THRESHOLD', 0.7),
        'critique_model' => env('CHATBOT_REFLECTION_CRITIQUE_MODEL', 'gpt-4.1-mini'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Streaming
    |--------------------------------------------------------------------------
    */
    'streaming' => [
        'enabled' => env('CHATBOT_STREAMING_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | RAG
    |--------------------------------------------------------------------------
    */
    'rag' => [
        'max_chars' => env('CHATBOT_RAG_MAX_CHARS', 3000),
    ],

    /*
    |--------------------------------------------------------------------------
    | BM25
    |--------------------------------------------------------------------------
    */
    'bm25' => [
        'corpus_cache_ttl' => env('CHATBOT_BM25_CACHE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Memory Management
    |--------------------------------------------------------------------------
    */
    'memory' => [
        'session_window' => env('CHATBOT_MEMORY_SESSION_WINDOW', 4),
        'summarization_enabled' => env('CHATBOT_MEMORY_SUMMARIZATION_ENABLED', true),
        'summarization_model' => env('CHATBOT_MEMORY_SUMMARIZATION_MODEL', 'gpt-4.1-nano'),
    ],
];
