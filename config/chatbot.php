<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Supervisor Configuration
    |--------------------------------------------------------------------------
    */
    'supervisor' => [
        'enabled' => env('CHATBOT_SUPERVISOR_ENABLED', true),
        'model' => env('CHATBOT_SUPERVISOR_MODEL', 'gpt-4o-mini'),
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
        'critique_model' => env('CHATBOT_REFLECTION_CRITIQUE_MODEL', 'gpt-4o-mini'),
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
    | Memory Management
    |--------------------------------------------------------------------------
    */
    'memory' => [
        'session_window' => env('CHATBOT_MEMORY_SESSION_WINDOW', 4),
        'summarization_enabled' => env('CHATBOT_MEMORY_SUMMARIZATION_ENABLED', true),
    ],
];
