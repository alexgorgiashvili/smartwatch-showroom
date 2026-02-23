<?php

return [
    'public_url' => rtrim(env('BOG_PUBLIC_URL', env('APP_URL', config('app.url'))), '/'),
    'client_id' => env('BOG_PAYMENT_CLIENT_ID'),
    'secret_key' => env('BOG_SECRET_KEY'),
    'language' => env('BOG_PAYMENT_LANGUAGE', 'ka'),
    'currency' => env('BOG_PAYMENT_CURRENCY', 'GEL'),
    'base_url' => env('BOG_PAYMENT_URL', 'https://api.bog.ge'),
    'oauth_url' => env('BOG_OAUTH_URL', 'https://oauth2.bog.ge/auth/realms/bog/protocol/openid-connect/token'),
    'callback_url' => rtrim(env('BOG_PUBLIC_URL', env('APP_URL', config('app.url'))), '/') . '/bog/payment/callback',
    'success_url' => rtrim(env('BOG_PUBLIC_URL', env('APP_URL', config('app.url'))), '/') . '/payment/success',
    'fail_url' => rtrim(env('BOG_PUBLIC_URL', env('APP_URL', config('app.url'))), '/') . '/payment/fail',
    'timeout' => (int) env('BOG_TIMEOUT', 20),
];
