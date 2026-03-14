<?php

namespace App\Services;

use App\Services\Chatbot\CarouselBuilderService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaApiService
{
    protected function resolveAccessToken(?string $platform = null): string
    {
        $platform = $platform ?: 'facebook';

        if ($platform === 'instagram') {
            $instagramAccessToken = (string) config('services.facebook.instagram_access_token', '');

            if ($instagramAccessToken !== '') {
                return $instagramAccessToken;
            }
        }

        return (string) config('services.facebook.page_access_token', '');
    }

    /**
     * @param array<int, array<string, string>> $products
     */
    public function sendCarousel(string $senderId, array $products, ?string $platform = null): array
    {
        $platform = $platform ?: 'facebook';

        $accessToken = $this->resolveAccessToken($platform);

        if ($accessToken === '' || count($products) < 2) {
            return [
                'success' => false,
                'error' => 'meta_carousel_config_or_products_missing',
            ];
        }

        $payload = [
            'recipient' => ['id' => $senderId],
            'messaging_type' => 'RESPONSE',
            'message' => [
                'attachment' => app(CarouselBuilderService::class)->buildMetaGenericCarousel($products)['attachment'],
            ],
        ];

        try {
            $endpoint = 'https://graph.facebook.com/v18.0/me/messages';
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(20)
                ->post($endpoint, $payload);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error' => 'meta_carousel_send_failed',
                    'status' => $response->status(),
                    'payload' => $payload,
                ];
            }

            return [
                'success' => true,
                'status' => $response->status(),
                'response' => $response->json(),
                'payload' => $payload,
            ];
        } catch (\Throwable $exception) {
            Log::error('Meta send carousel exception', [
                'platform' => $platform,
                'error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'meta_carousel_send_exception',
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Parse webhook payload from Meta (Facebook Messenger or Instagram DM)
     *
     * @param array $payload
     * @return array|null Array with keys: sender_id, conversation_id, message_text, attachments, timestamp
     *                     Returns null if not a message event
     */
    public function parseWebhookPayload(array $payload): ?array
    {
        // Extract message from payload
        $entry = $payload['entry'][0] ?? null;
        if (!$entry) {
            return null;
        }

        // Handle Facebook Messenger format
        if (isset($entry['messaging'])) {
            return $this->parseMessagingEvent($entry['messaging'][0] ?? []);
        }

        // Handle Instagram Direct format
        if (isset($entry['changes'])) {
            return $this->parseInstagramDirect($entry['changes'][0] ?? []);
        }

        return null;
    }

    /**
     * Parse Facebook Messenger messaging event
     *
     * @param array $messaging
     * @return array|null
     */
    protected function parseMessagingEvent(array $messaging): ?array
    {
        // Only process message events, not delivery/read receipts
        if (!isset($messaging['message'])) {
            return null;
        }

        $message = $messaging['message'];

        // Ignore message echoes (our own messages)
        if (isset($message['is_echo']) && $message['is_echo']) {
            return null;
        }

        $senderId = $messaging['sender']['id'] ?? null;
        $recipientId = $messaging['recipient']['id'] ?? null;

        if (!$senderId || !$recipientId) {
            return null;
        }

        // Messenger threads are scoped to the sender PSID for page inbox use cases.
        $conversationId = $senderId;

        // Extract message text
        $messageText = $message['text'] ?? '';

        // Extract attachments
        $attachments = $this->extractAttachments($message['attachments'] ?? []);

        // Get timestamp
        $timestamp = $messaging['timestamp'] ?? now()->timestamp;

        return [
            'sender_id' => $senderId,
            'conversation_id' => $conversationId,
            'message_text' => $messageText,
            'attachments' => $attachments,
            'timestamp' => $timestamp,
            'platform_message_id' => $message['mid'] ?? null,
        ];
    }

    /**
     * Parse Instagram Direct message event
     *
     * @param array $change
     * @return array|null
     */
    protected function parseInstagramDirect(array $change): ?array
    {
        // Instagram DM format differs from Facebook Messenger
        $field = $change['field'] ?? null;
        $value = $change['value'] ?? [];

        if ($field !== 'messages') {
            return null;
        }

        $messaging = $value['data']['messaging'][0] ?? null;
        if (!$messaging) {
            return null;
        }

        // Check if it's a message and not a read receipt
        if (!isset($messaging['message'])) {
            return null;
        }

        $senderId = $messaging['sender']['id'] ?? null;
        $conversationId = $messaging['conversation']['id'] ?? null;

        if (!$senderId || !$conversationId) {
            return null;
        }

        $message = $messaging['message'];

        // Extract message text
        $messageText = $message['text'] ?? '';

        // Extract attachments
        $attachments = $this->extractAttachments($message['attachments'] ?? []);

        // Get timestamp
        $timestamp = $messaging['timestamp'] ?? now()->timestamp;

        return [
            'sender_id' => $senderId,
            'conversation_id' => $conversationId,
            'message_text' => $messageText,
            'attachments' => $attachments,
            'timestamp' => $timestamp,
            'platform_message_id' => $message['mid'] ?? null,
        ];
    }

    /**
     * Extract attachments from message
     *
     * @param array $attachments
     * @return array
     */
    protected function extractAttachments(array $attachments): array
    {
        $extracted = [];

        foreach ($attachments as $attachment) {
            $type = $attachment['type'] ?? 'unknown';
            $payload = $attachment['payload'] ?? [];

            $media = [
                'type' => $type,
                'url' => $payload['url'] ?? null,
            ];

            // Add additional type-specific data
            if ($type === 'image' || $type === 'video') {
                $media['preview_url'] = $payload['preview_url'] ?? null;
            }

            if ($type === 'file') {
                $media['name'] = $payload['name'] ?? null;
                $media['size'] = $payload['size'] ?? null;
            }

            $extracted[] = $media;
        }

        return $extracted;
    }

    /**
     * Prepare message for sending via Meta API
     *
     * @param string $senderId
     * @param string $conversationId
     * @param string $message
     * @param string|null $mediaUrl
     * @return array Formatted array with 'platform', 'payload', 'headers'
     */
    public function sendMessage(string $senderId, string $conversationId, string $message, ?string $mediaUrl = null, ?string $platform = null): array
    {
        $platform = $platform ?: 'facebook';

        $accessToken = $this->resolveAccessToken($platform);

        if ($accessToken === '') {
            return [
                'success' => false,
                'error' => 'meta_config_missing',
            ];
        }

        $payload = [
            'recipient' => ['id' => $senderId],
            'messaging_type' => 'RESPONSE',
            'message' => ['text' => $message],
        ];

        // Add media if provided
        if ($mediaUrl) {
            $messageType = $this->detectMediaType($mediaUrl);
            $attachmentType = $messageType === 'video' ? 'video' : 'image';
            $payload['message'] = [
                'attachment' => [
                    'type' => $attachmentType,
                    'payload' => ['url' => $mediaUrl, 'is_reusable' => false],
                ],
            ];
        }

        try {
            $endpoint = 'https://graph.facebook.com/v18.0/me/messages';
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(20)
                ->post($endpoint, $payload);

            if (!$response->successful()) {
                Log::warning('Meta send message failed', [
                    'platform' => $platform,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error' => 'meta_send_failed',
                    'status' => $response->status(),
                    'payload' => $payload,
                ];
            }

            return [
                'success' => true,
                'status' => $response->status(),
                'response' => $response->json(),
                'payload' => $payload,
            ];
        } catch (\Throwable $exception) {
            Log::error('Meta send message exception', [
                'platform' => $platform,
                'error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'meta_send_exception',
                'message' => $exception->getMessage(),
                'payload' => $payload,
            ];
        }
    }

    /**
     * Detect media type from URL
     *
     * @param string $url
     * @return string
     */
    protected function detectMediaType(string $url): string
    {
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        // Image extensions
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            return 'image';
        }

        // Video extensions
        if (in_array($extension, ['mp4', 'avi', 'mov', 'mkv', 'webm'])) {
            return 'video';
        }

        // Default to image
        return 'image';
    }

    /**
     * Fetch user profile from Meta API
     *
     * @param string $userId
     * @return array Array with keys: name, avatar_url, email (if available)
     */
    public function fetchUserProfile(string $userId): array
    {
        // This is prepared for Phase 5 when HTTP calls are implemented
        // For now, return structure that would be populated with actual API call

        $accessToken = config('services.meta.page_access_token');

        return [
            'api_endpoint' => "https://graph.facebook.com/v18.0/{$userId}",
            'fields' => ['first_name', 'last_name', 'profile_pic', 'email'],
            'access_token' => $accessToken,
            'user_id' => $userId,
        ];
    }
}
