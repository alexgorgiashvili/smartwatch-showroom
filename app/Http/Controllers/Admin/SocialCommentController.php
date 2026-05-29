<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FacebookPost;
use App\Models\SocialComment;
use App\Models\SocialAutoReplyRule;
use App\Models\SocialQuickReply;
use App\Services\AiSuggestionService;
use App\Services\SocialCommentService;
use App\Traits\AuditTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use OpenSpout\Writer\Common\Creator\WriterEntityFactory;

class SocialCommentController extends Controller
{
    use AuditTrait;

    public function index(Request $request)
    {
        $view = view('admin.social-comments.index');

        return $this->renderPjaxView($request, $view);
    }

    // ── JSON API ────────────────────────────────────────────────

    public function list(Request $request): JsonResponse
    {
        $query = SocialComment::query()
            ->with(['facebookPost:id,message,image_url'])
            ->orderByDesc('commented_at');

        // Filters
        if ($request->filled('status') && $request->query('status') !== 'all') {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('platform') && $request->query('platform') !== 'all') {
            $query->where('platform', $request->query('platform'));
        }

        if ($request->filled('sentiment') && $request->query('sentiment') !== 'all') {
            $query->where('sentiment', $request->query('sentiment'));
        }

        if ($request->filled('search')) {
            $term = trim($request->query('search'));
            $query->where(function ($q) use ($term) {
                $q->where('author_name', 'like', "%{$term}%")
                  ->orWhere('message', 'like', "%{$term}%");
            });
        }

        if ($request->filled('date_from')) {
            try {
                $from = \Carbon\Carbon::parse($request->query('date_from'))->startOfDay();
                $query->where('commented_at', '>=', $from);
            } catch (\Throwable) {
            }
        }

        if ($request->filled('date_to')) {
            try {
                $to = \Carbon\Carbon::parse($request->query('date_to'))->endOfDay();
                $query->where('commented_at', '<=', $to);
            } catch (\Throwable) {
            }
        }

        $comments = $query->paginate(25);

        $commentIds = $comments->getCollection()->pluck('platform_comment_id');
        $parentIds  = SocialComment::whereIn('parent_comment_id', $commentIds)
            ->pluck('parent_comment_id')
            ->unique()
            ->flip();

        $items = $comments->getCollection()
            ->map(fn (SocialComment $c) => [
                'id'                  => $c->id,
                'author_name'         => $c->author_name ?? 'Unknown',
                'author_id'           => $c->author_id,
                'platform'            => $c->platform,
                'platform_comment_id' => $c->platform_comment_id,
                'message'             => $c->message,
                'message_short'       => mb_substr($c->message, 0, 80),
                'sentiment'           => $c->sentiment,
                'status'              => $c->status,
                'ai_suggested_reply'  => $c->ai_suggested_reply,
                'actual_reply'        => $c->actual_reply,
                'facebook_post_id'    => $c->facebook_post_id,
                'post_preview'        => $c->facebookPost
                    ? mb_substr((string) $c->facebookPost->message, 0, 50)
                    : null,
                'commented_at'        => $c->commented_at?->diffForHumans(),
                'replied_at'          => $c->replied_at?->diffForHumans(),
                'auto_replied_at'     => $c->auto_replied_at?->diffForHumans(),
                'has_replies'         => isset($parentIds[$c->platform_comment_id]),
            ])
            ->values();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $comments->currentPage(),
                'last_page'    => $comments->lastPage(),
                'total'        => $comments->total(),
            ],
            'counts' => [
                'unread'   => SocialComment::unread()->count(),
                'total'    => SocialComment::count(),
            ],
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:unread,read,replied,spam,hidden'],
        ]);

        $comment = SocialComment::findOrFail($id);
        $comment->update(['status' => $data['status']]);
        $this->audit('social_comment.status_update', ['id' => $id, 'status' => $data['status']]);

        return response()->json(['ok' => true]);
    }

    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids'    => ['required', 'array'],
            'ids.*'  => ['integer'],
            'status' => ['required', 'in:read,spam,hidden'],
        ]);

        SocialComment::whereIn('id', $data['ids'])->update(['status' => $data['status']]);
        $this->audit('social_comment.bulk_status_update', ['count' => count($data['ids']), 'status' => $data['status']]);

        return response()->json(['ok' => true, 'count' => count($data['ids'])]);
    }

    public function generateReply(int $id): JsonResponse
    {
        $comment = SocialComment::findOrFail($id);

        try {
            $result = app(AiSuggestionService::class)->generateCommentReply($comment);

            return response()->json([
                'reply'          => $result['reply'] ?? '',
                'suggested_tone' => $result['suggested_tone'] ?? 'warm',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'AI generation failed: ' . $e->getMessage()], 500);
        }
    }

    public function sendReply(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reply' => ['required', 'string', 'max:5000'],
        ]);

        $comment = SocialComment::findOrFail($id);

        $result = app(SocialCommentService::class)->replyToComment($comment, $data['reply']);

        if ($result['success']) {
            $this->audit('social_comment.reply', ['id' => $id]);
            return response()->json(['ok' => true]);
        }

        return response()->json(['error' => $result['error'] ?? 'Reply failed'], 500);
    }

    public function hideComment(int $id): JsonResponse
    {
        $comment = SocialComment::findOrFail($id);

        $result = app(SocialCommentService::class)->hideComment($comment);

        if ($result['success']) {
            $this->audit('social_comment.hide', ['id' => $id]);
            return response()->json(['ok' => true]);
        }

        return response()->json(['error' => $result['error'] ?? 'Hide failed'], 500);
    }

    public function fetchComments(): JsonResponse
    {
        $service = app(SocialCommentService::class);

        if (!$service->isConfigured()) {
            return response()->json(['error' => 'Meta API not configured'], 422);
        }

        $count = $service->fetchAllRecentComments(72);
        $this->audit('social_comment.fetch', ['imported' => $count]);

        return response()->json(['ok' => true, 'imported' => $count]);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $count = SocialComment::whereIn('id', $data['ids'])->delete();
        $this->audit('social_comment.bulk_delete', ['count' => $count]);

        return response()->json(['ok' => true, 'count' => $count]);
    }

    public function export(Request $request)
    {
        $format = strtolower((string) $request->query('format', 'csv'));
        if (!in_array($format, ['csv', 'xlsx'], true)) {
            abort(422, 'Invalid export format');
        }

        $query = $this->buildFilteredQuery($request)->orderByDesc('commented_at');

        $filename = 'social-comments-' . now()->format('Ymd-His') . '.' . $format;

        $this->audit('social_comment.export', ['format' => $format]);

        return response()->streamDownload(function () use ($format, $query) {
            if ($format === 'csv') {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['id', 'platform', 'author_name', 'author_id', 'message', 'status', 'commented_at', 'replied_at']);

                $query->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $c) {
                        fputcsv($out, [
                            $c->id,
                            $c->platform,
                            $c->author_name,
                            $c->author_id,
                            $c->message,
                            $c->status,
                            optional($c->commented_at)->toDateTimeString(),
                            optional($c->replied_at)->toDateTimeString(),
                        ]);
                    }
                });
                fclose($out);
                return;
            }

            $writer = WriterEntityFactory::createXLSXWriter();
            $writer->openToFile('php://output');
            $writer->addRow(WriterEntityFactory::createRowFromArray([
                'id', 'platform', 'author_name', 'author_id', 'message', 'status', 'commented_at', 'replied_at',
            ]));

            $query->chunk(500, function ($rows) use ($writer) {
                foreach ($rows as $c) {
                    $writer->addRow(WriterEntityFactory::createRowFromArray([
                        $c->id,
                        $c->platform,
                        $c->author_name,
                        $c->author_id,
                        $c->message,
                        $c->status,
                        optional($c->commented_at)->toDateTimeString(),
                        optional($c->replied_at)->toDateTimeString(),
                    ]));
                }
            });

            $writer->close();
        }, $filename, [
            'Content-Type' => $format === 'csv'
                ? 'text/csv; charset=UTF-8'
                : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function blockUser(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $comment = SocialComment::findOrFail($id);
        $service = app(SocialCommentService::class);

        $result = $service->blockAuthorFromComment($comment, $data['reason'] ?? null, $request->user()?->id);
        $this->audit('social_comment.block_user', ['comment_id' => $id, 'platform' => $comment->platform]);

        return response()->json($result);
    }

    public function bulkBlockUsers(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids'    => ['required', 'array'],
            'ids.*'  => ['integer'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $service = app(SocialCommentService::class);
        $blocked = 0;

        $comments = SocialComment::whereIn('id', $data['ids'])->get();
        foreach ($comments as $comment) {
            $res = $service->blockAuthorFromComment($comment, $data['reason'] ?? null, $request->user()?->id);
            if (!empty($res['success'])) {
                $blocked++;
            }
        }

        $this->audit('social_comment.bulk_block_user', ['count' => $blocked]);

        return response()->json(['ok' => true, 'count' => $blocked]);
    }

    public function replies(int $id): JsonResponse
    {
        $comment = SocialComment::findOrFail($id);

        $replies = SocialComment::where('parent_comment_id', $comment->platform_comment_id)
            ->orderBy('commented_at')
            ->get()
            ->map(fn (SocialComment $r) => [
                'id'           => $r->id,
                'author_name'  => $r->author_name ?? 'Unknown',
                'platform'     => $r->platform,
                'message'      => $r->message,
                'sentiment'    => $r->sentiment,
                'status'       => $r->status,
                'commented_at' => $r->commented_at?->diffForHumans(),
            ]);

        return response()->json(['data' => $replies]);
    }

    public function listQuickReplies(Request $request): JsonResponse
    {
        $platform = $request->query('platform');
        $q = SocialQuickReply::query()->orderByDesc('created_at');
        if ($platform && $platform !== 'all') {
            $q->where(function ($x) use ($platform) {
                $x->whereNull('platform')->orWhere('platform', $platform);
            });
        }

        return response()->json([
            'data' => $q->limit(200)->get(['id', 'title', 'body', 'platform']),
        ]);
    }

    public function storeQuickReply(Request $request): JsonResponse
    {
        $data = $request->validate([
            'platform' => ['nullable', 'in:facebook,instagram'],
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $qr = SocialQuickReply::create([
            'user_id' => $request->user()?->id,
            'platform' => $data['platform'] ?? null,
            'title' => $data['title'],
            'body' => $data['body'],
        ]);

        $this->audit('social_comment.quick_reply.create', ['id' => $qr->id]);

        return response()->json(['ok' => true, 'id' => $qr->id]);
    }

    public function updateQuickReply(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'platform' => ['nullable', 'in:facebook,instagram'],
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $qr = SocialQuickReply::findOrFail($id);
        $qr->update($data);
        $this->audit('social_comment.quick_reply.update', ['id' => $id]);

        return response()->json(['ok' => true]);
    }

    public function deleteQuickReply(int $id): JsonResponse
    {
        $qr = SocialQuickReply::findOrFail($id);
        $qr->delete();
        $this->audit('social_comment.quick_reply.delete', ['id' => $id]);

        return response()->json(['ok' => true]);
    }

    public function listAutoReplyRules(int $facebookPostId): JsonResponse
    {
        $rules = SocialAutoReplyRule::where('facebook_post_id', $facebookPostId)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $rules->map(fn (SocialAutoReplyRule $r) => [
                'id' => $r->id,
                'facebook_post_id' => $r->facebook_post_id,
                'match_type' => $r->match_type,
                'match_value' => $r->match_value,
                'use_ai' => (bool) $r->use_ai,
                'reply_template' => $r->reply_template,
                'enabled' => (bool) $r->enabled,
                'max_replies_per_author_per_day' => (int) $r->max_replies_per_author_per_day,
                'created_at' => $r->created_at?->diffForHumans(),
            ]),
        ]);
    }

    public function storeAutoReplyRule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'facebook_post_id' => ['required', 'exists:facebook_posts,id'],
            'match_type' => ['required', 'in:contains,keywords,regex'],
            'match_value' => ['required', 'string', 'max:5000'],
            'reply_template' => ['required', 'string', 'max:5000'],
            'use_ai' => ['required', 'boolean'],
            'enabled' => ['required', 'boolean'],
            'max_replies_per_author_per_day' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        FacebookPost::findOrFail($data['facebook_post_id']);

        $rule = SocialAutoReplyRule::create([
            'facebook_post_id' => $data['facebook_post_id'],
            'user_id' => $request->user()?->id,
            'match_type' => $data['match_type'],
            'match_value' => $data['match_value'],
            'reply_template' => $data['reply_template'],
            'use_ai' => (bool) $data['use_ai'],
            'enabled' => (bool) $data['enabled'],
            'max_replies_per_author_per_day' => (int) $data['max_replies_per_author_per_day'],
        ]);

        $this->audit('social_comment.auto_reply_rule.create', ['id' => $rule->id, 'facebook_post_id' => $rule->facebook_post_id]);

        return response()->json(['ok' => true, 'id' => $rule->id]);
    }

    public function updateAutoReplyRule(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'match_type' => ['required', 'in:contains,keywords,regex'],
            'match_value' => ['required', 'string', 'max:5000'],
            'reply_template' => ['required', 'string', 'max:5000'],
            'use_ai' => ['required', 'boolean'],
            'enabled' => ['required', 'boolean'],
            'max_replies_per_author_per_day' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $rule = SocialAutoReplyRule::findOrFail($id);
        $rule->update($data);
        $this->audit('social_comment.auto_reply_rule.update', ['id' => $id]);

        return response()->json(['ok' => true]);
    }

    public function deleteAutoReplyRule(int $id): JsonResponse
    {
        $rule = SocialAutoReplyRule::findOrFail($id);
        $rule->delete();
        $this->audit('social_comment.auto_reply_rule.delete', ['id' => $id]);

        return response()->json(['ok' => true]);
    }

    private function buildFilteredQuery(Request $request)
    {
        $query = SocialComment::query();

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('platform') && $request->query('platform') !== 'all') {
            $query->where('platform', $request->query('platform'));
        }

        if ($request->filled('sentiment') && $request->query('sentiment') !== 'all') {
            $query->where('sentiment', $request->query('sentiment'));
        }

        if ($request->filled('search')) {
            $term = trim($request->query('search'));
            $query->where(function ($q) use ($term) {
                $q->where('author_name', 'like', "%{$term}%")
                  ->orWhere('message', 'like', "%{$term}%");
            });
        }

        if ($request->filled('date_from')) {
            try {
                $from = \Carbon\Carbon::parse($request->query('date_from'))->startOfDay();
                $query->where('commented_at', '>=', $from);
            } catch (\Throwable) {
            }
        }

        if ($request->filled('date_to')) {
            try {
                $to = \Carbon\Carbon::parse($request->query('date_to'))->endOfDay();
                $query->where('commented_at', '<=', $to);
            } catch (\Throwable) {
            }
        }

        return $query;
    }
}
