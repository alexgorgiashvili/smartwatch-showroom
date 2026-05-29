<?php

namespace App\Console\Commands;

use App\Services\SmsOfficeService;
use Illuminate\Console\Command;

class TestSmsService extends Command
{
    protected $signature = 'sms:test {phone?}';
    protected $description = 'Test SMS Office service';

    public function handle(SmsOfficeService $smsService): int
    {
        $this->info('Testing SMS Office service...');

        // Test phone number formatting
        $testPhones = [
            '995599123456',  // Correct format
            '599123456',     // Missing 995
            '0599123456',    // With 0 prefix
            '123456789',     // Invalid
            '99559912345678', // Too long
        ];

        $this->info('Testing phone number validation:');
        foreach ($testPhones as $phone) {
            $isValid = $smsService->validatePhoneNumber($phone);
            $this->line("Phone: $phone - Valid: " . ($isValid ? 'Yes' : 'No'));
        }

        // Check API configuration
        $apiKey = config('services.smsoffice.api_key');
        if (empty($apiKey)) {
            $this->error('SMS Office API key not configured. Please set SMSOFFICE_API_KEY in .env file.');
            return 1;
        }

        $this->info('API key is configured.');

        // Test actual SMS sending if phone number provided
        $phone = $this->argument('phone');
        if ($phone) {
            $this->info("Testing SMS sending to: $phone");
            
            $result = $smsService->sendSms($phone, 'ტესტი SMS შეტყობინება');
            
            if ($result['success']) {
                $this->info('SMS sent successfully!');
                $this->line('Reference: ' . ($result['reference'] ?? 'N/A'));
            } else {
                $this->error('SMS sending failed: ' . $result['message']);
            }
        } else {
            $this->info('To test actual SMS sending, run: php artisan sms:test 995599123456');
        }

        $this->info('SMS Office service test completed.');
        return 0;
    }
}
