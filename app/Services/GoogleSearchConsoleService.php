<?php

namespace App\Services;

use Google\Client;
use Google\Service\SearchConsole;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GoogleSearchConsoleService
{
    private ?Client $client = null;
    private ?SearchConsole $service = null;

    public function __construct()
    {
        $this->initializeClient();
    }

    private function initializeClient(): void
    {
        try {
            $credentialsPath = config('services.google.search_console_credentials');
            
            if (!$credentialsPath || !file_exists($credentialsPath)) {
                Log::warning('Google Search Console credentials not found');
                return;
            }

            $this->client = new Client();
            $this->client->setAuthConfig($credentialsPath);
            $this->client->addScope(SearchConsole::WEBMASTERS_READONLY);
            $this->client->setApplicationName(config('app.name'));

            $this->service = new SearchConsole($this->client);
        } catch (\Exception $e) {
            Log::error('Failed to initialize Google Search Console client: ' . $e->getMessage());
        }
    }

    /**
     * Get search analytics data
     */
    public function getSearchAnalytics(string $siteUrl, int $days = 30): ?array
    {
        if (!$this->service) {
            return null;
        }

        $cacheKey = "gsc_analytics_{$days}";
        
        return Cache::remember($cacheKey, 3600, function () use ($siteUrl, $days) {
            try {
                $request = new SearchConsole\SearchAnalyticsQueryRequest();
                $request->setStartDate(now()->subDays($days)->format('Y-m-d'));
                $request->setEndDate(now()->format('Y-m-d'));
                $request->setDimensions(['query', 'page']);
                $request->setRowLimit(100);

                $response = $this->service->searchanalytics->query($siteUrl, $request);

                return [
                    'rows' => $response->getRows() ?? [],
                    'total_clicks' => collect($response->getRows())->sum('clicks'),
                    'total_impressions' => collect($response->getRows())->sum('impressions'),
                    'average_ctr' => collect($response->getRows())->avg('ctr'),
                    'average_position' => collect($response->getRows())->avg('position'),
                ];
            } catch (\Exception $e) {
                Log::error('GSC Search Analytics error: ' . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Get top performing queries
     */
    public function getTopQueries(string $siteUrl, int $limit = 10): array
    {
        if (!$this->service) {
            return [];
        }

        try {
            $request = new SearchConsole\SearchAnalyticsQueryRequest();
            $request->setStartDate(now()->subDays(30)->format('Y-m-d'));
            $request->setEndDate(now()->format('Y-m-d'));
            $request->setDimensions(['query']);
            $request->setRowLimit($limit);

            $response = $this->service->searchanalytics->query($siteUrl, $request);
            
            return collect($response->getRows() ?? [])
                ->map(fn($row) => [
                    'query' => $row->getKeys()[0] ?? '',
                    'clicks' => $row->getClicks() ?? 0,
                    'impressions' => $row->getImpressions() ?? 0,
                    'ctr' => round(($row->getCtr() ?? 0) * 100, 2),
                    'position' => round($row->getPosition() ?? 0, 1),
                ])
                ->toArray();
        } catch (\Exception $e) {
            Log::error('GSC Top Queries error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get indexing status
     */
    public function getIndexingStatus(string $siteUrl): ?array
    {
        if (!$this->service) {
            return null;
        }

        $cacheKey = 'gsc_indexing_status';
        
        return Cache::remember($cacheKey, 7200, function () use ($siteUrl) {
            try {
                $sitemaps = $this->service->sitemaps->listSitemaps($siteUrl);
                
                return [
                    'sitemaps' => collect($sitemaps->getSitemap() ?? [])
                        ->map(fn($sitemap) => [
                            'path' => $sitemap->getPath(),
                            'last_submitted' => $sitemap->getLastSubmitted(),
                            'is_pending' => $sitemap->getIsPending(),
                            'warnings' => $sitemap->getWarnings(),
                            'errors' => $sitemap->getErrors(),
                        ])
                        ->toArray(),
                ];
            } catch (\Exception $e) {
                Log::error('GSC Indexing Status error: ' . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Submit URL for indexing
     */
    public function submitUrl(string $url): bool
    {
        if (!$this->service) {
            return false;
        }

        try {
            $urlNotification = new SearchConsole\UrlNotification();
            $urlNotification->setUrl($url);
            $urlNotification->setType('URL_UPDATED');

            $this->service->urlNotifications->publish($urlNotification);
            
            Log::info("URL submitted to GSC: {$url}");
            return true;
        } catch (\Exception $e) {
            Log::error("GSC URL submission error for {$url}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get mobile usability issues
     */
    public function getMobileUsabilityIssues(string $siteUrl): array
    {
        if (!$this->service) {
            return [];
        }

        try {
            // Note: This requires Mobile Usability API which may need separate setup
            // Placeholder for future implementation
            return [];
        } catch (\Exception $e) {
            Log::error('GSC Mobile Usability error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if service is available
     */
    public function isAvailable(): bool
    {
        return $this->service !== null;
    }
}
