<?php

namespace App\Console\Commands;

use App\Models\ResearchTarget;
use App\Services\ProductQuality\ProductQualityResearchService;
use Illuminate\Console\Command;

class RunProductQualityAnalysisCommand extends Command
{
    protected $signature = 'product-quality:run
        {--target= : Existing research target ID}
        {--product= : Catalog product ID}
        {--source-url= : Public source URL}
        {--external-source= : Source name such as alibaba}
        {--external-product-id= : External product identifier}
        {--brand= : Product brand}
        {--model= : Product model}
        {--name= : Product name}
        {--manual-evidence= : Manual evidence JSON or text blocks}
        {--apify-json= : Raw Apify payload JSON}';

    protected $description = 'Run Product Quality Intelligence analysis for an existing target or a new catalog/ad-hoc target.';

    public function handle(ProductQualityResearchService $researchService): int
    {
        $targetOption = $this->option('target');

        if ($targetOption) {
            /** @var ResearchTarget $target */
            $target = ResearchTarget::query()->findOrFail((int) $targetOption);
        } else {
            $productId = $this->option('product');
            $target = $researchService->upsertTarget([
                'mode' => $productId ? 'catalog' : 'ad_hoc',
                'product_id' => $productId ? (int) $productId : null,
                'source_url' => $this->option('source-url'),
                'external_source' => $this->option('external-source'),
                'external_product_id' => $this->option('external-product-id'),
                'brand' => $this->option('brand'),
                'model' => $this->option('model'),
                'name' => $this->option('name'),
                'manual_evidence_input' => $this->option('manual-evidence'),
                'apify_json' => $this->option('apify-json'),
            ]);
        }

        $analysis = $researchService->runNow($target);

        $this->line('Target ID: ' . $analysis->research_target_id);
        $this->line('Analysis ID: ' . $analysis->id);
        $this->line('Status: ' . $analysis->status);
        $this->line('Verdict: ' . ($analysis->verdict ?? 'n/a'));
        $this->line('Confidence: ' . ($analysis->confidence_score ?? 'n/a'));

        if ($analysis->error_message) {
            $this->error($analysis->error_message);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
