<?php

namespace Tests\Feature;

use App\Models\FacebookPost;
use App\Models\SocialAutoReplyRule;
use App\Models\SocialComment;
use App\Models\User;
use App\Services\SocialCommentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialCommentsAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_social_comments_list_supports_date_filters(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $post = FacebookPost::create([
            'user_id' => $admin->id,
            'message' => 'Hello',
            'status' => 'published',
            'published_at' => now(),
        ]);

        SocialComment::create([
            'facebook_post_id' => $post->id,
            'platform' => 'facebook',
            'platform_comment_id' => 'c1',
            'author_name' => 'A',
            'author_id' => 'u1',
            'message' => 'Test',
            'status' => 'unread',
            'commented_at' => now()->subDays(5),
        ]);

        SocialComment::create([
            'facebook_post_id' => $post->id,
            'platform' => 'facebook',
            'platform_comment_id' => 'c2',
            'author_name' => 'B',
            'author_id' => 'u2',
            'message' => 'Test 2',
            'status' => 'unread',
            'commented_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.social-comments.list', [
                'date_from' => now()->subDays(2)->toDateString(),
                'date_to' => now()->toDateString(),
                'status' => 'all',
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_quick_replies_crud(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $create = $this->actingAs($admin)->postJson(route('admin.social-comments.quick-replies.store'), [
            'platform' => 'facebook',
            'title' => 'Hi',
            'body' => 'გამარჯობა!',
        ])->assertOk()->json();

        $id = $create['id'];

        $this->actingAs($admin)->putJson(route('admin.social-comments.quick-replies.update', ['id' => $id]), [
            'platform' => 'facebook',
            'title' => 'Hi2',
            'body' => 'გამარჯობა!!',
        ])->assertOk();

        $this->actingAs($admin)->getJson(route('admin.social-comments.quick-replies.list', ['platform' => 'facebook']))
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Hi2');

        $this->actingAs($admin)->deleteJson(route('admin.social-comments.quick-replies.delete', ['id' => $id]))
            ->assertOk();
    }

    public function test_auto_reply_rule_applies_on_import(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $post = FacebookPost::create([
            'user_id' => $admin->id,
            'message' => 'Post',
            'post_to_facebook' => true,
            'facebook_post_id' => 'p_123',
            'status' => 'published',
            'published_at' => now(),
        ]);

        SocialAutoReplyRule::create([
            'facebook_post_id' => $post->id,
            'user_id' => $admin->id,
            'match_type' => 'contains',
            'match_value' => 'hello',
            'use_ai' => false,
            'reply_template' => 'გამარჯობა!',
            'enabled' => true,
            'max_replies_per_author_per_day' => 3,
        ]);

        Http::fake([
            'graph.facebook.com/*/p_123/comments*' => Http::response([
                'data' => [
                    [
                        'id' => 'c_1',
                        'message' => 'hello there',
                        'from' => ['id' => 'u_1', 'name' => 'User 1'],
                        'created_time' => now()->toIso8601String(),
                    ],
                ],
                'paging' => [],
            ], 200),
            'graph.facebook.com/*/c_1/comments' => Http::response(['id' => 'r_1'], 200),
        ]);

        $service = app(SocialCommentService::class);
        $service->fetchAllRecentComments(24);

        $this->assertDatabaseHas('social_comments', [
            'platform_comment_id' => 'c_1',
            'status' => 'replied',
            'actual_reply' => 'გამარჯობა!',
        ]);
    }

    public function test_export_csv_works(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $post = FacebookPost::create([
            'user_id' => $admin->id,
            'message' => 'Hello',
            'status' => 'published',
            'published_at' => now(),
        ]);

        SocialComment::create([
            'facebook_post_id' => $post->id,
            'platform' => 'facebook',
            'platform_comment_id' => 'c1',
            'author_name' => 'A',
            'author_id' => 'u1',
            'message' => 'Test',
            'status' => 'unread',
            'commented_at' => now(),
        ]);

        $res = $this->actingAs($admin)->get(route('admin.social-comments.export', ['format' => 'csv']));
        $res->assertOk();
        $this->assertStringContainsString('text/csv', $res->headers->get('content-type'));
    }
}

