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
    public function publishPost(string $message, ?string $imageUrl = null, ?string $videoUrl = null): array
    {
        if ($videoUrl) {
            return $this->publishVideoPost($message, $videoUrl);
        }

        if ($imageUrl) {
            return $this->publishPhotoPost($message, $imageUrl);
        }

        return $this->publishTextPost($message);
    }

    private function publishTextPost(string $message): array
    {
        try {
            $response = Http::timeout(20)
                ->retry(2, 300)
                ->post("{$this->baseUrl}/{$this->pageId}/feed", [
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
                    'error_code' => $response->json('error.code'),
                ];
            }

            return [
                'success' => true,
                'post_id' => $response->json('id'),
            ];
        } catch (\Throwable $e) {
            Log::error('Facebook post exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function publishPhotoPost(string $message, string $imageUrl): array
    {
        try {
            $response = Http::timeout(30)
                ->retry(2, 300)
                ->post("{$this->baseUrl}/{$this->pageId}/photos", [
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
                    'error_code' => $response->json('error.code'),
                ];
            }

            return [
                'success' => true,
                'post_id' => $response->json('post_id') ?? $response->json('id'),
            ];
        } catch (\Throwable $e) {
            Log::error('Facebook photo post exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function publishVideoPost(string $message, string $videoUrl): array
    {
        try {
            $response = Http::timeout(60)
                ->retry(2, 500)
                ->post("{$this->baseUrl}/{$this->pageId}/videos", [
                    'description' => $message,
                    'file_url' => $videoUrl,
                    'access_token' => $this->accessToken,
                ]);

            if ($response->failed()) {
                Log::error('Facebook video post failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return [
                    'success' => false,
                    'error' => $response->json('error.message', 'Unknown error'),
                    'error_code' => $response->json('error.code'),
                ];
            }

            return [
                'success' => true,
                'post_id' => $response->json('id'),
            ];
        } catch (\Throwable $e) {
            Log::error('Facebook video post exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Fetch engagement metrics for a published post (reactions, shares, impressions).
     */
    public function fetchPostInsights(string $fbPostId): array
    {
        try {
            $response = Http::timeout(15)->get("{$this->baseUrl}/{$fbPostId}/insights", [
                'metric'       => 'post_reactions_by_type_total,post_shares,post_impressions',
                'access_token' => $this->accessToken,
            ]);

            if ($response->failed()) {
                return ['success' => false, 'error' => $response->json('error.message', 'Insights request failed')];
            }

            $data    = $response->json('data', []);
            $metrics = [];
            foreach ($data as $metric) {
                $metrics[$metric['name']] = $metric['values'][0]['value'] ?? 0;
            }

            $reactions = $metrics['post_reactions_by_type_total'] ?? 0;
            $totalReactions = is_array($reactions) ? array_sum($reactions) : (int) $reactions;

            return [
                'success'     => true,
                'reactions'   => $totalReactions,
                'shares'      => (int) ($metrics['post_shares'] ?? 0),
                'impressions' => (int) ($metrics['post_impressions'] ?? 0),
            ];
        } catch (\Throwable $e) {
            Log::warning('Facebook fetchPostInsights exception', ['post_id' => $fbPostId, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Fetch recent live posts from the configured Facebook page.
     */
    public function fetchRecentPosts(int $limit = 25): array
    {
        try {
            $response = Http::timeout(20)->get("{$this->baseUrl}/{$this->pageId}/posts", [
                'fields'       => 'id,message,created_time,permalink_url,status_type',
                'limit'        => $limit,
                'access_token' => $this->accessToken,
            ]);

            if ($response->failed()) {
                Log::warning('Facebook fetchRecentPosts failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return [
                    'success' => false,
                    'error' => $response->json('error.message', 'Posts request failed'),
                ];
            }

            $posts = collect($response->json('data', []))
                ->map(fn (array $post) => [
                    'id' => (string) ($post['id'] ?? ''),
                    'message' => (string) ($post['message'] ?? ''),
                    'created_time' => $post['created_time'] ?? null,
                    'permalink_url' => $post['permalink_url'] ?? null,
                    'status_type' => $post['status_type'] ?? null,
                ])
                ->filter(fn (array $post) => $post['id'] !== '')
                ->values()
                ->all();

            return [
                'success' => true,
                'posts' => $posts,
            ];
        } catch (\Throwable $e) {
            Log::warning('Facebook fetchRecentPosts exception', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if the service is configured properly.
     */
    public function isConfigured(): bool
    {
        return !empty($this->pageId) && !empty($this->accessToken);
    }
}
