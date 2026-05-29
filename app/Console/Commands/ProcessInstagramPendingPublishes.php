<?php

namespace App\Console\Commands;

use App\Models\FacebookPost;
use App\Services\InstagramPageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessInstagramPendingPublishes extends Command
{
    protected $signature = 'social:process-instagram-publishes {--limit=25}';

    protected $description = 'Finalize Instagram publishes that are waiting for media container processing';

    public function handle(InstagramPageService $instagram): int
    {
        $limit = (int) $this->option('limit');

        if (!$instagram->isConfigured()) {
            $this->warn('Instagram API not configured.');
            return self::SUCCESS;
        }

        $posts = FacebookPost::query()
            ->where('instagram_publish_status', 'publishing')
            ->whereNotNull('instagram_container_id')
            ->whereNull('instagram_post_id')
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        foreach ($posts as $post) {
            $containerId = (string) $post->instagram_container_id;
            if ($containerId === '') {
                continue;
            }

            $status = $instagram->getContainerStatus($containerId);
            $post->forceFill(['last_publish_check_at' => now()])->save();

            if (!$status['success']) {
                $post->forceFill([
                    'instagram_publish_status' => 'failed',
                    'instagram_error' => $status['error'] ?? 'Container status failed',
                ])->save();

                Log::warning('Instagram pending publish: status check failed', [
                    'facebook_post_id' => $post->id,
                    'container_id' => $containerId,
                    'error' => $status['error'] ?? null,
                ]);
                continue;
            }

            $code = strtoupper((string) ($status['status_code'] ?? ''));

            if ($code === 'IN_PROGRESS') {
                continue;
            }

            if ($code === 'ERROR') {
                $post->forceFill([
                    'instagram_publish_status' => 'failed',
                    'instagram_error' => 'Instagram container processing failed',
                ])->save();
                continue;
            }

            if ($code !== 'FINISHED') {
                continue;
            }

            $publish = $instagram->publishContainer($containerId);

            if ($publish['success']) {
                $post->forceFill([
                    'instagram_post_id' => $publish['post_id'] ?? null,
                    'instagram_publish_status' => 'published',
                    'instagram_error' => null,
                ])->save();

                if ($post->status !== 'published') {
                    $post->forceFill([
                        'status' => 'published',
                        'published_at' => $post->published_at ?: now(),
                    ])->save();
                }
            } else {
                $post->forceFill([
                    'instagram_publish_status' => 'failed',
                    'instagram_error' => $publish['error'] ?? 'Instagram publish failed',
                ])->save();
            }
        }

        $this->info("Processed {$posts->count()} pending Instagram publish(es).");

        return self::SUCCESS;
    }
}

