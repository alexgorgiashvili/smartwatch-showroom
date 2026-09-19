<?php

namespace App\Console\Commands;

use App\Models\ContentItem;
use App\Services\ContentStudioAnalyticsService;
use Illuminate\Console\Command;

class CollectContentStudioAnalytics extends Command
{
    protected $signature = 'content-studio:collect-analytics {--limit=100}';
    protected $description = 'Collect read-only daily Content Studio snapshots (GA4, GSC, Meta, AI traffic)';
    public function handle(ContentStudioAnalyticsService $analytics): int
    {
        $items = ContentItem::with('campaign')->where('status', 'published')->latest('published_at')->limit((int) $this->option('limit'))->get();
        foreach ($items as $item) foreach ([7, 28, 90] as $days) $analytics->collect($item, $days);
        $this->info("Collected analytics for {$items->count()} item(s).");
        return self::SUCCESS;
    }
}
