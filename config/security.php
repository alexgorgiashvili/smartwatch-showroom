<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for security features including webhooks, rate limiting,
    | input validation, and encryption settings.
    |
    */

    // Webhook signature verification settings
    'webhook_signature_timeout' => (int) env('WEBHOOK_SIGNATURE_TIMEOUT', 300), // 5 minutes in seconds
    'webhook_rate_limit' => (int) env('WEBHOOK_RATE_LIMIT', 100), // Max messages per minute per customer

    // Input validation settings
    'max_message_length' => (int) env('MAX_MESSAGE_LENGTH', 5000),

    // Rate limiting settings (requests per minute)
    'rate_limits' => [
        'webhook_reception' => (int) env('WEBHOOK_RECEPTION_RATE_LIMIT', 1000),
        'message_send' => (int) env('MESSAGE_SEND_RATE_LIMIT', 30),
        'ai_suggestion' => (int) env('AI_SUGGESTION_RATE_LIMIT', 10),
        'search' => (int) env('SEARCH_RATE_LIMIT', 60),
        'get_requests' => (int) env('GET_REQUESTS_RATE_LIMIT', 200),
        'post_requests' => (int) env('POST_REQUESTS_RATE_LIMIT', 100),
    ],

    // Message content encryption
    'encrypt_message_content' => (bool) env('ENCRYPT_MESSAGE_CONTENT', false),

    // Audit logging
    'log_webhook_payloads' => (bool) env('LOG_WEBHOOK_PAYLOADS', false),
    'log_sensitive_data' => (bool) env('LOG_SENSITIVE_DATA', false),

    // Data retention policies (in days)
    'message_retention_days' => (int) env('MESSAGE_RETENTION_DAYS', 90),
    'audit_log_retention_days' => (int) env('AUDIT_LOG_RETENTION_DAYS', 180),
    'webhook_log_retention_days' => (int) env('WEBHOOK_LOG_RETENTION_DAYS', 30),
];
