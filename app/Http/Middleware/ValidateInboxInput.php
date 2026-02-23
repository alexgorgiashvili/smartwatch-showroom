<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ValidateInboxInput
{
    /**
     * Handle incoming request
     * Validates all inbox endpoint inputs
     */
    public function handle(Request $request, Closure $next)
    {
        // Only validate POST, PATCH, PUT requests
        if (!in_array($request->method(), ['POST', 'PATCH', 'PUT'])) {
            return $next($request);
        }

        // Validate based on endpoint
        $path = $request->path();

        // Message content validation
        if (str_contains($path, 'messages')) {
            $this->validateMessageContent($request);
        }

        // Conversation validation
        if (str_contains($path, 'conversation') || str_contains($path, 'inbox')) {
            $this->validateConversation($request);
        }

        // Platform and sender validation
        if ($request->has('platform') || $request->has('sender_id')) {
            $this->validatePlatformAndSender($request);
        }

        return $next($request);
    }

    /**
     * Validate message content
     */
    protected function validateMessageContent(Request $request): void
    {
        if ($request->has('content')) {
            $content = $request->input('content', '');
            $maxLength = config('security.max_message_length', 5000);

            // Check if content exists and is not just whitespace
            if (empty(trim($content))) {
                Log::warning('Invalid message content - empty or whitespace only', [
                    'path' => $request->path(),
                    'user_id' => auth()->id(),
                ]);
                abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Message content cannot be empty or whitespace only');
            }

            // Check content length
            if (strlen($content) > $maxLength) {
                Log::warning('Message content exceeds maximum length', [
                    'path' => $request->path(),
                    'user_id' => auth()->id(),
                    'content_length' => strlen($content),
                    'max_length' => $maxLength,
                ]);
                abort(Response::HTTP_UNPROCESSABLE_ENTITY, "Message content cannot exceed $maxLength characters");
            }
        }

        // Validate media URLs if provided
        if ($request->has('media_url')) {
            $mediaUrl = $request->input('media_url');
            if ($mediaUrl && !$this->isValidUrl($mediaUrl)) {
                Log::warning('Invalid media URL provided', [
                    'path' => $request->path(),
                    'user_id' => auth()->id(),
                    'media_url' => $mediaUrl,
                ]);
                abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Media URL must be a valid HTTP/HTTPS URL');
            }
        }
    }

    /**
     * Validate conversation exists and belongs to authenticated admin
     */
    protected function validateConversation(Request $request): void
    {
        $conversationId = $request->route('conversation');

        if (!$conversationId) {
            return;
        }

        // Verify conversation exists (controller will handle authorization)
        // This is a basic check - detailed authorization is in controller
        if (!is_numeric($conversationId) && !$this->isValidUuid($conversationId)) {
            Log::warning('Invalid conversation ID format', [
                'path' => $request->path(),
                'user_id' => auth()->id(),
                'conversation_id' => $conversationId,
            ]);
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Conversation ID must be numeric or valid UUID');
        }
    }

    /**
     * Validate platform and sender_id
     */
    protected function validatePlatformAndSender(Request $request): void
    {
        $platform = $request->input('platform');
        $senderId = $request->input('sender_id');

        if ($platform) {
            $allowedPlatforms = ['facebook', 'instagram', 'whatsapp'];

            if (!in_array($platform, $allowedPlatforms)) {
                Log::warning('Invalid platform provided', [
                    'path' => $request->path(),
                    'user_id' => auth()->id(),
                    'platform' => $platform,
                ]);
                abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Platform must be one of: ' . implode(', ', $allowedPlatforms));
            }

            // Validate sender_id format based on platform
            if ($senderId) {
                $this->validateSenderId($senderId, $platform, $request);
            }
        }
    }

    /**
     * Validate sender ID format based on platform
     */
    protected function validateSenderId(string $senderId, string $platform, Request $request): void
    {
        $isValid = match ($platform) {
            'facebook', 'instagram' => $this->isValidMetaId($senderId),
            'whatsapp' => $this->isValidPhoneNumber($senderId),
            default => true,
        };

        if (!$isValid) {
            Log::warning('Invalid sender ID format for platform', [
                'path' => $request->path(),
                'user_id' => auth()->id(),
                'platform' => $platform,
                'sender_id' => $senderId,
            ]);

            $instruction = match ($platform) {
                'facebook', 'instagram' => 'Facebook/Instagram IDs must be numeric (15-20 digits)',
                'whatsapp' => 'WhatsApp IDs must be E.164 format phone numbers (+1234567890)',
                default => 'Invalid sender ID format',
            };

            abort(Response::HTTP_UNPROCESSABLE_ENTITY, $instruction);
        }
    }

    /**
     * Check if string is valid URL (http/https only)
     */
    protected function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false &&
               preg_match('/^https?:\/\//i', $url);
    }

    /**
     * Check if string is valid UUID (v4)
     */
    protected function isValidUuid(string $uuid): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid
        ) === 1;
    }

    /**
     * Check if sender ID is valid Meta format (numeric, 15-20 digits)
     */
    protected function isValidMetaId(string $senderId): bool
    {
        return preg_match('/^\d{15,20}$/', $senderId) === 1;
    }

    /**
     * Check if phone number is E.164 format for WhatsApp
     */
    protected function isValidPhoneNumber(string $phone): bool
    {
        // E.164 format: + followed by 1-15 digits
        return preg_match('/^\+[1-9]\d{1,14}$/', $phone) === 1;
    }
}
