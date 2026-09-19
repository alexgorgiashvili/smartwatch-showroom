<?php

namespace App\Services;

use App\Models\FacebookPost;
use Illuminate\Support\Facades\Log;

/** Idempotent publisher used by the legacy UI and scheduled Content Studio releases. */
class SocialPostPublisher
{
    public function __construct(private FacebookPageService $facebook, private InstagramPageService $instagram) {}

    public function publish(FacebookPost $post): array
    {
        $post->refresh(); $errors = []; $successes = [];
        $type = $post->media_type ?: ($post->video_url ? 'video' : ($post->image_url ? 'image' : 'none'));
        if ($post->post_to_facebook && !$post->facebook_post_id) {
            $post->update(['facebook_publish_status' => 'publishing']);
            $r = $this->facebook->isConfigured() ? $this->facebook->publishPost($post->message, $type === 'image' ? $post->image_url : null, $type === 'video' ? $post->video_url : null) : ['success' => false, 'error' => 'Facebook API not configured'];
            if ($r['success'] ?? false) { $post->update(['facebook_post_id' => $r['post_id'], 'facebook_publish_status' => 'published', 'facebook_error' => null]); $successes[] = 'facebook'; }
            else { $post->update(['facebook_publish_status' => 'failed', 'facebook_error' => $r['error'] ?? 'Facebook publish failed']); $errors[] = 'facebook'; }
        }
        if ($post->post_to_instagram && !$post->instagram_post_id) {
            if ($type === 'none') { $r = ['success' => false, 'error' => 'Instagram requires public HTTPS media']; }
            else { $post->update(['instagram_publish_status' => 'publishing']); $r = $this->instagram->isConfigured() ? $this->instagram->publishPost($post->message, (string) ($type === 'video' ? $post->video_url : $post->image_url), $type) : ['success' => false, 'error' => 'Instagram API not configured']; }
            if ($r['success'] ?? false) { $post->update(['instagram_post_id' => $r['post_id'], 'instagram_container_id' => $r['container_id'] ?? null, 'instagram_publish_status' => 'published', 'instagram_error' => null]); $successes[] = 'instagram'; }
            elseif (!empty($r['retryable'])) { $post->update(['instagram_container_id' => $r['container_id'] ?? null, 'instagram_publish_status' => 'publishing']); $successes[] = 'instagram_processing'; }
            else { $post->update(['instagram_publish_status' => 'failed', 'instagram_error' => $r['error'] ?? 'Instagram publish failed']); $errors[] = 'instagram'; }
        }
        $post->refresh(); $madeProgress = $post->facebook_post_id || $post->instagram_post_id || $post->instagram_publish_status === 'publishing';
        $post->update(['status' => $madeProgress ? 'published' : 'failed', 'published_at' => $madeProgress ? ($post->published_at ?: now()) : null, 'error_message' => $errors ? implode('; ', $errors) : null, 'last_publish_check_at' => now()]);
        Log::info('Social post publish attempt completed', ['post_id' => $post->id, 'facebook_done' => (bool) $post->facebook_post_id, 'instagram_done' => (bool) $post->instagram_post_id]);
        return ['success' => $madeProgress, 'successes' => $successes, 'errors' => $errors];
    }
}
