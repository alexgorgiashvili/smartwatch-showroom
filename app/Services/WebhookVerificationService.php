<?php

namespace App\Services;

use App\Models\WebhookLog;
use Illuminate\Support\Facades\Log;

class WebhookVerificationService
{
    /**
     * Verify Meta webhook signature using HMAC-SHA256
     *
     * @param string $payload The raw request body
     * @param string $signature The X-Hub-Signature-256 header value (format: sha256=...)
     * @param string $appSecret The app secret from Meta/Facebook
     * @return bool
     */
    public function verifyMetaSignature(string $payload, string $signature, string $appSecret): bool
    {
        try {
            // Meta signature format is "sha256=<hash>"
            // Extract the hash from the signature header
            if (strpos($signature, 'sha256=') !== 0) {
                Log::warning('Invalid signature format', ['signature' => $signature]);
                return false;
            }

            $providedHash = substr($signature, 7); // Remove "sha256=" prefix

            // Calculate expected hash using HMAC-SHA256
            $expectedHash = hash_hmac('sha256', $payload, $appSecret);

            // Use hash_equals to prevent timing attacks
            $isValid = hash_equals($expectedHash, $providedHash);

            if (!$isValid) {
                Log::warning('Signature verification failed', [
                    'provided_hash' => $providedHash,
                    'expected_hash' => $expectedHash,
                ]);
            }

            return $isValid;
        } catch (\Exception $e) {
            Log::error('Error during signature verification', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Log webhook attempt to database
     *
     * @param string $platform The platform (facebook, instagram, whatsapp)
     * @param string $eventType The event type
     * @param array $payload The webhook payload
     * @param bool $verified Whether the signature was verified
     * @param string|null $error Any error message
     * @return WebhookLog
     */
    public function logWebhook(
        string $platform,
        string $eventType,
        array $payload,
        bool $verified = false,
        ?string $error = null
    ): WebhookLog {
        return WebhookLog::create([
            'platform' => $platform,
            'event_type' => $eventType,
            'payload' => $payload,
            'verified' => $verified,
            'processed' => false,
            'error' => $error,
        ]);
    }
}
