<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsOfficeService
{
    private string $apiKey;
    private string $sender;
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->apiKey = (string) config('services.smsoffice.api_key', '');
        $this->sender = (string) config('services.smsoffice.sender', '');
        $this->baseUrl = (string) config('services.smsoffice.base_url', '');
        $this->timeout = (int) config('services.smsoffice.timeout', 30);
    }

    /**
     * Send SMS using SMS Office API
     */
    public function sendSms(string $phoneNumber, string $message, bool $urgent = false): array
    {
        if (empty($this->apiKey)) {
            Log::error('SMS Office API key is not configured');
            return [
                'success' => false,
                'message' => 'SMS service not configured',
                'error_code' => 'config_missing'
            ];
        }

        // Validate and format phone number
        $formattedPhone = $this->formatPhoneNumber($phoneNumber);
        if (!$formattedPhone) {
            Log::error('Invalid phone number format', ['phone' => $phoneNumber]);
            return [
                'success' => false,
                'message' => 'Invalid phone number format',
                'error_code' => 'invalid_phone'
            ];
        }

        try {
            $params = [
                'key' => $this->apiKey,
                'destination' => $formattedPhone,
                'sender' => $this->sender,
                'content' => $message,
            ];

            if ($urgent) {
                $params['urgent'] = 'true';
            }

            Log::info('SMS Office API Request', [
                'url' => $this->baseUrl,
                'params' => [
                    'key' => substr($this->apiKey, 0, 10) . '...',
                    'destination' => $formattedPhone,
                    'sender' => $this->sender,
                    'content' => $message,
                    'urgent' => $urgent ? 'true' : 'false',
                ]
            ]);

            $response = Http::timeout($this->timeout)
                ->get($this->baseUrl, $params);

            Log::info('SMS Office API Response', [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body(),
                'json' => $response->json(),
            ]);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('SMS sent successfully', [
                    'phone' => $formattedPhone,
                    'message' => $message,
                    'response' => $data,
                    'success_field' => $data['Success'] ?? null,
                    'message_field' => $data['Message'] ?? null,
                    'output_field' => $data['Output'] ?? null,
                    'error_code_field' => $data['ErrorCode'] ?? null,
                ]);

                return [
                    'success' => true,
                    'message' => $data['Message'] ?? 'SMS sent successfully',
                    'data' => $data['Output'] ?? null,
                    'reference' => $data['Output']['reference'] ?? null,
                    'api_response' => $data,
                ];
            } else {
                Log::error('SMS sending failed - HTTP error', [
                    'phone' => $formattedPhone,
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'headers' => $response->headers(),
                ]);

                return [
                    'success' => false,
                    'message' => 'SMS sending failed: ' . $response->status(),
                    'error_code' => 'http_error',
                    'status' => $response->status(),
                ];
            }
        } catch (\Exception $e) {
            Log::error('SMS service exception', [
                'phone' => $formattedPhone,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'SMS service error: ' . $e->getMessage(),
                'error_code' => 'exception'
            ];
        }
    }

    /**
     * Format phone number to Georgian format (995XXXXXXX)
     */
    private function formatPhoneNumber(string $phone): string
    {
        // Remove all non-digit characters
        $digits = preg_replace('/\D/', '', $phone);

        // Check if it's a valid Georgian number
        if (strlen($digits) === 9 && str_starts_with($digits, '5')) {
            // Add country code
            return '995' . $digits;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '995')) {
            return $digits;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            // Replace leading 0 with 995
            return '995' . substr($digits, 1);
        }

        return '';
    }

    /**
     * Validate Georgian phone number
     */
    public function validatePhoneNumber(string $phone): bool
    {
        return !empty($this->formatPhoneNumber($phone));
    }
}
