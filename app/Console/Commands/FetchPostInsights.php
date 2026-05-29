<?php

namespace App\Console\Commands;

use App\Models\FacebookPost;
use App\Services\FacebookPageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchPostInsights extends Command
{
    protected $signature = 'social:fetch-insights {--limit=50 : Max posts to process per run}';
    protected $description = 'Fetch engagement metrics (reactions, shares, impressions) for published posts from Meta Insights API';

    public function __construct(private FacebookPageService $facebookService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!$this->facebookService->isConfigured()) {
            $this->warn('Facebook API not configured. Skipping.');
            return 0;
        }

        $limit = (int) $this->option('limit');

        $posts = FacebookPost::published()
            ->whereNotNull('facebook_post_id')
            ->where(function ($q) {
                $q->whereNull('metrics_fetched_at')
                  ->orWhere('metrics_fetched_at', '<', now()->subHours(24));
            })
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();

        if ($posts->isEmpty()) {
            $this->line('No posts require metrics refresh.');
            return 0;
        }

        $this->info("Fetching insights for {$posts->count()} post(s)...");
        $updated = 0;

        foreach ($posts as $post) {
            try {
                $result = $this->facebookService->fetchPostInsights($post->facebook_post_id);

                if ($result['success']) {
                    $post->update([
                        'fb_reactions_count' => $result['reactions'],
                        'fb_shares_count'    => $result['shares'],
                        'fb_impressions'     => $result['impressions'],
                        'metrics_fetched_at' => now(),
                    ]);
                    $updated++;
                    $this->line("  #{$post->id}: {$result['reactions']} reactions, {$result['shares']} shares");
                } else {
                    $this->warn("  #{$post->id}: " . ($result['error'] ?? 'unknown error'));
                }

                usleep(500000); // 0.5s pause to respect rate limits
            } catch (\Throwable $e) {
                Log::warning('FetchPostInsights error', ['post_id' => $post->id, 'error' => $e->getMessage()]);
                $this->warn("  #{$post->id}: Exception — " . $e->getMessage());
            }
        }

        $this->info("Done. Updated {$updated} post(s).");
        return 0;
    }
}
