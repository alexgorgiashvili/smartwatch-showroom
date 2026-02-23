<?php

namespace App\Services;

class WhatsAppService
{
    /**
     * Parse webhook payload from WhatsApp Cloud API
     *
     * @param array $payload
     * @return array|null Array with keys: sender_id, conversation_id, message_text, attachments, timestamp
     *                     Returns null if not a message event
     */
    public function parseWebhookPayload(array $payload): ?array
    {
        // WhatsApp Cloud API structure
        $entry = $payload['entry'][0] ?? null;
        if (!$entry) {
            return null;
        }

        $changes = $entry['changes'][0] ?? null;
        if (!$changes) {
            return null;
        }

        $value = $changes['value'] ?? [];
        $field = $changes['field'] ?? null;

        // Only process messages field
        if ($field !== 'messages') {
            return null;
        }

        // Check if there are statuses instead of messages
        if (isset($value['statuses']) && !isset($value['messages'])) {
            return null; // Status updates, not messages
        }

        // Extract message
        $messages = $value['messages'] ?? [];
        if (empty($messages)) {
            return null;
        }

        $message = $messages[0];
        $messageType = $message['type'] ?? 'text';

        // Extract sender information
        $senderId = $message['from'] ?? null;
        $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;

        if (!$senderId || !$phoneNumberId) {
            return null;
        }

        // Use phone number ID as conversation ID for WhatsApp
        $conversationId = $phoneNumberId;

        // Extract message content based on type
        $messageData = $this->extractMessageContent($message, $messageType);

        if ($messageData === null) {
            return null;
        }

        // Get timestamp
        $timestamp = $message['timestamp'] ?? now()->timestamp;

        return [
            'sender_id' => $senderId,
            'conversation_id' => $conversationId,
            'message_text' => $messageData['text'],
            'attachments' => $messageData['attachments'],
            'timestamp' => (int) $timestamp,
        ];
    }

    /**
     * Extract message content based on message type
     *
     * @param array $message
     * @param string $type
     * @return array|null
     */
    protected function extractMessageContent(array $message, string $type): ?array
    {
        $text = '';
        $attachments = [];

        switch ($type) {
            case 'text':
                $text = $message['text']['body'] ?? '';
                break;

            case 'image':
                $imageData = $message['image'] ?? [];
                $attachments[] = [
                    'type' => 'image',
                    'url' => $imageData['link'] ?? null,
                    'media_id' => $imageData['id'] ?? null,
                ];
                $text = $imageData['caption'] ?? '';
                break;

            case 'video':
                $videoData = $message['video'] ?? [];
                $attachments[] = [
                    'type' => 'video',
                    'url' => $videoData['link'] ?? null,
                    'media_id' => $videoData['id'] ?? null,
                ];
                $text = $videoData['caption'] ?? '';
                break;

            case 'audio':
                $audioData = $message['audio'] ?? [];
                $attachments[] = [
                    'type' => 'audio',
                    'url' => $audioData['link'] ?? null,
                    'media_id' => $audioData['id'] ?? null,
                ];
                break;

            case 'document':
                $documentData = $message['document'] ?? [];
                $attachments[] = [
                    'type' => 'file',
                    'url' => $documentData['link'] ?? null,
                    'media_id' => $documentData['id'] ?? null,
                    'filename' => $documentData['filename'] ?? null,
                ];
                $text = $documentData['caption'] ?? '';
                break;

            case 'location':
                $location = $message['location'] ?? [];
                $text = sprintf(
                    'Location: %.4f, %.4f',
                    $location['latitude'] ?? 0,
                    $location['longitude'] ?? 0
                );
                break;

            case 'contacts':
                $contacts = $message['contacts'] ?? [];
                $text = 'Contact: ' . ($contacts[0]['profile']['name'] ?? 'Unknown');
                break;

            default:
                return null;
        }

        return [
            'text' => $text,
            'attachments' => $attachments,
        ];
    }

    /**
     * Prepare message for sending via WhatsApp Cloud API
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
            'platform' => 'whatsapp',
            'payload' => $payload,
            'headers' => [
                'Authorization' => 'Bearer ' . config('services.whatsapp.access_token'),
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
     * Fetch user profile from WhatsApp
     *
     * @param string $userId
     * @return array Array with keys: name, avatar_url, email (typically null for WhatsApp)
     */
    public function fetchUserProfile(string $userId): array
    {
        // WhatsApp phone numbers are the user IDs
        // This is prepared for Phase 5 when HTTP calls are implemented

        $accessToken = config('services.whatsapp.access_token');

        return [
            'phone_number' => $userId,
            'access_token' => $accessToken,
            'email' => null,
        ];
    }
}
