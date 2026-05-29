<?php

namespace App\Services;

use App\Models\FacebookCompetitorPage;
use App\Models\FacebookCompetitorPost;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookApifyScraperService
{
    private string $token;
    private string $baseUrl;

    public function __construct()
    {
        $this->token = (string) config('services.apify.token', '');
        $this->baseUrl = rtrim((string) config('services.apify.base_url', 'https://api.apify.com/v2'), '/');
    }

    /**
     * Scrape posts from a Facebook competitor page.
     *
     * @return array{new_posts: int, updated_posts: int, total_scraped: int}
     */
    public function scrapeCompetitorPage(FacebookCompetitorPage $page, ?int $maxPosts = null): array
    {
        // Check if recently scraped (cost optimization)
        $intervalHours = (int) config('services.apify.facebook_scrape_interval_hours', 168);
        if ($page->last_scraped_at && $page->last_scraped_at->gt(now()->subHours($intervalHours))) {
            Log::info('FacebookApifyScraper: Skipping - recently scraped', [
                'page' => $page->name,
                'last_scraped' => $page->last_scraped_at->diffForHumans(),
                'interval_hours' => $intervalHours,
            ]);

            return [
                'new_posts' => 0,
                'updated_posts' => 0,
                'total_scraped' => 0,
                'skipped' => true,
            ];
        }

        $this->ensureToken();

        $actorId = $this->normalizeActorId(
            (string) config('services.apify.facebook_posts_actor', 'apify/facebook-posts-scraper')
        );
        $timeout = (int) config('services.apify.facebook_scrape_timeout', 300);
        $maxPosts = $maxPosts ?? (int) config('services.apify.facebook_max_posts', 20); // Free tier optimized

        $input = $this->buildPostsInput($page, $maxPosts);

        Log::info('FacebookApifyScraper: Starting scrape', [
            'page' => $page->name,
            'url' => $page->facebook_url,
            'max_posts' => $maxPosts,
        ]);

        $items = $this->runActorSync($actorId, $input, $timeout);

        $result = $this->processScrapedPosts($page, $items);

        $page->update([
            'last_scraped_at' => now(),
            'total_posts_count' => $page->posts()->count(),
            'relevant_posts_count' => $page->relevantPosts()->count(),
        ]);

        Log::info('FacebookApifyScraper: Scrape complete', [
            'page' => $page->name,
            'new_posts' => $result['new_posts'],
            'updated_posts' => $result['updated_posts'],
            'total_scraped' => $result['total_scraped'],
        ]);

        return $result;
    }

    /**
     * Scrape page metadata (followers, ratings, etc.).
     */
    public function scrapePageMetadata(FacebookCompetitorPage $page): array
    {
        $this->ensureToken();

        $actorId = $this->normalizeActorId(
            (string) config('services.apify.facebook_pages_actor', 'apify/facebook-pages-scraper')
        );
        $timeout = (int) config('services.apify.facebook_scrape_timeout', 300);

        $input = [
            'startUrls' => [['url' => $page->facebook_url]],
            'proxyConfiguration' => [
                'useApifyProxy' => true,
            ],
        ];

        $items = $this->runActorSync($actorId, $input, $timeout);

        if (!empty($items[0])) {
            $metadata = $items[0];
            $page->update([
                'page_id' => $metadata['pageId'] ?? $metadata['id'] ?? $page->page_id,
                'follower_count' => $metadata['likes'] ?? $metadata['followers'] ?? $page->follower_count,
                'category' => $metadata['categories'][0] ?? $metadata['category'] ?? $page->category,
            ]);
        }

        return $items[0] ?? [];
    }

    /**
     * Estimate the cost of scraping.
     */
    public function estimateCost(int $postsCount): float
    {
        return round($postsCount * 0.007, 2);
    }

    /**
     * Run an Apify actor synchronously and return dataset items.
     */
    private function runActorSync(string $actorId, array $input, int $timeout): array
    {
        $response = Http::withToken($this->token)
            ->acceptJson()
            ->timeout($timeout)
            ->post("{$this->baseUrl}/acts/{$actorId}/run-sync-get-dataset-items", $input);

        if (!$response->successful()) {
            $status = $response->status();
            $body = $response->body();

            Log::error('FacebookApifyScraper: Actor run failed', [
                'actor' => $actorId,
                'status' => $status,
                'body' => mb_substr($body, 0, 500),
            ]);

            throw new \RuntimeException(
                "Apify actor run failed (HTTP {$status}). Check APIFY_API_TOKEN and actor access."
            );
        }

        $items = $response->json();

        if (!is_array($items)) {
            throw new \RuntimeException('Apify returned unexpected response format.');
        }

        return $items;
    }

    /**
     * Process scraped posts and upsert into database.
     *
     * @return array{new_posts: int, updated_posts: int, total_scraped: int}
     */
    private function processScrapedPosts(FacebookCompetitorPage $page, array $items): array
    {
        $newPosts = 0;
        $updatedPosts = 0;

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $postId = $this->extractPostId($item);
            if ($postId === null) {
                continue;
            }

            $postData = [
                'competitor_page_id' => $page->id,
                'facebook_post_id' => $postId,
                'post_url' => $item['postUrl'] ?? $item['url'] ?? null,
                'posted_at' => $this->parseDate($item['time'] ?? $item['date'] ?? $item['timestamp'] ?? null),
                'scraped_at' => now(),
                'text' => $item['text'] ?? $item['message'] ?? null,
                'images_json' => $this->extractImages($item),
                'video_url' => $item['videoUrl'] ?? $item['video'] ?? null,
                'likes_count' => (int) ($item['likes'] ?? $item['likesCount'] ?? 0),
                'comments_count' => (int) ($item['comments'] ?? $item['commentsCount'] ?? 0),
                'shares_count' => (int) ($item['shares'] ?? $item['sharesCount'] ?? 0),
                'reactions_json' => $item['reactions'] ?? $item['reactionsCount'] ?? null,
            ];

            $existing = FacebookCompetitorPost::where('facebook_post_id', $postId)->first();

            if ($existing) {
                $existing->update($postData);
                $updatedPosts++;
            } else {
                FacebookCompetitorPost::create($postData);
                $newPosts++;
            }
        }

        return [
            'new_posts' => $newPosts,
            'updated_posts' => $updatedPosts,
            'total_scraped' => count($items),
        ];
    }

    private function buildPostsInput(FacebookCompetitorPage $page, int $maxPosts): array
    {
        return [
            'startUrls' => [['url' => $page->facebook_url]],
            'maxPosts' => $maxPosts,
            'commentsMode' => 'RANKED_UNFILTERED',
            'scrapeReactions' => true,
            'proxyConfiguration' => [
                'useApifyProxy' => (bool) config('services.apify.use_proxy', true),
            ],
        ];
    }

    private function extractPostId(array $item): ?string
    {
        $postId = $item['postId'] ?? $item['id'] ?? $item['facebookId'] ?? null;

        if ($postId !== null) {
            return (string) $postId;
        }

        $postUrl = $item['postUrl'] ?? $item['url'] ?? '';
        if (preg_match('/\/posts\/(\d+)/', $postUrl, $matches)) {
            return $matches[1];
        }

        if (preg_match('/story_fbid=(\d+)/', $postUrl, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractImages(array $item): ?array
    {
        $images = $item['images'] ?? $item['photos'] ?? $item['media'] ?? null;

        if (is_array($images) && !empty($images)) {
            return $images;
        }

        $singleImage = $item['imageUrl'] ?? $item['image'] ?? $item['photo'] ?? null;
        if ($singleImage) {
            return [$singleImage];
        }

        return null;
    }

    private function parseDate($value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeActorId(string $actorId): string
    {
        $trimmed = trim($actorId);
        if ($trimmed === '') {
            return 'apify~facebook-posts-scraper';
        }

        return str_replace('/', '~', $trimmed);
    }

    private function ensureToken(): void
    {
        if ($this->token === '') {
            throw new \RuntimeException('Apify token is missing. Configure APIFY_API_TOKEN in .env.');
        }
    }
}
