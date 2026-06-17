<?php

namespace App\Services\Chatbot;

use App\Jobs\RunChatbotLabRunJob;
use App\Models\ChatbotTestResult;
use App\Models\ChatbotTestRun;
use App\Models\ChatbotTrainingCase;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatbotLabRunService
{
    public function __construct(
        private TestRunnerService $runner
    ) {
    }

    public function selectableCases(): Collection
    {
        return ChatbotTrainingCase::where('is_active', true)
            ->orderBy('title')
            ->orderBy('prompt')
            ->get();
    }

    public function runsReady(): bool
    {
        return true;
    }

    public function casesReady(): bool
    {
        return true;
    }

    public function queueStatus(): array
    {
        return [
            'can_dispatch' => true,
            'background_capable' => true,
            'driver' => config('queue.default'),
            'message' => 'Queue is ready',
        ];
    }

    public function observabilitySummary(): array
    {
        return [
            'recent_errors' => 0,
            'queue_depth' => 0,
        ];
    }

    public function queueRun(array $caseIds, bool $useLlmJudge = false): ChatbotTestRun
    {
        $run = $this->createRun($caseIds, $useLlmJudge, 'pending');

        RunChatbotLabRunJob::dispatch($run->id);

        return $run;
    }

    public function startRun(array $caseIds = [], bool $useLlmJudge = false): ChatbotTestRun
    {
        $run = $this->createRun($caseIds, $useLlmJudge, 'pending');

        return $this->executeQueuedRun($run->id);
    }

    public function executeQueuedRun(int $runId): ChatbotTestRun
    {
        $run = ChatbotTestRun::query()->findOrFail($runId);

        if ($run->isTerminal()) {
            return $run;
        }

        $run->update([
            'status' => 'running',
            'started_at' => $run->started_at ?? now(),
        ]);

        $filters = is_array($run->filters) ? $run->filters : [];
        $caseIds = collect($filters['case_ids'] ?? [])
            ->filter(fn ($value): bool => is_scalar($value) && (string) $value !== '')
            ->map(fn ($value): string => (string) $value)
            ->values()
            ->all();
        $useLlmJudge = (bool) ($filters['use_llm_judge'] ?? false);

        $cases = $this->casesForRun($caseIds);

        foreach ($cases as $case) {
            $run->refresh();

            if ($run->isTerminal()) {
                return $run;
            }

            $this->runner->executeCase($this->buildCasePayload($case), $run->id, [
                'use_llm_judge' => $useLlmJudge,
            ]);
        }

        $run->refresh();

        if (!$run->isTerminal()) {
            $this->runner->finalizeRun($run->id);
        }

        return $run->fresh();
    }

    public function labRunDetail(int $runId): ChatbotTestRun
    {
        return ChatbotTestRun::with(['results.trainingCase'])->findOrFail($runId);
    }

    public function statusSnapshot(ChatbotTestRun $run): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status,
            'started_at' => $run->started_at,
            'completed_at' => $run->completed_at,
            'total' => $run->results->count(),
            'passed' => $run->results->where('status', 'pass')->count(),
            'failed' => $run->results->where('status', 'fail')->count(),
            'pending' => $run->results->where('status', 'pending')->count(),
        ];
    }

    public function filteredResults(ChatbotTestRun $run, array $filters = [])
    {
        $query = $run->results()->with('trainingCase');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $query->where('question', 'like', '%' . $filters['search'] . '%');
        }

        return $query->paginate(20);
    }

    public function summarizeResultSignal(ChatbotTestResult $result): array
    {
        return [
            'has_issues' => $result->status === 'failed',
            'quality_score' => $result->llm_overall ?? 0,
        ];
    }

    public function runObservabilitySnapshot(ChatbotTestRun $run): array
    {
        return [
            'avg_response_time' => $run->results->avg('response_time_ms') ?? 0,
            'errors_count' => $run->results->where('status', 'failed')->count(),
        ];
    }

    public function cancelRun(ChatbotTestRun $run): void
    {
        $run->update(['status' => 'cancelled']);
    }

    /**
     * @param array<int, string|int> $caseIds
     */
    private function createRun(array $caseIds, bool $useLlmJudge, string $status): ChatbotTestRun
    {
        return DB::transaction(function () use ($caseIds, $useLlmJudge, $status): ChatbotTestRun {
            $run = ChatbotTestRun::create([
                'status' => $status,
                'triggered_by' => 'chatbot_lab',
                'filters' => [
                    'lab' => true,
                    'case_ids' => collect($caseIds)
                        ->filter(fn ($value): bool => is_scalar($value) && (string) $value !== '')
                        ->map(fn ($value): string => (string) $value)
                        ->values()
                        ->all(),
                    'use_llm_judge' => $useLlmJudge,
                ],
                'started_at' => $status === 'running' ? now() : null,
            ]);

            return $run;
        });
    }

    /**
     * @param array<int, string> $caseIds
     * @return EloquentCollection<int, ChatbotTrainingCase>
     */
    private function casesForRun(array $caseIds): EloquentCollection
    {
        $query = ChatbotTrainingCase::query()->orderBy('id');

        if ($caseIds !== []) {
            $query->whereIn('id', $caseIds);
        } else {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCasePayload(ChatbotTrainingCase $case): array
    {
        $context = is_array($case->conversation_context_json ?? null)
            ? $case->conversation_context_json
            : [];

        $messages = collect($context)
            ->filter(fn ($line): bool => is_string($line) && trim($line) !== '')
            ->map(fn (string $line): array => [
                'role' => 'user',
                'content' => $line,
            ])
            ->values()
            ->all();

        $question = trim((string) $case->prompt);
        $productSlugs = is_array($case->expected_product_slugs_json ?? null)
            ? $case->expected_product_slugs_json
            : [];

        if ($messages !== []) {
            $messages[] = [
                'role' => 'user',
                'content' => $question,
            ];
        }

        return [
            'id' => 'training-case-' . $case->id,
            'category' => (string) ($case->expected_intent ?: $case->source ?: 'training_case'),
            'question' => $question,
            'messages' => $messages !== [] ? $messages : null,
            'expected' => [
                'must_contain_any' => is_array($case->expected_keywords_json ?? null) ? $case->expected_keywords_json : [],
                'must_not_contain' => [],
                'product_slug' => $productSlugs[0] ?? null,
                'expected_price' => null,
                'price_tolerance_pct' => null,
                'stock_claim' => $case->expected_stock_behavior,
                'guardrail_should_pass' => true,
                'georgian_only' => true,
                'min_relevance_score' => null,
                'llm_judge_criteria' => (string) ($case->reviewer_notes ?: 'Training case execution.'),
                'context_preserved' => true,
                'final_must_contain_any' => [],
            ],
        ];
    }
}
