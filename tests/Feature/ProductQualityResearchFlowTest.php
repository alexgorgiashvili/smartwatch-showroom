<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductQualityAnalysis;
use App\Models\ResearchTarget;
use App\Models\User;
use App\Services\Chatbot\ModelCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ProductQualityResearchFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'is_admin' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testAdminCanCreateCatalogResearchAndItCompletesUsingStoredManualEvidence(): void
    {
        $product = Product::query()->create([
            'name_en' => 'Wonlex KT20',
            'name_ka' => 'Wonlex KT20',
            'slug' => 'wonlex-kt20',
            'external_source' => 'alibaba',
            'external_source_url' => 'https://www.alibaba.com/product-detail/Wonlex-KT20_1600123456789.html',
            'external_product_id' => '1600123456789',
            'brand' => 'Wonlex',
            'model' => 'KT20',
            'price' => 199,
            'currency' => 'GEL',
            'is_active' => true,
            'featured' => false,
        ]);

        $modelCompletion = Mockery::mock(ModelCompletionService::class);
        $modelCompletion->shouldReceive('complete')
            ->once()
            ->andReturn([
                'reply' => '',
                'reason' => 'chatbot_disabled',
                'usage' => [],
            ]);
        $this->instance(ModelCompletionService::class, $modelCompletion);

        $response = $this->actingAs($this->admin)->post(route('admin.product-quality.store'), [
            'mode' => 'catalog',
            'product_id' => $product->id,
            'manual_evidence_input' => json_encode([
                [
                    'source_type' => 'marketplace_review',
                    'author_type' => 'end_user',
                    'rating_raw' => 4,
                    'title' => 'Strong GPS',
                    'body_text' => 'GPS is accurate and battery lasts through the school day.',
                ],
                [
                    'source_type' => 'marketplace_review',
                    'author_type' => 'end_user',
                    'rating_raw' => 2,
                    'title' => 'Setup pain',
                    'body_text' => 'App setup is confusing and call quality is inconsistent.',
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $target = ResearchTarget::query()->firstOrFail();
        $analysis = ProductQualityAnalysis::query()->firstOrFail();

        $response->assertRedirect(route('admin.product-quality.show', $target));
        $this->assertSame($product->id, $target->product_id);
        $this->assertSame('completed', $analysis->status);
        $this->assertSame(2, $analysis->evidence_count);
        $this->assertNotEmpty($analysis->summary_json);
        $this->assertDatabaseCount('product_evidence_items', 2);
    }

    public function testAdHocResearchDoesNotCreateCatalogProduct(): void
    {
        $modelCompletion = Mockery::mock(ModelCompletionService::class);
        $modelCompletion->shouldReceive('complete')
            ->once()
            ->andReturn([
                'reply' => '',
                'reason' => 'chatbot_disabled',
                'usage' => [],
            ]);
        $this->instance(ModelCompletionService::class, $modelCompletion);

        $response = $this->actingAs($this->admin)->post(route('admin.product-quality.store'), [
            'mode' => 'ad_hoc',
            'name' => 'YQT Q12',
            'brand' => 'YQT',
            'model' => 'Q12',
            'manual_evidence_input' => "Battery is good.\n\nGPS is occasionally inaccurate.",
        ]);

        $target = ResearchTarget::query()->firstOrFail();

        $response->assertRedirect(route('admin.product-quality.show', $target));
        $this->assertNull($target->product_id);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseHas('product_quality_analyses', [
            'research_target_id' => $target->id,
            'status' => 'completed',
        ]);
    }

    public function testShowPageReturnsPjaxFragment(): void
    {
        $target = ResearchTarget::query()->create([
            'mode' => 'ad_hoc',
            'name' => 'PJAX Test Target',
        ]);

        ProductQualityAnalysis::query()->create([
            'research_target_id' => $target->id,
            'status' => 'completed',
            'evidence_count' => 0,
            'end_user_evidence_count' => 0,
            'supplier_evidence_count' => 0,
            'confidence_score' => 10,
            'verdict' => 'avoid_or_test_more',
            'summary_json' => [
                'strengths' => [],
                'weaknesses' => [],
                'recurring_praise' => [],
                'recurring_complaints' => [],
                'risk_flags' => [],
                'evidence_gaps' => ['No public evidence was ingested yet.'],
                'verdict_rationale' => 'Insufficient evidence.',
                'rubric' => [],
            ],
            'comparison_ready_payload' => [
                'display_name' => 'PJAX Test Target',
                'evidence_count' => 0,
                'confidence_score' => 10,
                'rubric' => [],
            ],
        ]);

        $response = $this->actingAs($this->admin)
            ->withHeader('X-PJAX', '1')
            ->get(route('admin.product-quality.show', $target));

        $response->assertOk();
        $response->assertSee('PJAX Test Target');
        $response->assertDontSee('<html', false);
    }
}
