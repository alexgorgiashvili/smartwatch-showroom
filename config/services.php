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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'search_console_credentials' => env('GOOGLE_SEARCH_CONSOLE_CREDENTIALS'),
        'site_url' => env('GOOGLE_SEARCH_CONSOLE_SITE_URL', 'https://mytechnic.ge'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'org_id' => env('OPENAI_ORG_ID'),
        'model' => env('OPENAI_MODEL', 'gpt-4.1-mini'),
        'intent_model' => env('OPENAI_INTENT_MODEL', 'gpt-4.1-nano'),
        'intent_enabled' => env('INTENT_ANALYZER_ENABLED', true),
        'judge_model' => env('OPENAI_JUDGE_MODEL', 'gpt-4.1-mini'),
        'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'multi_agent_enabled' => env('CHATBOT_MULTI_AGENT_ENABLED', false),
        'multi_agent_rollout' => env('CHATBOT_MULTI_AGENT_ROLLOUT', 0),
        'multi_agent_context_model' => env('OPENAI_MULTI_AGENT_CONTEXT_MODEL', 'gpt-4o-mini'),
        'multi_agent_response_model' => env('OPENAI_MULTI_AGENT_RESPONSE_MODEL', 'gpt-4o-mini'),
        'multi_agent_qa_model' => env('OPENAI_MULTI_AGENT_QA_MODEL', 'gpt-4.1-nano'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'judge_model' => env('ANTHROPIC_JUDGE_MODEL', 'claude-sonnet-4-20250514'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
    ],

    'llm_judge_provider' => env('LLM_JUDGE_PROVIDER', 'openai'),

    'pinecone' => [
        'api_key' => env('PINECONE_API_KEY'),
        'host' => env('PINECONE_HOST'),
        'namespace' => env('PINECONE_NAMESPACE'),
        'index' => env('PINECONE_INDEX_NAME', 'products'),
    ],

    'cohere' => [
        'enabled' => env('COHERE_ENABLED', true),
        'api_key' => env('COHERE_API_KEY'),
        'model' => env('COHERE_RERANK_MODEL', 'rerank-english-v3.0'),
        'verify' => env('COHERE_SSL_VERIFY', true),
        'connect_timeout' => env('COHERE_CONNECT_TIMEOUT', 3),
        'timeout' => env('COHERE_TIMEOUT', 8),
        'circuit_cooldown_seconds' => env('COHERE_CIRCUIT_COOLDOWN_SECONDS', 900),
    ],

    'facebook' => [
        'app_id' => env('FACEBOOK_APP_ID'),
        'app_secret' => env('FACEBOOK_APP_SECRET'),
        'page_access_token' => env('FACEBOOK_MESSENGER_ACCESS_TOKEN', env('FACEBOOK_PAGE_ACCESS_TOKEN')),
        'messenger_access_token' => env('FACEBOOK_MESSENGER_ACCESS_TOKEN', env('FACEBOOK_PAGE_ACCESS_TOKEN')),
        'instagram_access_token' => env('INSTAGRAM_ACCESS_TOKEN'),
        'page_id' => env('FACEBOOK_PAGE_ID'),
        'instagram_account_id' => env('INSTAGRAM_BUSINESS_ACCOUNT_ID'),
        'webhook_verify_token' => env('FACEBOOK_WEBHOOK_VERIFY_TOKEN'),
    ],

    'meta' => [
        'app_secret' => env('META_APP_SECRET', env('FACEBOOK_APP_SECRET')),
        'verify_token' => env('FACEBOOK_WEBHOOK_VERIFY_TOKEN'),
    ],

    'whatsapp' => [
        'api_key' => env('WHATSAPP_API_KEY'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_id' => env('WHATSAPP_BUSINESS_ID'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],

    'webpush' => [
        'public_key' => env('WEBPUSH_VAPID_PUBLIC_KEY'),
        'private_key' => env('WEBPUSH_VAPID_PRIVATE_KEY'),
        'subject' => env('WEBPUSH_VAPID_SUBJECT', 'mailto:admin@localhost'),
    ],

    'apify' => [
        'token' => env('APIFY_API_TOKEN'),
        'actor_id' => env('APIFY_ACTOR_ID', 'apify/web-scraper'),
        'base_url' => env('APIFY_BASE_URL', 'https://api.apify.com/v2'),
        'timeout' => (int) env('APIFY_TIMEOUT', 180),
        'use_proxy' => filter_var(env('APIFY_USE_PROXY', true), FILTER_VALIDATE_BOOL),
        'retry_with_residential' => filter_var(env('APIFY_RETRY_WITH_RESIDENTIAL', true), FILTER_VALIDATE_BOOL),
        'proxy_country' => env('APIFY_PROXY_COUNTRY'),
        'respect_robots' => filter_var(env('APIFY_RESPECT_ROBOTS', false), FILTER_VALIDATE_BOOL),
        'use_template_page_function' => filter_var(env('APIFY_USE_TEMPLATE_PAGE_FUNCTION', false), FILTER_VALIDATE_BOOL),
        'input_template_json' => env('APIFY_INPUT_TEMPLATE_JSON'),
    ],

    'scrapingbee' => [
        'api_key' => env('SCRAPINGBEE_API_KEY'),
        'base_url' => env('SCRAPINGBEE_BASE_URL', 'https://app.scrapingbee.com/api/v1/'),
        'render_js' => filter_var(env('SCRAPINGBEE_RENDER_JS', true), FILTER_VALIDATE_BOOL),
        'timeout' => (int) env('SCRAPINGBEE_TIMEOUT', 60),
        'premium_proxy' => filter_var(env('SCRAPINGBEE_PREMIUM_PROXY', true), FILTER_VALIDATE_BOOL),
        'country_code' => env('SCRAPINGBEE_COUNTRY_CODE'),
    ],

];
