<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for AI services including OpenAI and Pinecone
    |
    */

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'org_id' => env('OPENAI_ORG_ID'),
        'model' => env('OPENAI_MODEL', 'gpt-4-turbo'),
        'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
    ],

    'pinecone' => [
        'api_key' => env('PINECONE_API_KEY'),
        'host' => env('PINECONE_HOST'),
        'namespace' => env('PINECONE_NAMESPACE', 'mytechnic'),
        'index' => env('PINECONE_INDEX_NAME', 'products'),
    ],
];
