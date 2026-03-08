<?php

namespace App\Services\Chatbot;

use App\Jobs\RunChatbotLabRunJob;
use App\Models\ChatbotTestResult;
use App\Models\ChatbotTestRun;
use App\Models\ChatbotTrainingCase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ChatbotLabRunService
{
    public function __construct(
        private TestRunnerService $testRunner,
        private ChatbotQualityMetricsService $metrics
    ) {
    }

    public function casesReady(): bool
    {
        return Schema::hasTable('chatbot_training_cases');
    }

    public function runsReady(): bool
    {
        return Schema::hasTable('chatbot_test_runs') && Schema::hasTable('chatbot_test_results');
    }

    /**
     * @return array{driver:string,jobs_table_required:bool,jobs_table_ready:bool,background_capable:bool,can_dispatch:bool,message:string}
     */
    public function queueStatus(): array
    {
        $driver = (string) config('queue.default', 'sync');
        $jobsTableRequired = $driver === 'database';
        $jobsTableReady = !$jobsTableRequired || Schema::hasTable('jobs');
        $backgroundCapable = $driver !== 'sync' && $jobsTableReady;
        $canDispatch = $driver === 'sync' || $jobsTableReady;

        if ($driver === 'sync') {
            $message = 'The active queue driver is sync. Evaluation runs still work, but they execute inline and do not continue in the background.';
        } elseif (!$jobsTableReady) {
            $message = 'The active queue driver is database, but the jobs table is missing. Run migrations before starting evaluation runs.';
        } else {
            $message = 'Background queue execution is ready for Chatbot Lab runs.';
        }

        return [
            'driver' => $driver,
            'jobs_table_required' => $jobsTableRequired,
            'jobs_table_ready' => $jobsTableReady,
            'background_capable' => $backgroundCapable,
            'can_dispatch' => $canDispatch,
            'message' => $message,
        ];
    }

    /**
     * @return Collection<int, ChatbotTrainingCase>
     */
    public function selectableCases(): Collection
    {
        if (!$this->casesReady()) {
            return collect();
        }

        return ChatbotTrainingCase::query()
            ->active()
            ->latest('id')
            ->get();
    }

    public function recentRuns(int $limit = 10): Collection
    {
        if (!$this->runsReady()) {
            return collect();
        }

        return ChatbotTestRun::query()
            ->where('triggered_by', 'chatbot_lab')
            ->latest('id')
            ->take($limit)
            ->get();
    }

    public function startRun(array $caseIds, bool $useLlmJudge = false): ChatbotTestRun
    {
        $selectedCases = $this->selectedCases($caseIds);
        $run = $this->createRunRecord($selectedCases, $useLlmJudge, 'running');
        $this->markRunRunning($run, $selectedCases->count(), now());

        try {
            foreach ($selectedCases as $trainingCase) {
                $this->testRunner->executeCase(
                    $this->mapTrainingCaseToRunnerCase($trainingCase),
                    $run->id,
                    ['use_llm_judge' => $useLlmJudge]
                );
            }

            $this->testRunner->finalizeRun($run->id);

            return $run->fresh();
        } catch (\Throwable $exception) {
            $this->markRunFailed($run, $exception);

            throw $exception;
        }
    }

    public function queueRun(array $caseIds, bool $useLlmJudge = false): ChatbotTestRun
    {
        $queueStatus = $this->queueStatus();
        if (!$queueStatus['can_dispatch']) {
            throw new \RuntimeException($queueStatus['message']);
        }

        $selectedCases = $this->selectedCases($caseIds);
        $run = $this->createRunRecord($selectedCases, $useLlmJudge, 'pending');

        RunChatbotLabRunJob::dispatch($run->id);

        return $run;
    }

    public function executeQueuedRun(int $runId): ChatbotTestRun
    {
        $run = ChatbotTestRun::query()
            ->where('triggered_by', 'chatbot_lab')
            ->findOrFail($runId);

        if ($run->isTerminal()) {
            return $run;
        }

        $caseIds = collect($run->filters['case_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $useLlmJudge = (bool) ($run->filters['use_llm_judge'] ?? false);
        $selectedCases = ChatbotTrainingCase::query()
            ->whereIn('id', $caseIds)
            ->orderBy('id')
            ->get();

        if ($run->fresh()->isCancelled()) {
            return $run->fresh();
        }

        $this->markRunRunning($run, $selectedCases->count(), $run->started_at ?? now());

        try {
            foreach ($selectedCases as $trainingCase) {
                if ($run->fresh()->isCancelled()) {
                    return $run->fresh();
                }

                $this->testRunner->executeCase(
                    $this->mapTrainingCaseToRunnerCase($trainingCase),
                    $run->id,
                    ['use_llm_judge' => $useLlmJudge]
                );
            }

            if ($run->fresh()->isCancelled()) {
                return $run->fresh();
            }

            $this->testRunner->finalizeRun($run->id);

            return $run->fresh();
        } catch (\Throwable $exception) {
            $this->markRunFailed($run, $exception);

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function statusSnapshot(ChatbotTestRun|int $run): array
    {
        $runModel = $run instanceof ChatbotTestRun
            ? $run->fresh()
            : ChatbotTestRun::query()
                ->where('triggered_by', 'chatbot_lab')
                ->findOrFail($run);

        $totalCases = (int) ($runModel->total_cases ?? count($runModel->filters['case_ids'] ?? []));
        $processedCases = (int) $runModel->results()->count();
        $passedCases = (int) $runModel->results()->where('status', 'pass')->count();
        $failedCases = (int) $runModel->results()->where('status', 'fail')->count();
        $skippedCases = (int) $runModel->results()->where('status', 'skip')->count();
        $remainingCases = max($totalCases - $processedCases, 0);
        $percentComplete = $totalCases > 0
            ? round(min(($processedCases / $totalCases) * 100, 100), 2)
            : 0.0;

        return [
            'id' => $runModel->id,
            'status' => (string) $runModel->status,
            'is_terminal' => $runModel->isTerminal(),
            'can_cancel' => !$runModel->isTerminal(),
            'total_cases' => $totalCases,
            'processed_cases' => $processedCases,
            'remaining_cases' => $remainingCases,
            'passed_cases' => $runModel->isCompleted() ? (int) ($runModel->passed_cases ?? $passedCases) : $passedCases,
            'failed_cases' => $runModel->isCompleted() ? (int) ($runModel->failed_cases ?? $failedCases) : $failedCases,
            'skipped_cases' => $runModel->isCompleted() ? (int) ($runModel->skipped_cases ?? $skippedCases) : $skippedCases,
            'accuracy_pct' => $runModel->accuracy_pct !== null ? (float) $runModel->accuracy_pct : null,
            'duration_seconds' => $runModel->duration_seconds !== null ? (float) $runModel->duration_seconds : null,
            'started_at' => optional($runModel->started_at)?->toIso8601String(),
            'completed_at' => optional($runModel->completed_at)?->toIso8601String(),
            'error_message' => $runModel->error_message,
            'percent_complete' => $percentComplete,
        ];
    }

    public function cancelRun(ChatbotTestRun|int $run): ChatbotTestRun
    {
        $runModel = $run instanceof ChatbotTestRun
            ? $run->fresh()
            : ChatbotTestRun::query()
                ->where('triggered_by', 'chatbot_lab')
                ->findOrFail($run);

        if ($runModel->isTerminal()) {
            return $runModel;
        }

        $runModel->update([
            'status' => 'cancelled',
            'completed_at' => now(),
            'error_message' => 'Run cancelled by admin.',
        ]);

        return $runModel->fresh();
    }

    public function labRunDetail(int $runId): ?ChatbotTestRun
    {
        if (!$this->runsReady()) {
            return null;
        }

        return ChatbotTestRun::query()
            ->where('triggered_by', 'chatbot_lab')
            ->find($runId);
    }

    public function filteredResults(ChatbotTestRun $run, array $filters): LengthAwarePaginator
    {
        $query = $run->results()
            ->orderByRaw("CASE WHEN status = 'fail' THEN 0 WHEN status = 'error' THEN 1 WHEN status = 'skip' THEN 2 ELSE 3 END")
            ->orderBy('id');

        $status = (string) ($filters['status'] ?? '');
        if ($status !== '' && in_array($status, ['pass', 'fail', 'skip', 'error'], true)) {
            $query->where('status', $status);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('question', 'like', '%' . $search . '%')
                    ->orWhere('actual_response', 'like', '%' . $search . '%')
                    ->orWhere('case_id', 'like', '%' . $search . '%');
            });
        }

        return $query->paginate(20)->appends([
            'status' => $status,
            'search' => $search,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function observabilitySummary(int $days = 7): array
    {
        $safeDays = max(1, min($days, 14));
        $since = now()->subDays($safeDays - 1)->startOfDay();
        $thresholds = $this->metrics->thresholds();

        $runs = ChatbotTestRun::query()
            ->where('triggered_by', 'chatbot_lab')
            ->where('created_at', '>=', $since)
            ->get();

        $results = ChatbotTestResult::query()
            ->whereHas('testRun', function ($query) use ($since): void {
                $query->where('triggered_by', 'chatbot_lab')
                    ->where('created_at', '>=', $since);
            })
            ->get();

        $completedRuns = $runs->filter(fn (ChatbotTestRun $run): bool => $run->isCompleted());
        $providerIssueReasons = [
            ChatbotOutcomeReason::PROVIDER_UNAVAILABLE,
            ChatbotOutcomeReason::PROVIDER_EXCEPTION,
            ChatbotOutcomeReason::EMPTY_MODEL_OUTPUT,
            ChatbotOutcomeReason::CHATBOT_DISABLED,
        ];

        $providerIssueCount = $results->filter(
            fn (ChatbotTestResult $result): bool => in_array((string) $result->fallback_reason, $providerIssueReasons, true)
        )->count();
        $fallbackCount = $results->filter(fn (ChatbotTestResult $result): bool => !empty($result->fallback_reason))->count();
        $regenerationAttemptCount = $results->where('regeneration_attempted', true)->count();
        $regenerationSuccessCount = $results->where('regeneration_succeeded', true)->count();
        $slowResponseThresholdMs = (int) ($thresholds['slow_response_ms'] ?? 8000);
        $slowResponseCount = $results->filter(
            fn (ChatbotTestResult $result): bool => (int) ($result->response_time_ms ?? 0) >= $slowResponseThresholdMs
        )->count();

        $fallbackReasons = $results
            ->filter(fn (ChatbotTestResult $result): bool => !empty($result->fallback_reason))
            ->groupBy(fn (ChatbotTestResult $result): string => (string) $result->fallback_reason)
            ->map(fn (Collection $group, string $reason): array => [
                'reason' => $reason,
                'label' => $this->fallbackReasonLabel($reason) ?? $reason,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->take(5)
            ->all();

        $globalRange = $this->metrics->getRangeSummary($safeDays);
        $dailyTrend = collect($globalRange['daily'] ?? [])->map(function (array $day): array {
            $counts = is_array($day['counts'] ?? null) ? $day['counts'] : [];
            $rates = is_array($day['rates'] ?? null) ? $day['rates'] : [];

            return [
                'date' => (string) ($day['date'] ?? ''),
                'response_total' => (int) ($counts['response_total'] ?? 0),
                'fallback_rate' => (float) ($rates['fallback_rate'] ?? 0),
                'slow_response_rate' => (float) ($rates['slow_response_rate'] ?? 0),
                'provider_fallback_rate' => (float) ($rates['provider_fallback_rate'] ?? 0),
                'provider_incident_rate' => (float) ($rates['provider_incident_rate'] ?? 0),
                'validator_fallback_rate' => (float) ($rates['validator_fallback_rate'] ?? 0),
                'regeneration_attempt_rate' => (float) ($rates['regeneration_attempt_rate'] ?? 0),
            ];
        })->all();
        $globalTotals = collect($globalRange['daily'] ?? [])->reduce(function (array $carry, array $day): array {
            $counts = is_array($day['counts'] ?? null) ? $day['counts'] : [];
            $carry['response_total'] += (int) ($counts['response_total'] ?? 0);
            $carry['fallback_total'] += (int) ($counts['fallback_total'] ?? 0);
            $carry['non_georgian_total'] += (int) ($counts['non_georgian_total'] ?? 0);
            $carry['slow_response_total'] += (int) ($counts['slow_response_total'] ?? 0);
            $carry['regeneration_attempt_total'] += (int) ($counts['regeneration_attempt_total'] ?? 0);
            $carry['regeneration_success_total'] += (int) ($counts['regeneration_success_total'] ?? 0);
            $carry['provider_fallback_total'] += (int) ($counts['provider_fallback_total'] ?? 0);
            $carry['provider_incident_total'] += (int) ($counts['provider_incident_total'] ?? 0);
            $carry['validator_fallback_total'] += (int) ($counts['validator_fallback_total'] ?? 0);
            $carry['policy_fallback_total'] += (int) ($counts['policy_fallback_total'] ?? 0);
            return $carry;
        }, [
            'response_total' => 0,
            'fallback_total' => 0,
            'non_georgian_total' => 0,
            'slow_response_total' => 0,
            'regeneration_attempt_total' => 0,
            'regeneration_success_total' => 0,
            'provider_fallback_total' => 0,
            'provider_incident_total' => 0,
            'validator_fallback_total' => 0,
            'policy_fallback_total' => 0,
        ]);

        $alerts = [];
        $globalFallbackRate = $this->percentage($globalTotals['fallback_total'], $globalTotals['response_total']);
        $globalSlowRate = $this->percentage($globalTotals['slow_response_total'], $globalTotals['response_total']);
        $globalProviderRate = $this->percentage($globalTotals['provider_fallback_total'], $globalTotals['response_total']);
        $globalProviderIncidentRate = $this->percentage($globalTotals['provider_incident_total'], $globalTotals['response_total'] + $globalTotals['provider_incident_total']);
        $globalValidatorRate = $this->percentage($globalTotals['validator_fallback_total'], $globalTotals['response_total']);
        $globalRegenerationAttemptRate = $this->percentage($globalTotals['regeneration_attempt_total'], $globalTotals['response_total']);
        $globalRegenerationSuccessRate = $this->percentage($globalTotals['regeneration_success_total'], $globalTotals['regeneration_attempt_total']);

        if ($globalFallbackRate >= (float) $thresholds['fallback_alert_rate']) {
            $alerts[] = 'Global fallback rate is elevated.';
        }
        if ($globalSlowRate >= (float) $thresholds['slow_response_alert_rate']) {
            $alerts[] = 'Slow response rate is elevated.';
        }
        if ($globalProviderRate >= (float) $thresholds['provider_fallback_alert_rate']) {
            $alerts[] = 'Provider-related fallback rate is elevated.';
        }
        if ($globalProviderIncidentRate >= (float) $thresholds['provider_incident_alert_rate']) {
            $alerts[] = 'Provider incident rate is elevated.';
        }
        if ($globalValidatorRate >= (float) $thresholds['validator_fallback_alert_rate']) {
            $alerts[] = 'Validator fallback rate is elevated.';
        }
        if ($globalRegenerationAttemptRate >= (float) $thresholds['regeneration_attempt_alert_rate']
            && $globalRegenerationSuccessRate < (float) $thresholds['regeneration_success_min_rate']) {
            $alerts[] = 'Regeneration is being used heavily without enough recovery.';
        }

        return [
            'days' => $safeDays,
            'run_count' => $runs->count(),
            'completed_run_count' => $completedRuns->count(),
            'avg_run_duration_seconds' => round((float) $completedRuns->avg('duration_seconds'), 2),
            'avg_seconds_per_case' => round((float) $completedRuns->filter(fn (ChatbotTestRun $run): bool => (int) $run->total_cases > 0)
                ->avg(fn (ChatbotTestRun $run): float => (float) (($run->duration_seconds ?? 0) / max((int) $run->total_cases, 1))), 2),
            'result_count' => $results->count(),
            'avg_response_time_ms' => round((float) $results->avg('response_time_ms')),
            'slow_response_threshold_ms' => $slowResponseThresholdMs,
            'slow_response_count' => $slowResponseCount,
            'slow_response_rate' => $this->percentage($slowResponseCount, $results->count()),
            'fallback_count' => $fallbackCount,
            'fallback_rate' => $this->percentage($fallbackCount, $results->count()),
            'provider_issue_count' => $providerIssueCount,
            'provider_issue_rate' => $this->percentage($providerIssueCount, $results->count()),
            'regeneration_attempt_count' => $regenerationAttemptCount,
            'regeneration_attempt_rate' => $this->percentage($regenerationAttemptCount, $results->count()),
            'regeneration_success_count' => $regenerationSuccessCount,
            'regeneration_success_rate' => $this->percentage($regenerationSuccessCount, $regenerationAttemptCount),
            'top_fallback_reasons' => $fallbackReasons,
            'daily_trend' => $dailyTrend,
            'thresholds' => $thresholds,
            'global_quality' => [
                'response_total' => $globalTotals['response_total'],
                'fallback_total' => $globalTotals['fallback_total'],
                'fallback_rate' => $globalFallbackRate,
                'non_georgian_total' => $globalTotals['non_georgian_total'],
                'non_georgian_rate' => $this->percentage($globalTotals['non_georgian_total'], $globalTotals['response_total']),
                'slow_response_total' => $globalTotals['slow_response_total'],
                'slow_response_rate' => $globalSlowRate,
                'regeneration_attempt_total' => $globalTotals['regeneration_attempt_total'],
                'regeneration_attempt_rate' => $globalRegenerationAttemptRate,
                'regeneration_success_total' => $globalTotals['regeneration_success_total'],
                'regeneration_success_rate' => $globalRegenerationSuccessRate,
                'provider_fallback_total' => $globalTotals['provider_fallback_total'],
                'provider_fallback_rate' => $globalProviderRate,
                'provider_incident_total' => $globalTotals['provider_incident_total'],
                'provider_incident_rate' => $globalProviderIncidentRate,
                'validator_fallback_total' => $globalTotals['validator_fallback_total'],
                'validator_fallback_rate' => $globalValidatorRate,
                'policy_fallback_total' => $globalTotals['policy_fallback_total'],
                'policy_fallback_rate' => $this->percentage($globalTotals['policy_fallback_total'], $globalTotals['response_total']),
            ],
            'alerts' => $alerts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function runObservabilitySnapshot(ChatbotTestRun $run): array
    {
        $results = $run->results()->orderBy('id')->get();
        $providerIssueReasons = [
            ChatbotOutcomeReason::PROVIDER_UNAVAILABLE,
            ChatbotOutcomeReason::PROVIDER_EXCEPTION,
            ChatbotOutcomeReason::EMPTY_MODEL_OUTPUT,
            ChatbotOutcomeReason::CHATBOT_DISABLED,
        ];
        $fallbackCount = $results->filter(fn (ChatbotTestResult $result): bool => !empty($result->fallback_reason))->count();
        $providerIssueCount = $results->filter(fn (ChatbotTestResult $result): bool => in_array((string) $result->fallback_reason, $providerIssueReasons, true))->count();
        $regenerationAttemptCount = $results->where('regeneration_attempted', true)->count();
        $regenerationSuccessCount = $results->where('regeneration_succeeded', true)->count();
        $slowResponseThresholdMs = 8000;
        $slowResponseCount = $results->filter(fn (ChatbotTestResult $result): bool => (int) ($result->response_time_ms ?? 0) >= $slowResponseThresholdMs)->count();

        $topFallbackReason = $results->filter(fn (ChatbotTestResult $result): bool => !empty($result->fallback_reason))
            ->groupBy(fn (ChatbotTestResult $result): string => (string) $result->fallback_reason)
            ->sortByDesc(fn (Collection $group): int => $group->count())
            ->keys()
            ->first();

        return [
            'result_count' => $results->count(),
            'avg_response_time_ms' => round((float) $results->avg('response_time_ms')),
            'slow_response_threshold_ms' => $slowResponseThresholdMs,
            'slow_response_count' => $slowResponseCount,
            'fallback_count' => $fallbackCount,
            'provider_issue_count' => $providerIssueCount,
            'regeneration_attempt_count' => $regenerationAttemptCount,
            'regeneration_success_count' => $regenerationSuccessCount,
            'regeneration_success_rate' => $this->percentage($regenerationSuccessCount, $regenerationAttemptCount),
            'top_fallback_reason' => $topFallbackReason,
            'top_fallback_reason_label' => $this->fallbackReasonLabel($topFallbackReason),
        ];
    }

    /**
     * @return array{signal_group:string,signal_label:string,signal_severity:string,recommended_action:string}
     */
    public function summarizeResultSignal(ChatbotTestResult $result): array
    {
        if ($result->status === 'error') {
            return [
                'signal_group' => 'provider',
                'signal_label' => 'Execution error',
                'signal_severity' => 'high',
                'recommended_action' => 'Inspect the failing runtime path and infrastructure before changing case expectations.',
            ];
        }

        if ($result->guardrail_passed === false || $result->georgian_qa_passed === false) {
            return [
                'signal_group' => 'policy',
                'signal_label' => 'Guardrail or language failure',
                'signal_severity' => 'high',
                'recommended_action' => 'Review safety rules, Georgian QA checks, and generated wording for this case.',
            ];
        }

        if ($result->intent_match === false) {
            return [
                'signal_group' => 'intent',
                'signal_label' => 'Intent mismatch',
                'signal_severity' => 'medium',
                'recommended_action' => 'Inspect intent classification and add examples for this request pattern.',
            ];
        }

        if ($result->entity_match === false) {
            return [
                'signal_group' => 'search',
                'signal_label' => 'Entity grounding mismatch',
                'signal_severity' => 'medium',
                'recommended_action' => 'Check product/entity extraction and verify the retrieved item actually matches the request.',
            ];
        }

        if ($result->keyword_match === false || $result->price_match === false || $result->stock_match === false) {
            return [
                'signal_group' => 'validation',
                'signal_label' => 'Expectation mismatch',
                'signal_severity' => 'medium',
                'recommended_action' => 'Compare the answer with case expectations and adjust either grounding or the case if expectations are stale.',
            ];
        }

        return [
            'signal_group' => 'healthy',
            'signal_label' => 'No major issue detected',
            'signal_severity' => 'low',
            'recommended_action' => 'Use reviewer notes only if you want to tighten phrasing or add a stronger assertion.',
        ];
    }

    public function fallbackReasonLabel(?string $reason): ?string
    {
        if ($reason === null || trim($reason) === '') {
            return null;
        }

        return match ($reason) {
            ChatbotOutcomeReason::INPUT_GUARD => 'Input guard block',
            ChatbotOutcomeReason::GREETING_ONLY => 'Greeting-only fast path',
            ChatbotOutcomeReason::OUT_OF_DOMAIN => 'Out-of-domain clarification',
            ChatbotOutcomeReason::CLARIFICATION_NEEDED => 'Clarification requested',
            ChatbotOutcomeReason::CHATBOT_DISABLED => 'Chatbot disabled fallback',
            ChatbotOutcomeReason::PROVIDER_UNAVAILABLE => 'Provider unavailable',
            ChatbotOutcomeReason::PROVIDER_EXCEPTION => 'Provider exception',
            ChatbotOutcomeReason::EMPTY_MODEL_OUTPUT => 'Empty model output',
            ChatbotOutcomeReason::GENERIC_REPEATED => 'Generic or repeated fallback',
            ChatbotOutcomeReason::STRICT_GEORGIAN => 'Strict Georgian fallback',
            ChatbotOutcomeReason::VALIDATOR_FAILED => 'Validator blocked reply',
            ChatbotOutcomeReason::VALIDATOR_RETRY_FAILED => 'Validator retry failed',
            default => ucwords(str_replace('_', ' ', str_replace(':', ' ', $reason))),
        };
    }

    /**
     * @param array<int, int> $caseIds
     * @return Collection<int, ChatbotTrainingCase>
     */
    private function selectedCases(array $caseIds): Collection
    {
        return ChatbotTrainingCase::query()
            ->whereIn('id', $caseIds)
            ->orderBy('id')
            ->get();
    }

    private function createRunRecord(Collection $selectedCases, bool $useLlmJudge, string $status): ChatbotTestRun
    {
        return ChatbotTestRun::create([
            'status' => $status,
            'triggered_by' => 'chatbot_lab',
            'filters' => [
                'lab' => true,
                'case_ids' => $selectedCases->pluck('id')->map(fn ($id): string => (string) $id)->all(),
                'use_llm_judge' => $useLlmJudge,
            ],
            'total_cases' => $selectedCases->count(),
        ]);
    }

    private function percentage(int $numerator, int $denominator): float
    {
        if ($denominator <= 0) {
            return 0.0;
        }

        return round(($numerator / $denominator) * 100, 2);
    }

    private function markRunRunning(ChatbotTestRun $run, int $totalCases, \Illuminate\Support\Carbon $startedAt): void
    {
        $run->update([
            'status' => 'running',
            'started_at' => $startedAt,
            'completed_at' => null,
            'error_message' => null,
            'total_cases' => $totalCases,
        ]);
    }

    private function markRunFailed(ChatbotTestRun $run, \Throwable $exception): void
    {
        $run->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
            'completed_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapTrainingCaseToRunnerCase(ChatbotTrainingCase $trainingCase): array
    {
        $messages = collect($trainingCase->conversation_context_json ?? [])
            ->filter(fn ($line) => is_string($line) && trim($line) !== '')
            ->map(fn (string $line): array => [
                'role' => 'user',
                'content' => $line,
            ])
            ->values();

        $messages->push([
            'role' => 'user',
            'content' => (string) $trainingCase->prompt,
        ]);

        return [
            'id' => 'training-case-' . $trainingCase->id,
            'category' => $trainingCase->expected_intent ?: 'training_case',
            'question' => (string) $trainingCase->prompt,
            'messages' => $messages->all(),
            'expected' => [
                'must_contain_any' => $trainingCase->expected_keywords_json ?? [],
                'expected_intent' => $trainingCase->expected_intent,
                'expected_product_slugs' => $trainingCase->expected_product_slugs_json ?? [],
                'expected_price_behavior' => $trainingCase->expected_price_behavior,
                'stock_claim' => $trainingCase->expected_stock_behavior,
                'llm_judge_criteria' => $trainingCase->reviewer_notes ?: 'Must answer the user request accurately and safely.',
            ],
        ];
    }
}
