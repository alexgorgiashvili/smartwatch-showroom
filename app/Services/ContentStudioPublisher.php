<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ContentItem;
use App\Models\FacebookPost;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ContentStudioPublisher
{
    public function __construct(private ContentStudioWorkflow $workflow, private SocialPostPublisher $social) {}

    public function publish(ContentItem $item): ContentItem
    {
        $item->refresh()->load('approvedRevision');
        if (!in_array($item->status, ['approved', 'scheduled', 'failed'], true) || !$item->approvedRevision) {
            throw ValidationException::withMessages(['item' => 'An approved exact revision is required for release.']);
        }
        $this->workflow->transition($item, 'publishing');
        try {
            if ($item->channel === 'article') $this->releaseArticle($item);
            else $this->releaseSocial($item);
            $item->refresh()->update(['status' => 'published', 'published_at' => $item->published_at ?: now(), 'last_error' => null]);
            $this->workflow->audit($item, 'released', 'publishing', 'published', $item->approvedRevision);
        } catch (\Throwable $e) {
            $item->refresh()->update(['status' => 'failed', 'last_error' => Str::limit($e->getMessage(), 1000)]);
            $this->workflow->audit($item, 'release_failed', 'publishing', 'failed', $item->approvedRevision, null, ['error' => get_class($e)]);
            throw $e;
        }
        return $item->fresh();
    }

    private function releaseArticle(ContentItem $item): void
    {
        $p = $item->approvedRevision->payload;
        $slug = Str::slug((string) ($p['slug'] ?? $p['title_en'] ?? $p['title_ka']));
        $slug = $slug ?: 'content-studio-'.$item->id;
        $article = $item->published_record_type === Article::class ? Article::find($item->published_record_id) : null;
        if (!$article) {
            $suffix = 1; $base = $slug;
            while (Article::where('slug', $slug)->exists()) $slug = $base.'-'.$suffix++;
            $article = new Article(['slug' => $slug]);
        }
        $article->fill([
            'title_ka' => $p['title_ka'], 'title_en' => $p['title_en'], 'excerpt_ka' => $p['excerpt_ka'], 'excerpt_en' => $p['excerpt_en'],
            'body_ka' => $p['body_ka'], 'body_en' => $p['body_en'], 'meta_title_ka' => $p['meta_title_ka'], 'meta_title_en' => $p['meta_title_en'],
            'meta_description_ka' => $p['meta_description_ka'], 'meta_description_en' => $p['meta_description_en'],
            'cover_image' => $p['cover_image'] ?? null, 'schema_type' => $p['schema_type'] ?? 'Article', 'is_published' => true, 'published_at' => now(),
        ])->save();
        $item->update(['published_record_type' => Article::class, 'published_record_id' => $article->id]);
    }

    private function releaseSocial(ContentItem $item): void
    {
        $p = $item->approvedRevision->payload;
        $userId = User::where('is_admin', true)->value('id') ?? User::value('id');
        if (!$userId) throw new \RuntimeException('No owner account is available for social release.');
        $post = FacebookPost::firstOrCreate(['content_item_id' => $item->id], [
            'user_id' => $userId, 'message' => $p['message'], 'image_url' => $p['media_type'] === 'image' ? ($p['media_url'] ?? null) : null,
            'video_url' => $p['media_type'] === 'video' ? ($p['media_url'] ?? null) : null, 'media_type' => $p['media_type'] ?? 'none',
            'post_to_facebook' => $item->channel === 'facebook', 'post_to_instagram' => $item->channel === 'instagram', 'status' => 'draft',
        ]);
        $this->social->publish($post);
        $item->update(['published_record_type' => FacebookPost::class, 'published_record_id' => $post->id]);
    }
}
