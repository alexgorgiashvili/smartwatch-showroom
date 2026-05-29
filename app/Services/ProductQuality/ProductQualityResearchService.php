<?php

namespace App\Services\ProductQuality;

use App\Jobs\RunProductQualityAnalysisJob;
use App\Models\Product;
use App\Models\ProductQualityAnalysis;
use App\Models\ResearchTarget;
use Throwable;

class ProductQualityResearchService
{
    public function __construct(
        private readonly ProductQualityIdentityService $identityService,
        private readonly ProductQualityEvidenceIngestionService $ingestionService,
        private readonly ProductQualityAnalysisService $analysisService,
    ) {
    }

    public function upsertTarget(array $data): ResearchTarget
    {
        if (($data['mode'] ?? null) === 'catalog') {
            $product = Product::query()->findOrFail((int) $data['product_id']);
            $identity = $this->identityService->buildForCatalogProduct($product, $data);
            $target = ResearchTarget::query()->firstOrNew([
                'product_id' => $product->id,
                'mode' => 'catalog',
            ]);
        } else {
            $identity = $this->identityService->buildForAdHoc($data);
            $target = new ResearchTarget();
        }

        $target->fill($identity);
        $target->save();

        return $target->fresh(['product', 'latestAnalysis']);
    }

    public function queueAnalysis(ResearchTarget $target): ProductQualityAnalysis
    {
        $analysis = ProductQualityAnalysis::query()->create([
            'research_target_id' => $target->id,
            'product_id' => $target->product_id,
            'status' => 'queued',
        ]);

        RunProductQualityAnalysisJob::dispatch($analysis->id);

        return $analysis;
    }

    public function runNow(ResearchTarget $target): ProductQualityAnalysis
    {
        $analysis = ProductQualityAnalysis::query()->create([
            'research_target_id' => $target->id,
            'product_id' => $target->product_id,
            'status' => 'queued',
        ]);

        return $this->executeAnalysis($analysis->id);
    }

    public function executeAnalysis(int $analysisId): ProductQualityAnalysis
    {
        /** @var ProductQualityAnalysis $analysis */
        $analysis = ProductQualityAnalysis::query()
            ->with(['researchTarget.product'])
            ->findOrFail($analysisId);

        $analysis->update([
            'status' => 'running',
            'error_message' => null,
        ]);

        try {
            $target = $analysis->researchTarget;
            $this->ingestionService->ingest($target);
            $result = $this->analysisService->analyze($target);

            $analysis->update([
                'status' => 'completed',
                'model_used' => $result['model_used'],
                'evidence_count' => $result['evidence_count'],
                'end_user_evidence_count' => $result['end_user_evidence_count'],
                'supplier_evidence_count' => $result['supplier_evidence_count'],
                'confidence_score' => $result['confidence_score'],
                'verdict' => $result['verdict'],
                'summary_json' => $result['summary_json'],
                'comparison_ready_payload' => $result['comparison_ready_payload'],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $analysis->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);
        }

        return $analysis->fresh(['researchTarget.product']);
    }
}
