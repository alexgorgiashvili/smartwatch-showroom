<?php

namespace App\Console\Commands;

use App\Models\FacebookPost;
use App\Services\SocialPostPublisher;
use Illuminate\Console\Command;

class PublishScheduledPosts extends Command
{
    protected $signature = 'social:publish-scheduled';
    protected $description = 'Publish all posts whose scheduled_at time has arrived';

    public function __construct(private SocialPostPublisher $publisher) {
        parent::__construct();
    }

    public function handle(): int
    {
        $posts = FacebookPost::scheduled()->get();

        if ($posts->isEmpty()) {
            $this->line('No scheduled posts due for publishing.');
            return 0;
        }

        $this->info("Publishing {$posts->count()} scheduled post(s)...");

        foreach ($posts as $post) {
            $this->publishPost($post);
        }

        return 0;
    }

    private function publishPost(FacebookPost $post): void
    {
        $result = $this->publisher->publish($post);
        $label = implode(' & ', $result['successes']) ?: 'none';
        $this->line("  Post #{$post->id}: published to [{$label}]" . ($result['errors'] ? ' | errors: '.implode('; ', $result['errors']) : ''));
    }
}
