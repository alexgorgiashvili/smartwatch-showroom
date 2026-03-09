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
     * Publish a photo post to Instagram.
     * Instagram requires an image — text-only posts are not supported.
     */
    public function publishPost(string $caption, string $imageUrl): array
    {
        // Step 1: Create media container
        $containerResponse = Http::post("{$this->baseUrl}/{$this->accountId}/media", [
            'caption' => $caption,
            'image_url' => $imageUrl,
            'access_token' => $this->accessToken,
        ]);

        if ($containerResponse->failed()) {
            Log::error('Instagram container creation failed', [
                'status' => $containerResponse->status(),
                'body' => $containerResponse->json(),
            ]);

            return [
                'success' => false,
                'error' => $containerResponse->json('error.message', 'Instagram container creation failed'),
            ];
        }

        $containerId = $containerResponse->json('id');

        if (!$containerId) {
            return [
                'success' => false,
                'error' => 'Instagram container ID not returned',
            ];
        }

        // Step 2: Publish the container
        $publishResponse = Http::post("{$this->baseUrl}/{$this->accountId}/media_publish", [
            'creation_id' => $containerId,
            'access_token' => $this->accessToken,
        ]);

        if ($publishResponse->failed()) {
            Log::error('Instagram publish failed', [
                'status' => $publishResponse->status(),
                'body' => $publishResponse->json(),
            ]);

            return [
                'success' => false,
                'error' => $publishResponse->json('error.message', 'Instagram publish failed'),
            ];
        }

        return [
            'success' => true,
            'post_id' => $publishResponse->json('id'),
        ];
    }

    /**
     * Check if the service is configured properly.
     */
    public function isConfigured(): bool
    {
        return !empty($this->accountId) && !empty($this->accessToken);
    }
}
