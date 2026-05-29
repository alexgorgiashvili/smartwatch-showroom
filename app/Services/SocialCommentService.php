<?php

namespace App\Services;

use App\Models\FacebookPost;
use App\Models\SocialAutoReplyRule;
use App\Models\SocialBlockedUser;
use App\Models\SocialComment;
use App\Services\Chatbot\ModelCompletionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SocialCommentService
{
    protected string $baseUrl = 'https://graph.facebook.com/v19.0';
    protected string $pageAccessToken;
    protected string $instagramAccessToken;
    protected string $pageId;

    public function __construct()
    {
        $this->pageAccessToken = (string) config('services.facebook.page_access_token', '');
        $this->instagramAccessToken = (string) config('services.facebook.instagram_access_token', '');
        $this->pageId = (string) config('services.facebook.page_id', '');
    }

    /**
     * Fetch comments for a single published post from Meta Graph API.
     */
    public function fetchCommentsForPost(FacebookPost $post): array
    {
        $imported = [];

        if ($post->post_to_facebook && $post->facebook_post_id) {
            $imported = array_merge(
                $imported,
                $this->fetchPlatformComments($post, 'facebook', $post->facebook_post_id)
            );
        }

        if ($post->post_to_instagram && $post->instagram_post_id) {
            $imported = array_merge(
                $imported,
                $this->fetchPlatformComments($post, 'instagram', $post->instagram_post_id)
            );
        }

        return $imported;
    }

    /**
     * Fetch live comments for a platform post without writing to the database.
     */
    public function fetchLiveCommentsSnapshot(string $platform, string $platformPostId, int $limit = 100): array
    {
        $token = $this->resolveToken($platform);

        if ($token === '') {
            return ['success' => false, 'error' => 'access_token_missing', 'comments' => []];
        }

        $comments = [];
        $after = null;
        $remaining = max(1, $limit);

        do {
            $pageLimit = min(100, $remaining);
            $params = [
                'fields' => 'id,message,from,created_time,parent',
                'limit' => $pageLimit,
                'access_token' => $token,
            ];

            if ($after) {
                $params['after'] = $after;
            }

            $result = $this->graphApiGet("/{$platformPostId}/comments", $params);

            if (! $result['success']) {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'comments_fetch_failed',
                    'comments' => $comments,
                ];
            }

            $data = $result['data']['data'] ?? [];
            foreach ($data as $item) {
                $comments[] = [
                    'id' => (string) ($item['id'] ?? ''),
                    'parent_id' => $item['parent']['id'] ?? null,
                    'author_name' => $item['from']['name'] ?? null,
                    'author_id' => $item['from']['id'] ?? null,
                    'message' => (string) ($item['message'] ?? ''),
                    'created_time' => $item['created_time'] ?? null,
                ];
            }

            $remaining -= count($data);
            $after = $result['data']['paging']['cursors']['after'] ?? null;
            $hasNext = ! empty($result['data']['paging']['next']) && $remaining > 0;
        } while ($hasNext && $after);

        return [
            'success' => true,
            'comments' => collect($comments)
                ->filter(fn (array $comment) => $comment['id'] !== '')
                ->values()
                ->all(),
        ];
    }

    /**
     * Fetch comments for all published posts within the given time window.
     */
    public function fetchAllRecentComments(int $hours = 24): int
    {
        $posts = FacebookPost::published()
            ->where('published_at', '>=', now()->subHours($hours))
            ->get();

        $total = 0;

        foreach ($posts as $post) {
            $comments = $this->fetchCommentsForPost($post);
            $total += count($comments);
        }

        return $total;
    }

    /**
     * Reply to a comment via Meta Graph API.
     */
    public function replyToComment(SocialComment $comment, string $replyText): array
    {
        $token = $this->resolveToken($comment->platform);

        if ($token === '') {
            return ['success' => false, 'error' => 'access_token_missing'];
        }

        // Instagram uses /replies endpoint; Facebook uses /comments
        $endpoint = $comment->platform === 'instagram'
            ? "/{$comment->platform_comment_id}/replies"
            : "/{$comment->platform_comment_id}/comments";

        $result = $this->graphApiPost(
            $endpoint,
            ['message' => $replyText],
            $token
        );

        if ($result['success']) {
            $comment->update([
                'status' => 'replied',
                'actual_reply' => $replyText,
                'reply_platform_id' => $result['data']['id'] ?? null,
                'replied_at' => now(),
            ]);
        }

        return $result;
    }

    /**
     * Hide a comment on the platform.
     */
    public function hideComment(SocialComment $comment): array
    {
        $token = $this->resolveToken($comment->platform);

        $result = $this->graphApiPost(
            "/{$comment->platform_comment_id}",
            ['is_hidden' => true],
            $token
        );

        if ($result['success']) {
            $comment->update(['status' => 'hidden']);
        }

        return $result;
    }

    /**
     * Delete a comment on the platform (only own comments).
     */
    public function deleteComment(SocialComment $comment): array
    {
        $token = $this->resolveToken($comment->platform);

        if ($token === '') {
            return ['success' => false, 'error' => 'access_token_missing'];
        }

        try {
            $response = Http::delete("{$this->baseUrl}/{$comment->platform_comment_id}", [
                'access_token' => $token,
            ]);

            if ($response->successful()) {
                $comment->delete();
                return ['success' => true];
            }

            return [
                'success' => false,
                'error' => $response->json('error.message', 'Delete failed'),
            ];
        } catch (\Throwable $e) {
            Log::error('SocialComment delete exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Fetch comments from a specific platform for a post.
     */
    protected function fetchPlatformComments(FacebookPost $post, string $platform, string $platformPostId): array
    {
        $token = $this->resolveToken($platform);

        if ($token === '') {
            Log::warning("SocialComment: No token for {$platform}");
            return [];
        }

        $comments = [];
        $after = null;

        do {
            $params = [
                'fields' => 'id,message,from,created_time,parent',
                'limit' => 100,
                'access_token' => $token,
            ];

            if ($after) {
                $params['after'] = $after;
            }

            $result = $this->graphApiGet("/{$platformPostId}/comments", $params);

            if (!$result['success']) {
                Log::warning("SocialComment: fetch failed for {$platform} post {$platformPostId}", [
                    'error' => $result['error'] ?? 'unknown',
                ]);
                break;
            }

            $data = $result['data']['data'] ?? [];
            $after = $result['data']['paging']['cursors']['after'] ?? null;
            $hasNext = !empty($result['data']['paging']['next']);

            foreach ($data as $item) {
                $comment = $this->upsertComment($post, $platform, $platformPostId, $item);
                if ($comment->wasRecentlyCreated) {
                    event(new \App\Events\SocialCommentCreated($comment));
                    $this->applyAutoReplyIfMatched($comment);
                    $comments[] = $comment;
                }
            }
        } while ($hasNext && $after);

        return $comments;
    }

    /**
     * Upsert a comment record from API data.
     */
    protected function upsertComment(FacebookPost $post, string $platform, string $platformPostId, array $item): SocialComment
    {
        $message = $item['message'] ?? '';

        $comment = SocialComment::updateOrCreate(
            ['platform_comment_id' => $item['id']],
            [
                'facebook_post_id' => $post->id,
                'platform' => $platform,
                'platform_post_id' => $platformPostId,
                'parent_comment_id' => $item['parent']['id'] ?? null,
                'author_name' => $item['from']['name'] ?? null,
                'author_id' => $item['from']['id'] ?? null,
                'message' => $message,
                'commented_at' => isset($item['created_time'])
                    ? \Carbon\Carbon::parse($item['created_time'])
                    : now(),
            ]
        );

        if ($comment->wasRecentlyCreated && $comment->sentiment === null && $message !== '') {
            $sentiment = $this->analyzeSentiment($message);
            $comment->update(['sentiment' => $sentiment]);
        }

        if ($comment->author_id && $this->isAuthorBlocked($platform, (string) $comment->author_id)) {
            if (!in_array($comment->status, ['hidden', 'replied'], true)) {
                $comment->update(['status' => 'spam']);
            }
        }

        return $comment;
    }

    public function analyzeSentiment(string $message): string
    {
        try {
            $completion = app(ModelCompletionService::class);
            $result = $completion->complete(
                'gpt-4.1-nano',
                [
                    [
                        'role' => 'system',
                        'content' => 'სოციალური მედიის კომენტარის ტონი განსაზღვრე. დააბრუნე მხოლოდ ერთი სიტყვა: positive, negative, neutral ან question. სხვა არაფერი.'
                    ],
                    [
                        'role' => 'user',
                        'content' => mb_substr($message, 0, 300),
                    ]
                ],
                ['max_tokens' => 10, 'temperature' => 0]
            );

            $raw = strtolower(trim($result['reply'] ?? 'neutral'));
            return in_array($raw, ['positive', 'negative', 'neutral', 'question'], true) ? $raw : 'neutral';
        } catch (\Throwable $e) {
            Log::warning('SocialComment sentiment analysis failed', ['error' => $e->getMessage()]);
            return 'neutral';
        }
    }

    protected function resolveToken(string $platform): string
    {
        if ($platform === 'instagram' && $this->instagramAccessToken !== '') {
            return $this->instagramAccessToken;
        }

        return $this->pageAccessToken;
    }

    public function blockAuthorFromComment(SocialComment $comment, ?string $reason = null, ?int $blockedBy = null): array
    {
        $authorId = (string) ($comment->author_id ?? '');
        if ($authorId === '') {
            return ['success' => false, 'error' => 'author_id_missing'];
        }

        $blocked = SocialBlockedUser::updateOrCreate(
            ['platform' => $comment->platform, 'author_id' => $authorId],
            [
                'author_name' => $comment->author_name,
                'reason' => $reason,
                'blocked_by' => $blockedBy,
                'blocked_at' => now(),
            ]
        );

        SocialComment::where('platform', $comment->platform)
            ->where('author_id', $authorId)
            ->whereIn('status', ['unread', 'read'])
            ->update(['status' => 'spam']);

        $platformBlocked = null;
        $platformError = null;

        if ($comment->platform === 'facebook' && $this->pageId !== '' && $this->pageAccessToken !== '') {
            $result = $this->graphApiPost("/{$this->pageId}/blocked", ['uid' => $authorId], $this->pageAccessToken);
            $platformBlocked = $result['success'];
            $platformError = $result['success'] ? null : ($result['error'] ?? 'Platform block failed');
        }

        return [
            'success' => true,
            'blocked_id' => $blocked->id,
            'platform_blocked' => $platformBlocked,
            'platform_error' => $platformError,
        ];
    }

    protected function isAuthorBlocked(string $platform, string $authorId): bool
    {
        return SocialBlockedUser::where('platform', $platform)->where('author_id', $authorId)->exists();
    }

    protected function applyAutoReplyIfMatched(SocialComment $comment): void
    {
        if (!$comment->facebook_post_id) {
            return;
        }

        if ($comment->author_id === null || trim((string) $comment->author_id) === '') {
            return;
        }

        if (in_array($comment->status, ['spam', 'hidden', 'replied'], true)) {
            return;
        }

        $rules = SocialAutoReplyRule::query()
            ->where('facebook_post_id', $comment->facebook_post_id)
            ->where('enabled', true)
            ->orderByDesc('created_at')
            ->get();

        if ($rules->isEmpty()) {
            return;
        }

        $message = (string) $comment->message;

        foreach ($rules as $rule) {
            if (!$this->ruleMatches($rule, $message)) {
                continue;
            }

            $authorId = (string) $comment->author_id;
            $countToday = SocialComment::query()
                ->where('facebook_post_id', $comment->facebook_post_id)
                ->where('platform', $comment->platform)
                ->where('author_id', $authorId)
                ->whereNotNull('auto_replied_at')
                ->whereDate('auto_replied_at', now()->toDateString())
                ->count();

            if ($countToday >= (int) $rule->max_replies_per_author_per_day) {
                return;
            }

            $replyText = $rule->use_ai
                ? $this->generateAutoReplyViaAi($comment, $rule->reply_template)
                : $this->renderAutoReplyTemplate($rule->reply_template, $comment);

            if ($replyText === '') {
                return;
            }

            $result = $this->replyToComment($comment, $replyText);

            if ($result['success']) {
                $comment->forceFill([
                    'auto_reply_rule_id' => $rule->id,
                    'auto_replied_at' => now(),
                    'auto_reply_error' => null,
                ])->save();

                $this->insertAuditLog(
                    $rule->user_id,
                    'social.auto_reply',
                    [
                        'social_comment_id' => $comment->id,
                        'rule_id' => $rule->id,
                        'platform' => $comment->platform,
                    ],
                    'Auto-replied to social comment',
                    200
                );
            } else {
                $comment->forceFill([
                    'auto_reply_rule_id' => $rule->id,
                    'auto_reply_error' => $result['error'] ?? 'Auto reply failed',
                ])->save();
            }

            return;
        }
    }

    protected function ruleMatches(SocialAutoReplyRule $rule, string $message): bool
    {
        $type = $rule->match_type;
        $value = (string) $rule->match_value;

        if ($type === 'regex') {
            try {
                return @preg_match($value, $message) === 1;
            } catch (\Throwable) {
                return false;
            }
        }

        if ($type === 'keywords') {
            $parts = preg_split('/[,;\n]+/', $value) ?: [];
            foreach ($parts as $p) {
                $kw = trim($p);
                if ($kw === '') {
                    continue;
                }
                if (mb_stripos($message, $kw) !== false) {
                    return true;
                }
            }
            return false;
        }

        return mb_stripos($message, $value) !== false;
    }

    protected function renderAutoReplyTemplate(string $template, SocialComment $comment): string
    {
        $map = [
            '{author_name}' => (string) ($comment->author_name ?? ''),
            '{comment}' => (string) ($comment->message ?? ''),
            '{platform}' => (string) ($comment->platform ?? ''),
        ];

        return trim(strtr($template, $map));
    }

    protected function generateAutoReplyViaAi(SocialComment $comment, string $template): string
    {
        try {
            $result = app(AiSuggestionService::class)->generateAutoReplyFromTemplate($comment, $template);
            return trim((string) ($result['reply'] ?? ''));
        } catch (\Throwable $e) {
            Log::warning('Auto reply AI failed', ['error' => $e->getMessage()]);
            return '';
        }
    }

    protected function insertAuditLog(?int $userId, string $action, array $parameters = [], ?string $description = null, int $statusCode = 200): void
    {
        try {
            DB::table('admin_audit_logs')->insert([
                'user_id' => $userId,
                'action' => $action,
                'method' => 'SYSTEM',
                'endpoint' => 'system',
                'ip_address' => '0.0.0.0',
                'user_agent' => null,
                'parameters' => json_encode($parameters),
                'description' => $description,
                'status_code' => $statusCode,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable) {
        }
    }

    protected function graphApiGet(string $endpoint, array $params = []): array
    {
        try {
            $response = Http::timeout(20)->get("{$this->baseUrl}{$endpoint}", $params);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            $error = $response->json('error.message', 'API request failed');
            $code = $response->json('error.code');

            Log::warning('Meta Graph API GET failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'error_code' => $code,
                'error' => $error,
            ]);

            return ['success' => false, 'error' => $error, 'error_code' => $code];
        } catch (\Throwable $e) {
            Log::error('Meta Graph API GET exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function graphApiPost(string $endpoint, array $params = [], ?string $token = null): array
    {
        try {
            $params['access_token'] = $token ?: $this->pageAccessToken;

            $response = Http::timeout(20)->post("{$this->baseUrl}{$endpoint}", $params);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            $error = $response->json('error.message', 'API request failed');

            Log::warning('Meta Graph API POST failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'error' => $error,
            ]);

            return ['success' => false, 'error' => $error];
        } catch (\Throwable $e) {
            Log::error('Meta Graph API POST exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check if the service has at least one platform configured.
     */
    public function isConfigured(): bool
    {
        return $this->pageAccessToken !== '' || $this->instagramAccessToken !== '';
    }
}
