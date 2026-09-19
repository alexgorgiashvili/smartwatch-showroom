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
            if (!class_exists(Client::class) || !class_exists(SearchConsole::class)) {
                Log::notice('Google Search Console client dependency is not installed.');
                return;
            }
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
            Log::error('Failed to initialize Google Search Console client.');
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

                $rows = collect($response->getRows() ?? [])->map(fn ($row) => [
                    'keys' => $row->getKeys() ?? [],
                    'clicks' => (float) ($row->getClicks() ?? 0),
                    'impressions' => (float) ($row->getImpressions() ?? 0),
                    'ctr' => (float) ($row->getCtr() ?? 0),
                    'position' => (float) ($row->getPosition() ?? 0),
                ])->values();
                return [
                    'rows' => $rows->all(),
                    'total_clicks' => $rows->sum('clicks'),
                    'total_impressions' => $rows->sum('impressions'),
                    'average_ctr' => $rows->avg('ctr'),
                    'average_position' => $rows->avg('position'),
                ];
            } catch (\Exception $e) {
                Log::error('GSC Search Analytics request failed.');
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
            Log::error('GSC Top Queries request failed.');
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
            Log::error('GSC Indexing Status request failed.');
                return null;
            }
        });
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
