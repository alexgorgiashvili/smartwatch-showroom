<?php

namespace App\Console\Commands;

use App\Models\FacebookCompetitorPage;
use App\Models\FacebookCompetitorPost;
use App\Services\FacebookApifyScraperService;
use App\Services\FacebookCompetitorAiService;
use Illuminate\Console\Command;

class ScrapeFacebookCompetitors extends Command
{
    protected $signature = 'competitors:scrape-facebook
        {--page= : Scrape a specific page by ID}
        {--max-posts= : Override max posts per page}
        {--analyze : Run AI relevance filtering after scraping}
        {--weekly-analysis : Run comprehensive weekly analysis}
        {--dry-run : Show what would be scraped without actually scraping}';

    protected $description = 'Scrape Facebook competitor pages and optionally run AI analysis';

    public function handle(
        FacebookApifyScraperService $scraper,
        FacebookCompetitorAiService $aiService
    ): int {
        if ($this->option('weekly-analysis')) {
            return $this->runWeeklyAnalysis($aiService);
        }

        $pages = $this->getPages();

        if ($pages->isEmpty()) {
            $this->warn('No active competitor pages found. Add pages via the admin panel.');
            return self::SUCCESS;
        }

        $maxPosts = $this->option('max-posts') ? (int) $this->option('max-posts') : null;
        $isDryRun = (bool) $this->option('dry-run');

        $this->info("Scraping {$pages->count()} competitor page(s)...");

        if ($isDryRun) {
            $estimatedCost = $scraper->estimateCost(($maxPosts ?? 50) * $pages->count());
            $this->table(
                ['Page', 'URL', 'Last Scraped', 'Max Posts'],
                $pages->map(fn (FacebookCompetitorPage $p) => [
                    $p->name,
                    $p->facebook_url,
                    $p->last_scraped_at?->diffForHumans() ?? 'Never',
                    $maxPosts ?? config('services.apify.facebook_max_posts', 50),
                ])->toArray()
            );
            $this->info("Estimated Apify cost: \${$estimatedCost}");
            return self::SUCCESS;
        }

        $totalNew = 0;
        $totalUpdated = 0;

        foreach ($pages as $page) {
            $this->line("  → Scraping: {$page->name} ({$page->facebook_url})");

            try {
                $result = $scraper->scrapeCompetitorPage($page, $maxPosts);
                $totalNew += $result['new_posts'];
                $totalUpdated += $result['updated_posts'];

                $this->info("    ✓ {$result['new_posts']} new, {$result['updated_posts']} updated (total scraped: {$result['total_scraped']})");
            } catch (\Throwable $e) {
                $this->error("    ✗ Failed: {$e->getMessage()}");
                report($e);
            }
        }

        $this->newLine();
        $this->info("Scraping complete: {$totalNew} new posts, {$totalUpdated} updated posts.");

        // Auto-run AI analysis if enabled in config
        $aiAnalysisEnabled = (bool) config('services.apify.facebook_ai_analysis_enabled', true);

        if ($this->option('analyze') || ($aiAnalysisEnabled && $totalNew > 0)) {
            $this->newLine();
            $this->info('Running AI relevance analysis...');
            $this->runRelevanceFilter($aiService);
        }

        return self::SUCCESS;
    }

    private function runRelevanceFilter(FacebookCompetitorAiService $aiService): void
    {
        $unfilteredPosts = FacebookCompetitorPost::query()
            ->whereNull('is_relevant')
            ->orderByDesc('scraped_at')
            ->limit(200)
            ->get();

        if ($unfilteredPosts->isEmpty()) {
            $this->info('No unfiltered posts to analyze.');
            return;
        }

        $this->info("Running AI relevance filter on {$unfilteredPosts->count()} posts...");

        $filtered = $aiService->filterRelevantPosts($unfilteredPosts);
        $relevant = $unfilteredPosts->fresh()->where('is_relevant', true)->count();

        $this->info("  ✓ Filtered {$filtered} posts. {$relevant} are relevant to kids smartwatches.");
    }

    private function runWeeklyAnalysis(FacebookCompetitorAiService $aiService): int
    {
        $this->info('Running comprehensive weekly analysis...');

        $analysis = $aiService->runWeeklyAnalysis();

        if (!$analysis) {
            $this->warn('No data available for weekly analysis.');
            return self::SUCCESS;
        }

        $this->info("  ✓ Analysis complete (ID: {$analysis->id})");
        $this->info("  Posts analyzed: {$analysis->posts_analyzed_count}");

        $recommendations = $analysis->recommendations_json ?? [];
        if (!empty($recommendations)) {
            $this->info("  Recommendations generated: " . count($recommendations));
            foreach ($recommendations as $rec) {
                $priority = strtoupper($rec['priority'] ?? 'medium');
                $label = $rec['title'] ?? $rec['action'] ?? 'N/A';
                $this->line("    [{$priority}] {$label}");
            }
        }

        return self::SUCCESS;
    }

    private function getPages()
    {
        $pageId = $this->option('page');

        if ($pageId) {
            $page = FacebookCompetitorPage::find((int) $pageId);
            if (!$page) {
                $this->error("Page #{$pageId} not found.");
                return collect();
            }
            return collect([$page]);
        }

        return FacebookCompetitorPage::dueForScraping()->get();
    }
}
