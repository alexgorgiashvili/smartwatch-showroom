<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\SmsOfficeService;

// Test SMS Office service
$smsService = new SmsOfficeService();

// Test phone number formatting
$testPhones = [
    '995599123456',  // Correct format
    '599123456',     // Missing 995
    '0599123456',    // With 0 prefix
    '123456789',     // Invalid
    '99559912345678', // Too long
];

echo "Testing phone number formatting:\n";
foreach ($testPhones as $phone) {
    $isValid = $smsService->validatePhoneNumber($phone);
    echo "Phone: $phone - Valid: " . ($isValid ? 'Yes' : 'No') . "\n";
}

// Test SMS sending (only if API key is configured)
$apiKey = config('services.smsoffice.api_key');
if (empty($apiKey)) {
    echo "\nSMS Office API key not configured. Please set SMSOFFICE_API_KEY in .env file.\n";
} else {
    echo "\nAPI key found. You can test SMS sending by uncommenting the test code below.\n";
    /*
    $result = $smsService->sendSms('995599123456', 'ტესტი SMS შეტყობინება');
    echo "SMS Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
    */
}

echo "\nSMS Office service test completed.\n";
