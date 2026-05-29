<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FacebookPost;
use App\Models\Product;
use App\Models\SocialComment;
use App\Services\FacebookPageService;
use App\Services\InstagramPageService;
use App\Services\SocialCommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SocialDashboardController extends Controller
{
    public function __construct(
        private FacebookPageService $facebookService,
        private InstagramPageService $instagramService,
        private SocialCommentService $socialCommentService,
    ) {}

    public function index(Request $request)
    {
        $products = Product::active()->orderBy('name_ka')->get(['id', 'name_ka', 'name_en', 'price', 'sale_price']);

        $view = view('admin.social-dashboard.index', [
            'products'     => $products,
            'fbConfigured' => $this->facebookService->isConfigured(),
            'igConfigured' => $this->instagramService->isConfigured(),
        ]);

        return $this->renderPjaxView($request, $view);
    }

    public function stats(): JsonResponse
    {
        return response()->json($this->getStats());
    }

    public function posts(Request $request): JsonResponse
    {
        $query = FacebookPost::with(['user:id,name', 'product:id,name_ka,name_en'])
            ->when(
                $request->filled('status') && $request->query('status') !== 'all',
                fn ($q) => $q->where('status', $request->query('status'))
            )
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = trim((string) $request->query('search'));
                $q->where('message', 'like', "%{$term}%");
            })
            ->orderByDesc('created_at');

        $paginated = $query->paginate(15);
        $posts = collect($paginated->items())
            ->map(fn (FacebookPost $p) => [
                'id'            => $p->id,
                'message'       => mb_substr((string) $p->message, 0, 100),
                'status'        => $p->status,
                'post_to_facebook' => (bool) $p->post_to_facebook,
                'post_to_instagram' => (bool) $p->post_to_instagram,
                'has_image'     => !empty($p->image_url),
                'media_type'    => $p->media_type,
                'published_at'  => $p->published_at?->diffForHumans(),
                'scheduled_at'  => $p->scheduled_at?->format('M d, H:i'),
                'created_at'    => $p->created_at->diffForHumans(),
                'author'        => $p->user?->name ?? '—',
                'product'       => $p->product ? ($p->product->name_ka ?: $p->product->name_en) : null,
                'fb_reactions'  => $p->fb_reactions_count,
                'fb_shares'     => $p->fb_shares_count,
                'ig_likes'      => $p->ig_likes_count,
            ])
            ->values();

        return response()->json([
            'data' => $posts,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    public function scheduled(Request $request): JsonResponse
    {
        $posts = FacebookPost::where('status', 'scheduled')
            ->orderBy('scheduled_at')
            ->get(['id', 'message', 'scheduled_at', 'post_to_facebook', 'post_to_instagram', 'image_url', 'created_at'])
            ->map(fn (FacebookPost $p) => [
                'id'            => $p->id,
                'message'       => mb_substr((string) $p->message, 0, 100),
                'scheduled_at'  => $p->scheduled_at?->format('Y-m-d H:i'),
                'scheduled_at_human' => $p->scheduled_at?->diffForHumans(),
                'post_to_facebook'  => (bool) $p->post_to_facebook,
                'post_to_instagram' => (bool) $p->post_to_instagram,
                'has_image'     => !empty($p->image_url),
            ]);

        return response()->json(['data' => $posts]);
    }

    public function platformStatus(): JsonResponse
    {
        return response()->json([
            'fb_configured' => $this->facebookService->isConfigured(),
            'ig_configured' => $this->instagramService->isConfigured(),
        ]);
    }

    public function compareFacebook(Request $request): JsonResponse
    {
        $limit = max(1, min(50, (int) $request->integer('limit', 10)));
        $commentLimit = max(1, min(200, (int) $request->integer('comment_limit', 100)));

        if (! $this->facebookService->isConfigured()) {
            return response()->json(['error' => 'Facebook API not configured'], 422);
        }

        $remotePostsResult = $this->facebookService->fetchRecentPosts($limit);
        if (! ($remotePostsResult['success'] ?? false)) {
            return response()->json(['error' => $remotePostsResult['error'] ?? 'Facebook posts fetch failed'], 502);
        }

        $remotePosts = collect($remotePostsResult['posts'] ?? []);
        $remotePostIds = $remotePosts->pluck('id')->filter()->values();

        $localPosts = FacebookPost::query()
            ->withCount('comments')
            ->whereNotNull('facebook_post_id')
            ->whereIn('facebook_post_id', $remotePostIds)
            ->get()
            ->keyBy(fn (FacebookPost $post) => (string) $post->facebook_post_id);

        $comparison = $remotePosts->map(function (array $remotePost) use ($localPosts, $commentLimit) {
            $remotePostId = (string) $remotePost['id'];
            /** @var FacebookPost|null $localPost */
            $localPost = $localPosts->get($remotePostId);

            $entry = [
                'facebook_post_id' => $remotePostId,
                'remote_message' => mb_substr((string) ($remotePost['message'] ?? ''), 0, 120),
                'remote_created_time' => $remotePost['created_time'] ?? null,
                'remote_permalink_url' => $remotePost['permalink_url'] ?? null,
                'local_post_id' => $localPost?->id,
                'local_status' => $localPost?->status,
                'local_comments_count' => $localPost?->comments_count ?? 0,
                'matched_local_post' => (bool) $localPost,
            ];

            if (! $localPost) {
                $entry['comment_compare'] = null;
                return $entry;
            }

            $remoteCommentsResult = $this->socialCommentService->fetchLiveCommentsSnapshot('facebook', $remotePostId, $commentLimit);
            if (! ($remoteCommentsResult['success'] ?? false)) {
                $entry['comment_compare'] = [
                    'success' => false,
                    'error' => $remoteCommentsResult['error'] ?? 'Remote comments fetch failed',
                ];

                return $entry;
            }

            $remoteComments = collect($remoteCommentsResult['comments'] ?? []);
            $remoteCommentIds = $remoteComments->pluck('id')->filter()->values();
            $localCommentIds = $localPost->comments()
                ->where('platform', 'facebook')
                ->pluck('platform_comment_id')
                ->map(fn ($id) => (string) $id)
                ->values();

            $missingLocalCommentIds = $remoteCommentIds->diff($localCommentIds)->values();
            $extraLocalCommentIds = $localCommentIds->diff($remoteCommentIds)->values();

            $entry['comment_compare'] = [
                'success' => true,
                'remote_count' => $remoteCommentIds->count(),
                'local_count' => $localCommentIds->count(),
                'missing_local_count' => $missingLocalCommentIds->count(),
                'extra_local_count' => $extraLocalCommentIds->count(),
                'missing_local_comment_ids' => $missingLocalCommentIds->take(50)->all(),
                'extra_local_comment_ids' => $extraLocalCommentIds->take(50)->all(),
            ];

            return $entry;
        })->values();

        $missingLocalPosts = $remotePosts
            ->filter(fn (array $remotePost) => ! $localPosts->has((string) $remotePost['id']))
            ->map(fn (array $remotePost) => [
                'facebook_post_id' => (string) $remotePost['id'],
                'message' => mb_substr((string) ($remotePost['message'] ?? ''), 0, 120),
                'created_time' => $remotePost['created_time'] ?? null,
                'permalink_url' => $remotePost['permalink_url'] ?? null,
            ])
            ->values();

        return response()->json([
            'meta' => [
                'remote_posts_count' => $remotePosts->count(),
                'matched_local_posts_count' => $comparison->where('matched_local_post', true)->count(),
                'missing_local_posts_count' => $missingLocalPosts->count(),
                'limit' => $limit,
                'comment_limit' => $commentLimit,
            ],
            'missing_local_posts' => $missingLocalPosts,
            'posts' => $comparison,
        ]);
    }

    private function getStats(): array
    {
        $totalPosts = FacebookPost::count();
        $publishedPosts = FacebookPost::published()->count();
        $scheduledPosts = FacebookPost::where('status', 'scheduled')->count();
        $draftPosts = FacebookPost::where('status', 'draft')->count();

        $totalComments = SocialComment::count();
        $unreadComments = SocialComment::unread()->count();
        $repliedComments = SocialComment::where('status', 'replied')->count();

        $totalReactions = (int) FacebookPost::whereNotNull('fb_reactions_count')->sum('fb_reactions_count');
        $totalShares = (int) FacebookPost::whereNotNull('fb_shares_count')->sum('fb_shares_count');
        $totalIgLikes = (int) FacebookPost::whereNotNull('ig_likes_count')->sum('ig_likes_count');

        $recentPosts = FacebookPost::query()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'message', 'status', 'published_at', 'post_to_facebook', 'post_to_instagram', 'image_url', 'created_at'])
            ->map(fn ($p) => [
                'id'           => $p->id,
                'message'      => mb_substr((string) $p->message, 0, 80),
                'status'       => $p->status,
                'published_at' => $p->published_at?->format('M d, H:i'),
                'platforms'    => array_values(array_filter([
                    $p->post_to_facebook ? 'FB' : null,
                    $p->post_to_instagram ? 'IG' : null,
                ])),
                'has_image'    => !empty($p->image_url),
            ]);

        $recentComments = SocialComment::query()
            ->orderByDesc('commented_at')
            ->limit(5)
            ->get(['id', 'author_name', 'platform', 'message', 'status', 'commented_at'])
            ->map(fn ($c) => [
                'id'           => $c->id,
                'author_name'  => $c->author_name ?? 'Unknown',
                'platform'     => $c->platform,
                'message'      => mb_substr((string) $c->message, 0, 60),
                'status'       => $c->status,
                'commented_at' => $c->commented_at?->diffForHumans(),
            ]);

        return [
            'total_posts'      => $totalPosts,
            'published_posts'  => $publishedPosts,
            'scheduled_posts'  => $scheduledPosts,
            'draft_posts'      => $draftPosts,
            'failed_posts'     => $totalPosts - $publishedPosts - $scheduledPosts - $draftPosts,
            'total_comments'   => $totalComments,
            'unread_comments'  => $unreadComments,
            'replied_comments' => $repliedComments,
            'total_reactions'  => $totalReactions,
            'total_shares'     => $totalShares,
            'total_ig_likes'   => $totalIgLikes,
            'recent_posts'     => $recentPosts,
            'recent_comments'  => $recentComments,
        ];
    }
}
