<?php

namespace App\Jobs;

use App\Models\ChatbotTestRun;
use App\Services\Chatbot\TestRunnerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunTestSuiteJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param array<int, string> $categories
     */
    public function __construct(
        public int $runId,
        public array $categories = []
    ) {
    }

    public function handle(TestRunnerService $runner): void
    {
        $run = ChatbotTestRun::query()->findOrFail($this->runId);

        if ($run->isTerminal()) {
            return;
        }

        $run->update([
            'status' => 'running',
            'started_at' => $run->started_at ?? now(),
        ]);

        $dataset = $runner->loadDataset();

        if ($this->categories !== []) {
            $dataset = $dataset->filter(function (array $case): bool {
                return in_array((string) ($case['category'] ?? ''), $this->categories, true);
            })->values();
        }

        foreach ($dataset as $case) {
            $run->refresh();

            if ($run->isTerminal()) {
                return;
            }

            $runner->executeCase($case, $run->id);
        }

        $run->refresh();

        if (!$run->isTerminal()) {
            $runner->finalizeRun($run->id);
        }
    }
}
