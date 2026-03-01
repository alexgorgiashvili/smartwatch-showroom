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

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4.1-mini'),
        'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    ],

    'pinecone' => [
        'api_key' => env('PINECONE_API_KEY'),
        'host' => env('PINECONE_HOST'),
        'namespace' => env('PINECONE_NAMESPACE'),
    ],

    'facebook' => [
        'app_id' => env('FACEBOOK_APP_ID'),
        'app_secret' => env('FACEBOOK_APP_SECRET'),
        'page_access_token' => env('FACEBOOK_PAGE_ACCESS_TOKEN'),
        'page_id' => env('FACEBOOK_PAGE_ID'),
        'webhook_verify_token' => env('FACEBOOK_WEBHOOK_VERIFY_TOKEN'),
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
