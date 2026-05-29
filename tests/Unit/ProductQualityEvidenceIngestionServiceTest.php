<?php

namespace Tests\Unit;

use App\Models\ResearchTarget;
use App\Services\ProductQuality\ProductQualityEvidenceIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductQualityEvidenceIngestionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function testManualEvidenceIngestionDedupesAcrossRunsAndPreservesAuthorTypes(): void
    {
        $target = ResearchTarget::query()->create([
            'mode' => 'ad_hoc',
            'name' => 'Wonlex KT20',
            'brand' => 'Wonlex',
            'model' => 'KT20',
            'identity_payload' => [
                'research_input' => [
                    'manual_evidence_input' => json_encode([
                        [
                            'source_type' => 'marketplace_review',
                            'author_type' => 'end_user',
                            'title' => 'Good battery',
                            'body_text' => 'Battery life is good and GPS is accurate for daily school use.',
                        ],
                        [
                            'source_type' => 'alibaba_supplier_review',
                            'author_type' => 'supplier',
                            'title' => 'Supplier note',
                            'body_text' => 'Customers usually praise the easy setup and stable calls.',
                        ],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
            ],
        ]);

        $service = app(ProductQualityEvidenceIngestionService::class);

        $firstRun = $service->ingest($target);
        $secondRun = $service->ingest($target->fresh());

        $this->assertSame(2, $firstRun['stored_count']);
        $this->assertSame(2, $secondRun['duplicate_count']);
        $this->assertDatabaseCount('product_evidence_items', 2);
        $this->assertDatabaseHas('product_evidence_items', [
            'research_target_id' => $target->id,
            'author_type' => 'end_user',
            'source_type' => 'marketplace_review',
        ]);
        $this->assertDatabaseHas('product_evidence_items', [
            'research_target_id' => $target->id,
            'author_type' => 'supplier',
            'source_type' => 'alibaba_supplier_review',
        ]);
    }

    public function testApifyProductPayloadFallsBackToSupplierEvidenceWhenNoReviewsExist(): void
    {
        $target = ResearchTarget::query()->create([
            'mode' => 'ad_hoc',
            'name' => 'Wonlex KT20',
            'brand' => 'Wonlex',
            'model' => 'KT20',
            'source_url' => 'https://www.alibaba.com/product-detail/WONLEX-KT20-4G-Waterproof-Smart-Watch_1601200344089.html',
            'external_source' => 'alibaba',
            'identity_payload' => [
                'research_input' => [
                    'apify_json' => json_encode([
                        'item' => [
                            'url' => 'https://www.alibaba.com/product-detail/WONLEX-KT20-4G-Waterproof-Smart-Watch_1601200344089.html',
                            'title' => 'Wonlex KT20 4G Waterproof Smart Watch',
                            'description' => 'IP67 kids smartwatch with GPS, 4G connectivity, and video calling.',
                            'rating' => 4.5,
                            'reviewCount' => 12,
                            'specifications' => [
                                'Waterproof' => 'IP67',
                                'Network' => '4G',
                                'Battery' => '900mAh',
                                'function' => 'GPS tracker, SOS button, Video call',
                            ],
                        ],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
            ],
        ]);

        $service = app(ProductQualityEvidenceIngestionService::class);
        $result = $service->ingest($target);

        $this->assertSame(3, $result['stored_count']);
        $this->assertSame(3, $result['evidence_count']);
        $this->assertSame(3, $result['supplier_evidence_count']);
        $this->assertDatabaseHas('product_evidence_items', [
            'research_target_id' => $target->id,
            'author_type' => 'supplier',
            'source_type' => 'supplier_listing',
        ]);
        $this->assertDatabaseHas('product_evidence_items', [
            'research_target_id' => $target->id,
            'author_type' => 'supplier',
            'source_type' => 'supplier_spec_sheet',
        ]);
    }
}
