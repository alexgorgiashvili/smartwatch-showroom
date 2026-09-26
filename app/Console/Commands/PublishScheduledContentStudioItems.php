<?php

namespace App\Console\Commands;

use App\Jobs\PublishContentStudioItem;
use App\Models\ContentItem;
use Illuminate\Console\Command;

class PublishScheduledContentStudioItems extends Command
{
    protected $signature = 'content-studio:publish-scheduled {--limit=25}';
    protected $description = 'Queue approved Content Studio items scheduled in Asia/Tbilisi';
    public function handle(): int
    {
        $items = ContentItem::where('status', 'scheduled')->where('scheduled_at', '<=', now())->orderBy('scheduled_at')->limit((int) $this->option('limit'))->get();
        foreach ($items as $item) PublishContentStudioItem::dispatch($item->id);
        $this->info("Queued {$items->count()} Content Studio release(s).");
        return self::SUCCESS;
    }
}
