<?php

namespace App\Services\Platform;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramApiService
{
    protected string $accessToken;
    protected string $apiVersion = 'v18.0';

    public function __construct()
    {
        $this->accessToken = config('services.facebook.instagram_access_token', '');
    }

    public function parseWebhook(array $payload): ?array
    {
        $entry = $payload['entry'][0] ?? null;
        if (!$entry) {
            return null;
        }

        $changes = $entry['changes'][0] ?? null;
        if (!$changes) {
            return null;
        }

        $field = $changes['field'] ?? null;
        $value = $changes['value'] ?? [];

        if ($field !== 'messages') {
            return null;
        }

        $messaging = $value['data']['messaging'][0] ?? null;
        if (!$messaging || !isset($messaging['message'])) {
            return null;
        }

        $message = $messaging['message'];
        $senderId = $messaging['sender']['id'] ?? null;
        $conversationId = $messaging['conversation']['id'] ?? null;

        if (!$senderId || !$conversationId) {
            return null;
        }

        return [
            'sender_id' => $senderId,
            'conversation_id' => $conversationId,
            'message_text' => $message['text'] ?? '',
            'attachments' => $this->extractAttachments($message['attachments'] ?? []),
            'timestamp' => $messaging['timestamp'] ?? now()->timestamp,
            'platform_message_id' => $message['mid'] ?? null,
        ];
    }

    public function sendMessage(string $recipientId, string $message, ?string $mediaUrl = null): array
    {
        if (!$this->accessToken) {
            return [
                'success' => false,
                'error' => 'instagram_access_token_missing',
            ];
        }

        $payload = [
            'recipient' => ['id' => $recipientId],
            'message' => [],
        ];

        if ($mediaUrl) {
            $payload['message']['attachment'] = [
                'type' => $this->detectMediaType($mediaUrl),
                'payload' => ['url' => $mediaUrl],
            ];

            if ($message) {
                $payload['message']['text'] = $message;
            }
        } else {
            $payload['message']['text'] = $message;
        }

        try {
            $endpoint = "https://graph.facebook.com/{$this->apiVersion}/me/messages";

            $response = Http::withToken($this->accessToken)
                ->acceptJson()
                ->timeout(20)
                ->post($endpoint, $payload);

            if (!$response->successful()) {
                Log::warning('Instagram send message failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error' => 'instagram_send_failed',
                    'status' => $response->status(),
                    'response' => $response->json(),
                ];
            }

            return [
                'success' => true,
                'status' => $response->status(),
                'response' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('Instagram send message exception', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'instagram_send_exception',
                'message' => $e->getMessage(),
            ];
        }
    }

    public function sendTypingIndicator(string $recipientId, bool $isTyping): void
    {
        if (!$this->accessToken) {
            return;
        }

        try {
            $endpoint = "https://graph.facebook.com/{$this->apiVersion}/me/messages";

            Http::withToken($this->accessToken)
                ->acceptJson()
                ->timeout(10)
                ->post($endpoint, [
                    'recipient' => ['id' => $recipientId],
                    'sender_action' => $isTyping ? 'typing_on' : 'typing_off',
                ]);
        } catch (\Exception $e) {
            Log::debug('Instagram typing indicator failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function fetchUserProfile(string $userId): array
    {
        if (!$this->accessToken) {
            return [];
        }

        try {
            $endpoint = "https://graph.facebook.com/{$this->apiVersion}/{$userId}";

            $response = Http::withToken($this->accessToken)
                ->acceptJson()
                ->timeout(10)
                ->get($endpoint, [
                    'fields' => 'name,profile_pic',
                ]);

            if (!$response->successful()) {
                return [];
            }

            $data = $response->json();

            return [
                'name' => $data['name'] ?? null,
                'avatar_url' => $data['profile_pic'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::warning('Instagram fetch user profile failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    protected function extractAttachments(array $attachments): array
    {
        $result = [];

        foreach ($attachments as $attachment) {
            $type = $attachment['type'] ?? 'file';
            $payload = $attachment['payload'] ?? [];

            $result[] = [
                'type' => $type,
                'url' => $payload['url'] ?? null,
            ];
        }

        return $result;
    }

    protected function detectMediaType(string $url): string
    {
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return 'image';
        }

        if (in_array($extension, ['mp4', 'avi', 'mov', 'mkv', 'webm'], true)) {
            return 'video';
        }

        if (in_array($extension, ['mp3', 'wav', 'ogg', 'm4a'], true)) {
            return 'audio';
        }

        return 'file';
    }
}
