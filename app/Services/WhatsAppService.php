<?php

namespace App\Services;

use App\Services\Chatbot\CarouselBuilderService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WhatsAppService
{
    /**
     * @param array<int, array<string, string>> $products
     */
    public function sendCarousel(string $senderId, string $conversationId, array $products): array
    {
        $accessToken = (string) config('services.whatsapp.access_token');
        $phoneNumberId = (string) config('services.whatsapp.phone_number_id');
        $catalogId = (string) config('services.whatsapp.business_id');

        if ($accessToken === '' || $phoneNumberId === '' || $catalogId === '' || count($products) < 2) {
            return [
                'success' => false,
                'error' => 'whatsapp_carousel_config_or_products_missing',
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $senderId,
        ] + app(CarouselBuilderService::class)->buildWhatsAppCarousel($products);

        try {
            $endpoint = 'https://graph.facebook.com/v18.0/' . $phoneNumberId . '/messages';
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(20)
                ->post($endpoint, $payload);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error' => 'whatsapp_carousel_send_failed',
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
            Log::error('WhatsApp send carousel exception', [
                'error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'whatsapp_carousel_send_exception',
                'message' => $exception->getMessage(),
            ];
        }
    }

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
            'platform_message_id' => $message['id'] ?? null,
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
                $mediaId = $imageData['id'] ?? null;
                $attachments[] = [
                    'type' => 'image',
                    'url' => $imageData['link'] ?? ($mediaId ? $this->downloadMedia($mediaId) : null),
                    'media_id' => $mediaId,
                ];
                $text = $imageData['caption'] ?? '';
                break;

            case 'video':
                $videoData = $message['video'] ?? [];
                $mediaId = $videoData['id'] ?? null;
                $attachments[] = [
                    'type' => 'video',
                    'url' => $videoData['link'] ?? ($mediaId ? $this->downloadMedia($mediaId) : null),
                    'media_id' => $mediaId,
                ];
                $text = $videoData['caption'] ?? '';
                break;

            case 'audio':
                $audioData = $message['audio'] ?? [];
                $mediaId = $audioData['id'] ?? null;
                $attachments[] = [
                    'type' => 'audio',
                    'url' => $audioData['link'] ?? ($mediaId ? $this->downloadMedia($mediaId) : null),
                    'media_id' => $mediaId,
                ];
                break;

            case 'document':
                $documentData = $message['document'] ?? [];
                $mediaId = $documentData['id'] ?? null;
                $attachments[] = [
                    'type' => 'file',
                    'url' => $documentData['link'] ?? ($mediaId ? $this->downloadMedia($mediaId) : null),
                    'media_id' => $mediaId,
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
        $accessToken = (string) config('services.whatsapp.access_token');
        $phoneNumberId = (string) config('services.whatsapp.phone_number_id');

        if ($accessToken === '' || $phoneNumberId === '') {
            Log::warning('WhatsApp sendMessage missing config', [
                'has_access_token' => $accessToken !== '',
                'has_phone_number_id' => $phoneNumberId !== '',
            ]);

            return [
                'success' => false,
                'error' => 'whatsapp_config_missing',
            ];
        }

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

        try {
            $endpoint = 'https://graph.facebook.com/v18.0/' . $phoneNumberId . '/messages';

            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(20)
                ->post($endpoint, $payload);

            if (!$response->successful()) {
                Log::warning('WhatsApp send message failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error' => 'whatsapp_send_failed',
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
            Log::error('WhatsApp send message exception', [
                'error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'whatsapp_send_exception',
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

    private function downloadMedia(string $mediaId): ?string
    {
        $accessToken = (string) config('services.whatsapp.access_token');

        if ($accessToken === '') {
            return null;
        }

        try {
            $metadataResponse = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(20)
                ->get('https://graph.facebook.com/v18.0/' . $mediaId);

            if (!$metadataResponse->successful()) {
                return null;
            }

            $downloadUrl = (string) data_get($metadataResponse->json(), 'url', '');
            $mimeType = (string) data_get($metadataResponse->json(), 'mime_type', 'application/octet-stream');

            if ($downloadUrl === '') {
                return null;
            }

            $binaryResponse = Http::withToken($accessToken)
                ->timeout(30)
                ->get($downloadUrl);

            if (!$binaryResponse->successful()) {
                return null;
            }

            $extension = $this->extensionFromMime($mimeType);
            $relativePath = 'whatsapp-media/' . date('Y/m') . '/' . $mediaId . '-' . Str::random(6) . '.' . $extension;

            Storage::disk('public')->put($relativePath, $binaryResponse->body());

            return url('/storage/' . $relativePath);
        } catch (\Throwable $exception) {
            Log::warning('WhatsApp media download failed', [
                'media_id' => $mediaId,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function extensionFromMime(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'audio/mpeg' => 'mp3',
            'audio/ogg' => 'ogg',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }
}
