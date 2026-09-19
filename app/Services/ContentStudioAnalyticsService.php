<?php

namespace App\Services;

use App\Models\ContentAnalyticsSnapshot;
use App\Models\ContentItem;
use App\Models\FacebookPost;
use Illuminate\Support\Facades\DB;

class ContentStudioAnalyticsService
{
    public function __construct(private GoogleAnalyticsDataService $ga4, private GoogleSearchConsoleService $gsc, private FacebookPageService $facebook, private InstagramPageService $instagram) {}

    public function collect(ContentItem $item, int $days): ContentAnalyticsSnapshot
    {
        $sourceData = [
            'ga4' => $this->ga4->summary($days, $item->campaign?->utm['utm_campaign'] ?? null),
            'gsc' => $this->gsc->getSearchAnalytics((string) config('services.google.site_url'), $days),
            'ai_traffic' => $this->aiTraffic($item, $days),
            'facebook' => $this->social($item, 'facebook'),
            'instagram' => $this->social($item, 'instagram'),
        ];
        $snapshots = [];
        foreach ($sourceData as $source => $metrics) $snapshots[$source] = $this->save($item, $source, $days, $metrics);
        return $snapshots['ga4'];
    }

    public function latest(ContentItem $item, int $days): array
    {
        return ContentAnalyticsSnapshot::where('content_item_id', $item->id)->where('window_days', $days)->get()->keyBy('source')->all();
    }

    private function save(ContentItem $item, string $source, int $days, ?array $metrics): ContentAnalyticsSnapshot
    {
        $existing = ContentAnalyticsSnapshot::where('content_campaign_id', $item->campaign_id)->where('content_item_id', $item->id)->where('source', $source)->where('window_days', $days)->first();
        if ($metrics !== null) return ContentAnalyticsSnapshot::updateOrCreate(['content_campaign_id' => $item->campaign_id, 'content_item_id' => $item->id, 'source' => $source, 'window_days' => $days], ['metrics' => $metrics, 'collected_at' => now(), 'status' => 'fresh', 'error' => null]);
        if ($existing) { $existing->update(['status' => 'stale', 'error' => 'Source unavailable; showing last successful snapshot.']); return $existing; }
        return ContentAnalyticsSnapshot::create(['content_campaign_id' => $item->campaign_id, 'content_item_id' => $item->id, 'source' => $source, 'window_days' => $days, 'metrics' => [], 'collected_at' => now(), 'status' => 'error', 'error' => 'No snapshot available.']);
    }

    private function aiTraffic(ContentItem $item, int $days): array
    {
        $path = $item->published_record_type === \App\Models\Article::class && $item->published_record_id ? optional(\App\Models\Article::find($item->published_record_id))->slug : null;
        $query = DB::table('ai_traffic')->where('created_at', '>=', now()->subDays($days));
        if ($path) $query->where('path', 'like', '%/blog/'.$path.'%');
        return ['crawler_requests' => $query->count(), 'note' => 'AI crawler requests measure access only; they do not prove citations, mentions, or ranking.'];
    }

    private function social(ContentItem $item, string $channel): ?array
    {
        if ($item->published_record_type !== FacebookPost::class || !$item->published_record_id) return [];
        $post = FacebookPost::find($item->published_record_id); if (!$post) return [];
        if ($channel === 'facebook' && $post->facebook_post_id && $this->facebook->isConfigured()) return $this->facebook->fetchPostInsights($post->facebook_post_id);
        if ($channel === 'instagram' && $post->instagram_post_id && $this->instagram->isConfigured()) return $this->instagram->fetchPostInsights($post->instagram_post_id);
        return [];
    }
}
