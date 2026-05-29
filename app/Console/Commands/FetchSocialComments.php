<?php

namespace App\Console\Commands;

use App\Services\SocialCommentService;
use Illuminate\Console\Command;

class FetchSocialComments extends Command
{
    protected $signature = 'social:fetch-comments {--hours=24 : Time window in hours}';

    protected $description = 'Fetch recent comments from Facebook and Instagram via Meta Graph API';

    public function handle(SocialCommentService $service): int
    {
        if (!$service->isConfigured()) {
            $this->error('No Meta API tokens configured. Set FACEBOOK_PAGE_ACCESS_TOKEN or INSTAGRAM_ACCESS_TOKEN.');
            return self::FAILURE;
        }

        $hours = (int) $this->option('hours');
        $this->info("Fetching comments for posts published in the last {$hours} hours...");

        $count = $service->fetchAllRecentComments($hours);

        $this->info("Done. {$count} new comment(s) imported.");

        return self::SUCCESS;
    }
}
