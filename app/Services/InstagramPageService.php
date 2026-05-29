<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramPageService
{
    private string $accountId;
    private string $accessToken;
    private string $baseUrl = 'https://graph.facebook.com/v19.0';

    public function __construct()
    {
        $this->accountId = config('services.facebook.instagram_account_id', '');
        $this->accessToken = config('services.facebook.page_access_token');
    }

    /**
     * Publish a media post to Instagram.
     * Instagram requires media — text-only posts are not supported.
     */
    public function publishPost(string $caption, string $mediaUrl, string $mediaType = 'image'): array
    {
        $create = $this->createMediaContainer($caption, $mediaUrl, $mediaType);

        if (!$create['success']) {
            return $create;
        }

        $containerId = (string) ($create['container_id'] ?? '');
        if ($containerId === '') {
            return ['success' => false, 'error' => 'Instagram container ID not returned'];
        }

        $ready = $this->waitForContainerReady($containerId, $mediaType);
        if (!$ready['success']) {
            return array_merge($ready, ['container_id' => $containerId]);
        }

        $publish = $this->publishContainer($containerId);

        return array_merge($publish, ['container_id' => $containerId]);
    }

    public function createMediaContainer(string $caption, string $mediaUrl, string $mediaType = 'image'): array
    {
        try {
            $payload = [
                'caption' => $caption,
                'access_token' => $this->accessToken,
            ];

            if ($mediaType === 'video') {
                $payload['media_type'] = 'VIDEO';
                $payload['video_url'] = $mediaUrl;
            } else {
                $payload['image_url'] = $mediaUrl;
            }

            $response = Http::timeout(30)
                ->retry(2, 400)
                ->post("{$this->baseUrl}/{$this->accountId}/media", $payload);

            if ($response->failed()) {
                Log::error('Instagram container creation failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return [
                    'success' => false,
                    'error' => $response->json('error.message', 'Instagram container creation failed'),
                    'error_code' => $response->json('error.code'),
                ];
            }

            return [
                'success' => true,
                'container_id' => $response->json('id'),
            ];
        } catch (\Throwable $e) {
            Log::error('Instagram container creation exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getContainerStatus(string $containerId): array
    {
        try {
            $response = Http::timeout(20)
                ->retry(1, 250)
                ->get("{$this->baseUrl}/{$containerId}", [
                    'fields' => 'status_code',
                    'access_token' => $this->accessToken,
                ]);

            if ($response->failed()) {
                return [
                    'success' => false,
                    'error' => $response->json('error.message', 'Instagram container status failed'),
                    'error_code' => $response->json('error.code'),
                ];
            }

            return [
                'success' => true,
                'status_code' => $response->json('status_code'),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function publishContainer(string $containerId): array
    {
        try {
            $response = Http::timeout(30)
                ->retry(2, 400)
                ->post("{$this->baseUrl}/{$this->accountId}/media_publish", [
                    'creation_id' => $containerId,
                    'access_token' => $this->accessToken,
                ]);

            if ($response->failed()) {
                Log::error('Instagram publish failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return [
                    'success' => false,
                    'error' => $response->json('error.message', 'Instagram publish failed'),
                    'error_code' => $response->json('error.code'),
                ];
            }

            return [
                'success' => true,
                'post_id' => $response->json('id'),
            ];
        } catch (\Throwable $e) {
            Log::error('Instagram publish exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function waitForContainerReady(string $containerId, string $mediaType): array
    {
        $maxAttempts = $mediaType === 'video' ? 12 : 6;
        $sleepMs = $mediaType === 'video' ? [500, 800, 1200, 1500, 2000, 2500] : [300, 500, 800];
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $status = $this->getContainerStatus($containerId);

            if (!$status['success']) {
                return $status;
            }

            $code = strtoupper((string) ($status['status_code'] ?? ''));

            if ($code === 'FINISHED') {
                return ['success' => true];
            }

            if ($code === 'ERROR') {
                return ['success' => false, 'error' => 'Instagram container processing failed'];
            }

            $delay = $sleepMs[min($attempt, count($sleepMs) - 1)] ?? 1000;
            usleep((int) $delay * 1000);
            $attempt++;
        }

        return ['success' => false, 'error' => 'Instagram media is still processing', 'retryable' => true];
    }

    /**
     * Check if the service is configured properly.
     */
    public function isConfigured(): bool
    {
        return !empty($this->accountId) && !empty($this->accessToken);
    }
}
