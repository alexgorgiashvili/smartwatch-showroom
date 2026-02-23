<?php

namespace App\Services;

class MetaApiService
{
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

        // Use recipient ID (page ID) as conversation ID
        $conversationId = $recipientId;

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
        $value = $change['value'] ?? [];

        if ($value['field'] !== 'messages') {
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
    public function sendMessage(string $senderId, string $conversationId, string $message, ?string $mediaUrl = null): array
    {
        // Determine message type and structure
        $messageType = 'text';
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $senderId,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $message,
            ],
        ];

        // Add media if provided
        if ($mediaUrl) {
            $messageType = $this->detectMediaType($mediaUrl);
            $payload['type'] = $messageType;

            // Remove text field for media messages
            unset($payload['text']);

            // Add media payload based on type
            if ($messageType === 'image') {
                $payload['image'] = [
                    'link' => $mediaUrl,
                ];
            } elseif ($messageType === 'video') {
                $payload['video'] = [
                    'link' => $mediaUrl,
                ];
            } else {
                // Default to image for other types
                $payload['image'] = [
                    'link' => $mediaUrl,
                ];
            }
        }

        return [
            'platform' => 'facebook',
            'payload' => $payload,
            'headers' => [
                'Authorization' => 'Bearer ' . config('services.meta.page_access_token'),
                'Content-Type' => 'application/json',
            ],
        ];
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
