<?php

namespace App\Http\Middleware;

use App\Services\WebhookVerificationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class VerifyWebhookSignature
{
    protected WebhookVerificationService $verificationService;

    public function __construct(WebhookVerificationService $verificationService)
    {
        $this->verificationService = $verificationService;
    }

    /**
     * Handle incoming request
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip verification for GET requests (Meta webhook verification challenge)
        if ($request->isMethod('get')) {
            return $next($request);
        }

        // Get the signature header
        $signature = $request->header('X-Hub-Signature-256');

        // Get raw request body
        $payload = $request->getContent();

        // Determine platform from request path
        $platform = $this->determinePlatform($request);
        $eventType = $request->input('object', 'unknown');

        if (!$signature) {
            Log::warning('Missing X-Hub-Signature-256 header for webhook', [
                'path' => $request->path(),
                'platform' => $platform,
            ]);

            // Log the unverified webhook
            $this->verificationService->logWebhook(
                $platform,
                $eventType,
                $request->all(),
                false,
                'Missing signature header'
            );

            return response('Unauthorized', Response::HTTP_FORBIDDEN);
        }

        // Get app secret from config
        $appSecret = $this->getAppSecret($platform);

        if (!$appSecret) {
            Log::error('Missing app secret configuration for platform', ['platform' => $platform]);

            // Log the webhook with error
            $this->verificationService->logWebhook(
                $platform,
                $eventType,
                $request->all(),
                false,
                'Missing app secret configuration'
            );

            return response('Server Error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Verify the signature
        $isVerified = $this->verificationService->verifyMetaSignature($payload, $signature, $appSecret);

        if (!$isVerified) {
            Log::warning('Webhook signature verification failed', [
                'path' => $request->path(),
                'platform' => $platform,
                'signature' => $signature,
            ]);

            // Log the unverified webhook
            $this->verificationService->logWebhook(
                $platform,
                $eventType,
                $request->all(),
                false,
                'Signature verification failed'
            );

            return response('Unauthorized', Response::HTTP_FORBIDDEN);
        }

        // Verify timestamp to prevent replay attacks (5 minute window)
        $timestamp = $request->input('timestamp');
        if (!$this->verifyTimestamp($timestamp)) {
            Log::warning('Webhook timestamp verification failed - possible replay attack', [
                'path' => $request->path(),
                'platform' => $platform,
                'timestamp' => $timestamp,
            ]);

            $this->verificationService->logWebhook(
                $platform,
                $eventType,
                $request->all(),
                false,
                'Timestamp verification failed - possible replay attack'
            );

            return response('Unauthorized', Response::HTTP_FORBIDDEN);
        }

        // Check rate limit by sender_id (max 100 messages per minute per customer)
        if (!$this->checkRateLimit($request, $platform)) {
            Log::warning('Webhook rate limit exceeded for sender', [
                'path' => $request->path(),
                'platform' => $platform,
            ]);

            $this->verificationService->logWebhook(
                $platform,
                $eventType,
                $request->all(),
                false,
                'Rate limit exceeded for sender'
            );

            return response('Too Many Requests', Response::HTTP_TOO_MANY_REQUESTS);
        }

        // Store verification result in request for controller
        $request->attributes->set('webhook_verified', true);
        $request->attributes->set('webhook_platform', $platform);

        return $next($request);
    }

    /**
     * Verify webhook timestamp to prevent replay attacks
     * Requires timestamp within 5 minutes (300 seconds)
     */
    protected function verifyTimestamp(?int $timestamp): bool
    {
        if (!$timestamp) {
            // Timestamp is required
            return false;
        }

        $timeoutSeconds = config('security.webhook_signature_timeout', 300);
        $currentTime = time();

        // Check if timestamp is within acceptable window
        if (abs($currentTime - $timestamp) > $timeoutSeconds) {
            Log::warning('Webhook timestamp outside acceptable window', [
                'timestamp' => $timestamp,
                'current_time' => $currentTime,
                'diff_seconds' => abs($currentTime - $timestamp),
                'max_seconds' => $timeoutSeconds,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Check rate limit for webhook sender
     * Maximum 100 messages per minute per customer/sender_id
     */
    protected function checkRateLimit(Request $request, string $platform): bool
    {
        // Extract sender_id from webhook payload
        $entries = $request->input('entry', []);
        $senderId = null;

        foreach ($entries as $entry) {
            if (isset($entry['messaging']) && is_array($entry['messaging'])) {
                foreach ($entry['messaging'] as $message) {
                    if (isset($message['sender']['id'])) {
                        $senderId = $message['sender']['id'];
                        break 2;
                    }
                }
            }
        }

        if (!$senderId) {
            // Can't rate limit without sender ID, but allow through
            return true;
        }

        $cacheKey = "webhook_rate_limit:{$platform}:{$senderId}";
        $maxRequests = config('security.webhook_rate_limit', 100);
        $windowSeconds = 60;

        // Get current count from cache
        $currentCount = Cache::get($cacheKey, 0);

        if ($currentCount >= $maxRequests) {
            Log::warning('Rate limit exceeded for webhook sender', [
                'platform' => $platform,
                'sender_id' => $senderId,
                'current_count' => $currentCount,
                'max_requests' => $maxRequests,
            ]);
            return false;
        }

        // Increment counter and set expiration
        Cache::put($cacheKey, $currentCount + 1, $windowSeconds);

        return true;
    }

    /**
     * Determine platform from request path
     */
    protected function determinePlatform(Request $request): string
    {
        // Default to facebook; can be extended based on request data
        return 'facebook';
    }

    /**
     * Get app secret for platform
     */
    protected function getAppSecret(string $platform): ?string
    {
        return config("services.meta.app_secret");
    }
}
