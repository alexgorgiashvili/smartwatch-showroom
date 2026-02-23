<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'api/webhooks/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'localhost',
        '127.0.0.1',
        // Admin domain (update with your actual admin domain)
        // 'https://admin.example.com',
    ],

    'allowed_origins_patterns' => [
        // Allow Meta/Facebook webhooks
        '/^https:\/\/.*\.facebook\.com$/',
        '/^https:\/\/.*\.instagram\.com$/',
        // Allow WhatsApp Cloud API
        '/^https:\/\/.*\.whatsapp\.com$/',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
