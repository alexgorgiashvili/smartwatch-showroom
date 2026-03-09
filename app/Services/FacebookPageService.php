<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookPageService
{
    private string $pageId;
    private string $accessToken;
    private string $baseUrl = 'https://graph.facebook.com/v19.0';

    public function __construct()
    {
        $this->pageId = config('services.facebook.page_id');
        $this->accessToken = config('services.facebook.page_access_token');
    }

    /**
     * Publish a text post to the Facebook page.
     */
    public function publishPost(string $message, ?string $imageUrl = null): array
    {
        if ($imageUrl) {
            return $this->publishPhotoPost($message, $imageUrl);
        }

        return $this->publishTextPost($message);
    }

    private function publishTextPost(string $message): array
    {
        $response = Http::post("{$this->baseUrl}/{$this->pageId}/feed", [
            'message' => $message,
            'access_token' => $this->accessToken,
        ]);

        if ($response->failed()) {
            Log::error('Facebook post failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return [
                'success' => false,
                'error' => $response->json('error.message', 'Unknown error'),
            ];
        }

        return [
            'success' => true,
            'post_id' => $response->json('id'),
        ];
    }

    private function publishPhotoPost(string $message, string $imageUrl): array
    {
        $response = Http::post("{$this->baseUrl}/{$this->pageId}/photos", [
            'caption' => $message,
            'url' => $imageUrl,
            'access_token' => $this->accessToken,
        ]);

        if ($response->failed()) {
            Log::error('Facebook photo post failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return [
                'success' => false,
                'error' => $response->json('error.message', 'Unknown error'),
            ];
        }

        return [
            'success' => true,
            'post_id' => $response->json('post_id') ?? $response->json('id'),
        ];
    }

    /**
     * Check if the service is configured properly.
     */
    public function isConfigured(): bool
    {
        return !empty($this->pageId) && !empty($this->accessToken);
    }
}
