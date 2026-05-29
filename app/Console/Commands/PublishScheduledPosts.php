<?php

namespace App\Console\Commands;

use App\Models\FacebookPost;
use App\Services\FacebookPageService;
use App\Services\InstagramPageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PublishScheduledPosts extends Command
{
    protected $signature = 'social:publish-scheduled';
    protected $description = 'Publish all posts whose scheduled_at time has arrived';

    public function __construct(
        private FacebookPageService $facebookService,
        private InstagramPageService $instagramService,
    ) {
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
        $errors   = [];
        $successes = [];
        $mediaType = $post->media_type ?: ($post->video_url ? 'video' : ($post->image_url ? 'image' : 'none'));

        if ($post->post_to_facebook) {
            if (!$this->facebookService->isConfigured()) {
                $errors[] = 'Facebook API not configured';
            } else {
                try {
                    $post->forceFill(['facebook_publish_status' => 'publishing'])->save();
                    $result = $this->facebookService->publishPost(
                        $post->message,
                        $mediaType === 'image' ? $post->image_url : null,
                        $mediaType === 'video' ? $post->video_url : null,
                    );
                    if ($result['success']) {
                        $successes[] = 'Facebook';
                        $post->forceFill([
                            'facebook_post_id'       => $result['post_id'],
                            'facebook_publish_status' => 'published',
                            'facebook_error'          => null,
                        ])->save();
                    } else {
                        $errors[] = 'Facebook: ' . ($result['error'] ?? 'unknown');
                        $post->forceFill(['facebook_publish_status' => 'failed', 'facebook_error' => $result['error']])->save();
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'Facebook exception: ' . $e->getMessage();
                    Log::error('PublishScheduledPosts FB exception', ['post_id' => $post->id, 'error' => $e->getMessage()]);
                }
            }
        }

        if ($post->post_to_instagram) {
            if (!$this->instagramService->isConfigured()) {
                $errors[] = 'Instagram API not configured';
            } elseif ($mediaType === 'none') {
                $errors[] = 'Instagram requires media';
            } else {
                try {
                    $post->forceFill(['instagram_publish_status' => 'publishing'])->save();
                    $mediaUrl = $mediaType === 'video' ? $post->video_url : $post->image_url;
                    $result   = $this->instagramService->publishPost($post->message, (string) $mediaUrl, $mediaType);
                    if ($result['success']) {
                        $successes[] = 'Instagram';
                        $post->forceFill([
                            'instagram_post_id'        => $result['post_id'],
                            'instagram_publish_status' => 'published',
                            'instagram_error'          => null,
                        ])->save();
                    } else {
                        $errors[] = 'Instagram: ' . ($result['error'] ?? 'unknown');
                        $post->forceFill(['instagram_publish_status' => 'failed', 'instagram_error' => $result['error']])->save();
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'Instagram exception: ' . $e->getMessage();
                    Log::error('PublishScheduledPosts IG exception', ['post_id' => $post->id, 'error' => $e->getMessage()]);
                }
            }
        }

        $hasSuccess = !empty($successes);

        $post->update([
            'status'          => $hasSuccess ? 'published' : 'failed',
            'published_at'    => $hasSuccess ? now() : null,
            'error_message'   => !empty($errors) ? implode('; ', $errors) : null,
            'last_publish_check_at' => now(),
        ]);

        $label = implode(' & ', $successes) ?: 'none';
        $this->line("  Post #{$post->id}: published to [{$label}]" . (!empty($errors) ? ' | errors: ' . implode('; ', $errors) : ''));
    }
}
