<?php

return [
    'thresholds' => [
        'slow_response_ms' => 8000,
        'fallback_alert_rate' => 20.0,
        'slow_response_alert_rate' => 15.0,
        'provider_fallback_alert_rate' => 10.0,
        'provider_incident_alert_rate' => 5.0,
        'validator_fallback_alert_rate' => 10.0,
        'regeneration_attempt_alert_rate' => 20.0,
        'regeneration_success_min_rate' => 50.0,
    ],

    'widget_trace' => [
        'enabled' => env('CHATBOT_WIDGET_TRACE_ENABLED', false),
        'channel' => env('CHATBOT_WIDGET_TRACE_CHANNEL', 'chatbot_widget_trace'),
        'include_payloads' => env('CHATBOT_WIDGET_TRACE_INCLUDE_PAYLOADS', true),
        'max_chars' => env('CHATBOT_WIDGET_TRACE_MAX_CHARS', 800),
        'max_items' => env('CHATBOT_WIDGET_TRACE_MAX_ITEMS', 8),
    ],
];
