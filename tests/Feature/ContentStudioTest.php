<?php

namespace Tests\Feature;

use App\Models\ContentCampaign;
use App\Models\ContentItem;
use App\Models\ContentRevision;
use App\Models\User;
use App\Services\ContentStudioWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContentStudioTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return ['title_ka' => 'ქართული სათაური', 'title_en' => 'English title', 'excerpt_ka' => 'ქართული მოკლე ტექსტი', 'excerpt_en' => 'English excerpt', 'body_ka' => 'ქართული სრული ტექსტი', 'body_en' => 'English full text', 'meta_title_ka' => 'ქართული meta', 'meta_title_en' => 'English meta', 'meta_description_ka' => 'ქართული description', 'meta_description_en' => 'English description'];
    }

    public function test_agent_token_can_submit_but_cannot_access_owner_actions(): void
    {
        $agent = User::factory()->create();
        Sanctum::actingAs($agent, ['content-studio:submit']);
        $campaign = $this->postJson('/api/content-studio/campaigns', ['name' => 'Weekly', 'week_key' => '2026-W38', 'idempotency_key' => 'campaign-1'])->assertCreated()->json('campaign');
        $item = $this->postJson('/api/content-studio/campaigns/'.$campaign['id'].'/items', ['channel' => 'article', 'idempotency_key' => 'article-1'])->assertCreated()->json('item');
        $this->postJson('/api/content-studio/items/'.$item['id'].'/revisions', ['payload' => $this->payload(), 'evidence' => $this->evidence()])->assertCreated();
        $this->post('/admin/content-studio/'.$item['id'].'/approve-now')->assertRedirect(route('admin.login'));
    }

    public function test_intake_idempotency_returns_one_campaign_and_revision(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['content-studio:submit']);
        $body = ['name' => 'Weekly', 'week_key' => '2026-W38', 'idempotency_key' => 'same'];
        $first = $this->postJson('/api/content-studio/campaigns', $body)->assertCreated()->json('campaign.id');
        $this->postJson('/api/content-studio/campaigns', $body)->assertOk()->assertJsonPath('campaign.id', $first)->assertJsonPath('idempotent', true);
        $this->assertDatabaseCount('content_campaigns', 1);
    }

    public function test_new_revision_invalidates_approval_and_revisions_cannot_be_mutated(): void
    {
        $owner = User::factory()->create(['is_admin' => true]);
        $campaign = ContentCampaign::create(['name' => 'Weekly', 'week_key' => '2026-W38', 'fingerprint' => hash('sha256', 'c')]);
        $item = ContentItem::create(['campaign_id' => $campaign->id, 'channel' => 'article', 'fingerprint' => hash('sha256', 'i'), 'status' => 'generated']);
        $workflow = app(ContentStudioWorkflow::class);
        $first = $workflow->submitRevision($item, $this->payload(), [], $owner);
        $workflow->approve($item->fresh(), $owner);
        $workflow->submitRevision($item->fresh(), array_merge($this->payload(), ['title_en' => 'Changed']), [], $owner);
        $this->assertDatabaseHas('content_items', ['id' => $item->id, 'status' => 'ready_for_owner', 'approved_revision_id' => null]);
        $this->expectException(\LogicException::class);
        $first->update(['revision_number' => 99]);
    }

    public function test_revision_validation_marks_separate_evidence_as_attached(): void
    {
        $owner = User::factory()->create(['is_admin' => true]);
        $campaign = ContentCampaign::create(['name' => 'Weekly', 'week_key' => '2026-W38', 'fingerprint' => hash('sha256', 'evidence-campaign')]);
        $item = ContentItem::create(['campaign_id' => $campaign->id, 'channel' => 'article', 'fingerprint' => hash('sha256', 'evidence-item'), 'status' => 'generated']);

        $revision = app(ContentStudioWorkflow::class)->submitRevision($item, $this->payload(), ['sources' => ['https://example.test/source']], $owner);

        $this->assertTrue($revision->validation['evidence_attached']);
    }

    public function test_agent_intake_requires_generation_provenance(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['content-studio:submit']);
        $campaign = ContentCampaign::create(['name' => 'Weekly', 'week_key' => '2026-W38', 'fingerprint' => hash('sha256', 'provenance-campaign')]);
        $item = ContentItem::create(['campaign_id' => $campaign->id, 'channel' => 'article', 'fingerprint' => hash('sha256', 'provenance-item'), 'status' => 'generated']);

        $this->postJson('/api/content-studio/items/'.$item->id.'/revisions', ['payload' => $this->payload(), 'evidence' => ['sources' => ['https://example.test/source']]])->assertUnprocessable()->assertJsonValidationErrors(['evidence.generation', 'evidence.claim_verification']);
    }

    public function test_instagram_requires_public_https_media(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['content-studio:submit']);
        $campaign = ContentCampaign::create(['name' => 'Weekly', 'week_key' => '2026-W38', 'fingerprint' => hash('sha256', 'c2')]);
        $item = ContentItem::create(['campaign_id' => $campaign->id, 'channel' => 'instagram', 'fingerprint' => hash('sha256', 'i2'), 'status' => 'generated']);
        $this->postJson('/api/content-studio/items/'.$item->id.'/revisions', ['payload' => ['message' => 'Caption', 'media_type' => 'image', 'media_url' => 'http://example.test/a.jpg']])->assertStatus(422);
    }

    public function test_owner_inbox_filters_and_shows_revision_diff(): void
    {
        $owner = User::factory()->create(['is_admin' => true]);
        $campaign = ContentCampaign::create(['name' => 'Weekly', 'week_key' => '2026-W38', 'fingerprint' => hash('sha256', 'c3')]);
        $item = ContentItem::create(['campaign_id' => $campaign->id, 'channel' => 'article', 'fingerprint' => hash('sha256', 'i3'), 'status' => 'generated']);
        $workflow = app(ContentStudioWorkflow::class);
        $workflow->submitRevision($item, $this->payload(), [], $owner);
        $workflow->submitRevision($item->fresh(), array_merge($this->payload(), ['title_en' => 'Changed']), [], $owner);
        $this->actingAs($owner)->get(route('admin.content-studio.index', ['status' => 'ready_for_owner', 'channel' => 'article']))->assertOk()->assertSee('Weekly');
        $this->actingAs($owner)->get(route('admin.content-studio.show', $item))->assertOk()->assertSee('ცვლილებების diff')->assertSee('Changed');
    }

    private function evidence(): array
    {
        return ['sources' => ['https://example.test/source'], 'generation' => ['provider' => 'chatgpt_browser', 'model' => 'ChatGPT browser', 'generated_at' => now()->toIso8601String()], 'claim_verification' => 'Verified against the supplied source.'];
    }
}
