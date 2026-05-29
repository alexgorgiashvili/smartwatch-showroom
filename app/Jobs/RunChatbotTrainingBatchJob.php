<?php

namespace App\Jobs;

use App\Services\Chatbot\ChatbotTrainingRunnerService;
use App\Services\Chatbot\ChatbotTrainingStoreService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunChatbotTrainingBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        public string $runId,
        public string $batchId
    ) {}

    public function handle(
        ChatbotTrainingStoreService $store,
        ChatbotTrainingRunnerService $runner
    ): void {
        $batch = $store->getBatch($this->batchId);

        if ($batch === null) {
            $store->failRun($this->runId, 'Batch ვერ მოიძებნა queued job-ის შესრულებისას.');

            return;
        }

        $store->markRunStarted($this->runId);

        try {
            $executedRun = $runner->runBatch($batch);
            $store->completeRun($this->runId, $executedRun);
        } catch (\Throwable $exception) {
            $store->failRun($this->runId, $exception->getMessage());

            throw $exception;
        }
    }
}
