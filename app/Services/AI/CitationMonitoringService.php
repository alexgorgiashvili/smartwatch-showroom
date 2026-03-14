<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CitationMonitoringService
{
    /**
     * Check if MyTechnic.ge is cited in AI responses
     */
    public function checkCitations(string $query): array
    {
        $results = [];

        // Check different AI systems
        $results['perplexity'] = $this->checkPerplexity($query);
        $results['you_com'] = $this->checkYouCom($query);
        
        return $results;
    }

    /**
     * Monitor AI traffic for citation patterns
     */
    public function analyzeCitationPatterns(): array
    {
        $weekAgo = now()->subWeek();

        // Get AI traffic data
        $traffic = DB::table('ai_traffic')
            ->where('created_at', '>=', $weekAgo)
            ->get();

        $patterns = [
            'total_visits' => $traffic->count(),
            'api_calls' => $traffic->where('path', 'like', '/api/ai/%')->count(),
            'product_views' => $traffic->where('path', 'like', '/products/%')->count(),
            'by_family' => [],
            'by_endpoint' => [],
        ];

        // Group by AI family
        foreach ($traffic->groupBy('ai_family') as $family => $visits) {
            $patterns['by_family'][$family] = $visits->count();
        }

        // Group by endpoint
        foreach ($traffic->where('path', 'like', '/api/ai/%')->groupBy('path') as $path => $visits) {
            $patterns['by_endpoint'][$path] = $visits->count();
        }

        return $patterns;
    }

    /**
     * Get recommended products from AI traffic
     */
    public function getRecommendedProducts(): array
    {
        $weekAgo = now()->subWeek();

        // Extract product IDs from paths
        $productViews = DB::table('ai_traffic')
            ->where('created_at', '>=', $weekAgo)
            ->where('path', 'like', '/products/%')
            ->orWhere('path', 'like', '/api/ai/products/%')
            ->select('path', DB::raw('count(*) as count'))
            ->groupBy('path')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $recommendations = [];
        foreach ($productViews as $view) {
            // Extract product slug or ID from path
            if (preg_match('/\/products\/([^\/]+)/', $view->path, $matches)) {
                $recommendations[] = [
                    'product_identifier' => $matches[1],
                    'views' => $view->count,
                    'path' => $view->path,
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Track citation accuracy
     */
    public function trackCitationAccuracy(array $citation): void
    {
        // Log citation for manual review
        Log::channel('daily')->info('AI_CITATION', [
            'source' => $citation['source'] ?? 'unknown',
            'product' => $citation['product'] ?? null,
            'price_cited' => $citation['price'] ?? null,
            'accuracy' => $citation['accuracy'] ?? 'unknown',
            'timestamp' => now()->toIso8601String(),
        ]);

        // Store in database for analytics (future enhancement)
        // DB::table('ai_citations')->insert([...]);
    }

    /**
     * Get citation statistics
     */
    public function getCitationStats(): array
    {
        $monthAgo = now()->subMonth();

        return [
            'total_ai_visits' => DB::table('ai_traffic')
                ->where('created_at', '>=', $monthAgo)
                ->count(),
            'api_calls' => DB::table('ai_traffic')
                ->where('created_at', '>=', $monthAgo)
                ->where('path', 'like', '/api/ai/%')
                ->count(),
            'unique_ai_families' => DB::table('ai_traffic')
                ->where('created_at', '>=', $monthAgo)
                ->distinct('ai_family')
                ->count('ai_family'),
            'top_ai_family' => DB::table('ai_traffic')
                ->where('created_at', '>=', $monthAgo)
                ->select('ai_family', DB::raw('count(*) as count'))
                ->groupBy('ai_family')
                ->orderByDesc('count')
                ->first(),
        ];
    }

    /**
     * Check Perplexity for citations (placeholder)
     */
    private function checkPerplexity(string $query): array
    {
        // This would require Perplexity API access
        // For now, return placeholder
        return [
            'found' => false,
            'message' => 'Perplexity API integration pending',
        ];
    }

    /**
     * Check You.com for citations (placeholder)
     */
    private function checkYouCom(string $query): array
    {
        // This would require You.com API access
        // For now, return placeholder
        return [
            'found' => false,
            'message' => 'You.com API integration pending',
        ];
    }

    /**
     * Generate citation report
     */
    public function generateReport(int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $report = [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => now()->toDateString(),
                'days' => $days,
            ],
            'traffic' => [
                'total_visits' => DB::table('ai_traffic')
                    ->where('created_at', '>=', $startDate)
                    ->count(),
                'by_family' => DB::table('ai_traffic')
                    ->where('created_at', '>=', $startDate)
                    ->select('ai_family', DB::raw('count(*) as count'))
                    ->groupBy('ai_family')
                    ->orderByDesc('count')
                    ->get()
                    ->toArray(),
                'by_bot' => DB::table('ai_traffic')
                    ->where('created_at', '>=', $startDate)
                    ->select('ai_bot', DB::raw('count(*) as count'))
                    ->groupBy('ai_bot')
                    ->orderByDesc('count')
                    ->limit(10)
                    ->get()
                    ->toArray(),
            ],
            'api_usage' => [
                'total_calls' => DB::table('ai_traffic')
                    ->where('created_at', '>=', $startDate)
                    ->where('path', 'like', '/api/ai/%')
                    ->count(),
                'by_endpoint' => DB::table('ai_traffic')
                    ->where('created_at', '>=', $startDate)
                    ->where('path', 'like', '/api/ai/%')
                    ->select('path', DB::raw('count(*) as count'))
                    ->groupBy('path')
                    ->orderByDesc('count')
                    ->get()
                    ->toArray(),
            ],
            'recommended_products' => $this->getRecommendedProducts(),
        ];

        return $report;
    }
}
