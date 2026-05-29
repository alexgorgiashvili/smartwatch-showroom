<?php

namespace App\Jobs;

use App\Services\ProductQuality\ProductQualityResearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunProductQualityAnalysisJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $analysisId
    ) {
    }

    public function handle(ProductQualityResearchService $researchService): void
    {
        $researchService->executeAnalysis($this->analysisId);
    }
}
