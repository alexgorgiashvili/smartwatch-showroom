<?php

namespace App\Services\Chatbot;

use InvalidArgumentException;
use Throwable;

/**
 * Compares preapproved models on the same frozen, sanitized cases.
 * The executor is supplied by the caller. This class never connects to a
 * provider, database, queue, or logging service.
 */
class PairedModelBenchmarkService
{
    public const HARD_BUDGET_USD = 3.0;
    public const MIN_GRADED_PAIRS = 100;

    /**
     * @param array<int, array<string, mixed>> $cases
     * @param array<string, array<string, float|int>> $pricesPerMillion
     * @param callable(array, string, array): array $executor Must enforce limits and return actual usage and measured latency.
     * @param null|callable(array, array, array): ?array $offlineJudge Returns per-side quality_score (1..5), grounded, georgian_ok, critical_unverified_claim, fallback_ok.
     */
    public function run(
        array $cases,
        string $baselineModel,
        string $candidateModel,
        array $pricesPerMillion,
        callable $executor,
        ?callable $offlineJudge = null,
        array $options = []
    ): array {
        if ($baselineModel === '' || $candidateModel === '' || $baselineModel === $candidateModel) {
            throw new InvalidArgumentException('Two distinct model IDs are required.');
        }

        $budgetUsd = (float) ($options['budget_usd'] ?? self::HARD_BUDGET_USD);
        if ($budgetUsd <= 0 || $budgetUsd > self::HARD_BUDGET_USD) {
            throw new InvalidArgumentException('The benchmark budget must be above zero and at most $3.');
        }

        $models = [$baselineModel, $candidateModel];
        foreach ($models as $model) {
            $this->validatePrices($model, $pricesPerMillion[$model] ?? null);
        }

        $maxInputTokens = max(1, (int) ($options['max_input_tokens'] ?? 6000));
        $maxCompletionTokens = max(1, (int) ($options['max_completion_tokens'] ?? 400));
        $minGradedPairs = max(self::MIN_GRADED_PAIRS, (int) ($options['min_graded_pairs'] ?? self::MIN_GRADED_PAIRS));
        $limits = [
            'max_input_tokens' => $maxInputTokens,
            'max_completion_tokens' => $maxCompletionTokens,
            'eval_bypass_cache' => true,
        ];
        $reservation = [];
        foreach ($models as $model) {
            $reservation[$model] = $this->maximumCost($pricesPerMillion[$model], $maxInputTokens, $maxCompletionTokens);
        }

        $spent = 0.0;
        $budgetVerified = true;
        $completedPairs = 0;
        $gradedPairs = 0;
        $releaseGradedPairs = 0;
        $stoppedReason = null;
        $rows = [];
        $summaries = [];
        foreach ($models as $model) {
            $summaries[$model] = [
                'cost_usd' => 0.0,
                'latencies_ms' => [],
                'quality_scores' => [],
                'successful_answers' => 0,
                'georgian_ok_count' => 0,
                'critical_unverified_claim_count' => 0,
            ];
        }

        foreach ($cases as $case) {
            if (!is_array($case) || trim((string) ($case['id'] ?? '')) === '') {
                throw new InvalidArgumentException('Every frozen case requires a stable id.');
            }

            // Reserve both sides before either call so the comparison is paired.
            if ($spent + array_sum($reservation) > $budgetUsd + 0.000000001) {
                $stoppedReason = 'budget_reservation_exhausted';
                break;
            }

            $row = [
                'id' => (string) $case['id'],
                'category' => (string) ($case['category'] ?? ''),
                'baseline' => null,
                'candidate' => null,
                'graded' => false,
            ];
            $rawSides = [];

            foreach ($models as $index => $model) {
                try {
                    $recording = $executor($case, $model, $limits);
                } catch (Throwable) {
                    $stoppedReason = 'executor_error';
                    break;
                }

                if (!is_array($recording)) {
                    $stoppedReason = 'invalid_recording';
                    break;
                }
                $rawSides[$index === 0 ? 'baseline' : 'candidate'] = $recording;

                $usage = $this->normalizeUsage($recording['usage'] ?? null);
                if ($usage === null) {
                    $budgetVerified = false;
                    $stoppedReason = 'usage_unavailable';
                    break;
                }

                $cost = $this->actualCost($pricesPerMillion[$model], $usage);
                $spent += $cost;
                $summaries[$model]['cost_usd'] += $cost;

                if ($usage['input_tokens'] > $maxInputTokens
                    || $usage['output_tokens'] > $maxCompletionTokens
                    || $cost > $reservation[$model] + 0.000000001
                    || $spent > $budgetUsd + 0.000000001) {
                    $budgetVerified = false;
                    $stoppedReason = 'usage_exceeded_reservation';
                    break;
                }

                $latency = filter_var($recording['latency_ms'] ?? null, FILTER_VALIDATE_INT);
                $latency = $latency !== false && $latency > 0 ? $latency : null;
                if ($latency !== null) {
                    $summaries[$model]['latencies_ms'][] = $latency;
                }

                $side = [
                    'model' => $model,
                    'cost_usd' => round($cost, 8),
                    'usage' => $usage,
                    'latency_ms' => $latency,
                    'intent' => is_string($recording['intent'] ?? null) ? $recording['intent'] : null,
                    'fallback_reason' => is_string($recording['fallback_reason'] ?? null) ? $recording['fallback_reason'] : null,
                    'quality_score' => null,
                ];
                if ($options['include_responses'] ?? false) {
                    $side['response'] = (string) ($recording['response'] ?? '');
                }

                $row[$index === 0 ? 'baseline' : 'candidate'] = $side;
            }

            if ($stoppedReason !== null) {
                $rows[] = $row;
                break;
            }

            $completedPairs++;
            if ($offlineJudge !== null) {
                try {
                    $grades = $offlineJudge($case, $rawSides['baseline'], $rawSides['candidate']);
                } catch (Throwable) {
                    $grades = null;
                }
                $baselineGrade = $grades['baseline'] ?? null;
                $candidateGrade = $grades['candidate'] ?? null;
                $baselineScore = $this->validScore(is_array($baselineGrade) ? ($baselineGrade['quality_score'] ?? null) : $baselineGrade);
                $candidateScore = $this->validScore(is_array($candidateGrade) ? ($candidateGrade['quality_score'] ?? null) : $candidateGrade);
                if ($baselineScore !== null && $candidateScore !== null) {
                    $row['baseline']['quality_score'] = $baselineScore;
                    $row['candidate']['quality_score'] = $candidateScore;
                    $row['graded'] = true;
                    $gradedPairs++;
                    $summaries[$baselineModel]['quality_scores'][] = $baselineScore;
                    $summaries[$candidateModel]['quality_scores'][] = $candidateScore;
                }

                $baselineRubric = $this->releaseRubric($baselineGrade);
                $candidateRubric = $this->releaseRubric($candidateGrade);
                if ($baselineRubric !== null && $candidateRubric !== null) {
                    $releaseGradedPairs++;
                    foreach (['baseline' => $baselineModel, 'candidate' => $candidateModel] as $sideName => $modelName) {
                        $rubric = $sideName === 'baseline' ? $baselineRubric : $candidateRubric;
                        $success = $rubric['grounded'] && $rubric['georgian_ok']
                            && !$rubric['critical_unverified_claim'] && $rubric['fallback_ok'];
                        $row[$sideName]['grounded_success'] = $success;
                        $row[$sideName]['georgian_ok'] = $rubric['georgian_ok'];
                        $row[$sideName]['critical_unverified_claim'] = $rubric['critical_unverified_claim'];
                        $row[$sideName]['fallback_ok'] = $rubric['fallback_ok'];
                        $summaries[$modelName]['successful_answers'] += (int) $success;
                        $summaries[$modelName]['georgian_ok_count'] += (int) $rubric['georgian_ok'];
                        $summaries[$modelName]['critical_unverified_claim_count'] += (int) $rubric['critical_unverified_claim'];
                    }
                }
            }

            $rows[] = $row;
        }

        $complete = $stoppedReason === null && $completedPairs === count($cases);
        $allLatenciesMeasured = count($summaries[$baselineModel]['latencies_ms']) === $completedPairs
            && count($summaries[$candidateModel]['latencies_ms']) === $completedPairs;
        $reviewReady = $complete
            && $budgetVerified
            && $offlineJudge !== null
            && $gradedPairs >= $minGradedPairs
            && $releaseGradedPairs >= $minGradedPairs
            && $allLatenciesMeasured;

        foreach ($models as $model) {
            $latencies = $summaries[$model]['latencies_ms'];
            $scores = $summaries[$model]['quality_scores'];
            sort($latencies);
            $summaries[$model] = [
                'cost_usd' => round($summaries[$model]['cost_usd'], 8),
                'mean_latency_ms' => $latencies === [] ? null : round(array_sum($latencies) / count($latencies), 1),
                'p95_latency_ms' => $latencies === [] ? null : $latencies[(int) ceil(count($latencies) * 0.95) - 1],
                'measured_latency_count' => count($latencies),
                'mean_quality_score' => $scores === [] ? null : round(array_sum($scores) / count($scores), 3),
                'graded_count' => count($scores),
                'successful_answers' => $summaries[$model]['successful_answers'],
                'grounded_success_pct' => $releaseGradedPairs > 0
                    ? round(100 * $summaries[$model]['successful_answers'] / $releaseGradedPairs, 2) : null,
                'georgian_ok_pct' => $releaseGradedPairs > 0
                    ? round(100 * $summaries[$model]['georgian_ok_count'] / $releaseGradedPairs, 2) : null,
                'critical_unverified_claim_count' => $summaries[$model]['critical_unverified_claim_count'],
                'cost_per_success_usd' => $summaries[$model]['successful_answers'] > 0
                    ? round($summaries[$model]['cost_usd'] / $summaries[$model]['successful_answers'], 8) : null,
            ];
        }

        $baseline = $summaries[$baselineModel];
        $candidate = $summaries[$candidateModel];
        $qualityDeltaPp = $reviewReady
            ? round($candidate['grounded_success_pct'] - $baseline['grounded_success_pct'], 2) : null;
        $costReductionPct = $reviewReady && $baseline['cost_per_success_usd'] > 0
            && $candidate['cost_per_success_usd'] !== null
                ? round(100 * (1 - $candidate['cost_per_success_usd'] / $baseline['cost_per_success_usd']), 2)
                : null;
        $acceptance = $reviewReady ? [
            'quality_delta_pp' => $qualityDeltaPp,
            'cost_per_success_reduction_pct' => $costReductionPct,
            'quality_gain_at_least_5pp' => $qualityDeltaPp >= 5,
            'cost_reduction_at_least_20pct' => $costReductionPct !== null && $costReductionPct >= 20,
            'georgian_not_worse' => $candidate['georgian_ok_pct'] >= $baseline['georgian_ok_pct'],
            'p95_not_worse' => $candidate['p95_latency_ms'] <= $baseline['p95_latency_ms'],
            'no_candidate_critical_claims' => $candidate['critical_unverified_claim_count'] === 0,
        ] : null;
        $passes = $acceptance !== null
            && $acceptance['quality_gain_at_least_5pp']
            && $acceptance['cost_reduction_at_least_20pct']
            && $acceptance['georgian_not_worse']
            && $acceptance['p95_not_worse']
            && $acceptance['no_candidate_critical_claims'];

        return [
            'baseline_model' => $baselineModel,
            'candidate_model' => $candidateModel,
            'dataset_cases' => count($cases),
            'completed_pairs' => $completedPairs,
            'graded_pairs' => $gradedPairs,
            'release_graded_pairs' => $releaseGradedPairs,
            'minimum_graded_pairs' => $minGradedPairs,
            'judge_available' => $offlineJudge !== null,
            'budget_usd' => $budgetUsd,
            'total_cost_usd' => $budgetVerified ? round($spent, 8) : null,
            'budget_verified' => $budgetVerified,
            'stopped_reason' => $stoppedReason,
            'decision' => !$reviewReady ? 'insufficient_evidence' : ($passes ? 'candidate_meets_gates' : 'candidate_rejected'),
            'acceptance' => $acceptance,
            'models' => $summaries,
            'cases' => $rows,
        ];
    }

    private function validatePrices(string $model, mixed $row): void
    {
        if (!is_array($row)) {
            throw new InvalidArgumentException("Missing price table for {$model}.");
        }
        foreach (['input', 'cached_input', 'cache_write', 'output'] as $key) {
            if (!isset($row[$key]) || !is_numeric($row[$key]) || (float) $row[$key] < 0) {
                throw new InvalidArgumentException("Invalid {$key} price for {$model}.");
            }
        }
    }

    private function maximumCost(array $prices, int $maxInputTokens, int $maxCompletionTokens): float
    {
        $inputRate = max((float) $prices['input'], (float) $prices['cached_input'], (float) $prices['cache_write']);
        return ($maxInputTokens * $inputRate + $maxCompletionTokens * (float) $prices['output']) / 1_000_000;
    }

    private function actualCost(array $prices, array $usage): float
    {
        $uncached = $usage['input_tokens'] - $usage['cached_input_tokens'] - $usage['cache_write_tokens'];
        return (
            $uncached * (float) $prices['input']
            + $usage['cached_input_tokens'] * (float) $prices['cached_input']
            + $usage['cache_write_tokens'] * (float) $prices['cache_write']
            + $usage['output_tokens'] * (float) $prices['output']
        ) / 1_000_000;
    }

    private function normalizeUsage(mixed $usage): ?array
    {
        if (!is_array($usage)) {
            return null;
        }

        $input = $usage['input_tokens'] ?? $usage['prompt_tokens'] ?? null;
        $output = $usage['output_tokens'] ?? $usage['completion_tokens'] ?? null;
        $cached = $usage['cached_input_tokens']
            ?? ($usage['prompt_tokens_details']['cached_tokens'] ?? null)
            ?? ($usage['input_tokens_details']['cached_tokens'] ?? 0);
        $cacheWrite = $usage['cache_write_tokens'] ?? 0;
        foreach ([$input, $output, $cached, $cacheWrite] as $value) {
            if (!is_numeric($value) || (int) $value < 0 || (float) $value !== (float) (int) $value) {
                return null;
            }
        }
        if ((int) $cached + (int) $cacheWrite > (int) $input) {
            return null;
        }

        return [
            'input_tokens' => (int) $input,
            'cached_input_tokens' => (int) $cached,
            'cache_write_tokens' => (int) $cacheWrite,
            'output_tokens' => (int) $output,
        ];
    }

    private function validScore(mixed $score): ?float
    {
        if (!is_numeric($score)) {
            return null;
        }
        $value = (float) $score;
        return $value >= 1.0 && $value <= 5.0 ? $value : null;
    }

    /** @return null|array{grounded:bool,georgian_ok:bool,critical_unverified_claim:bool,fallback_ok:bool} */
    private function releaseRubric(mixed $grade): ?array
    {
        if (!is_array($grade)) {
            return null;
        }
        $keys = ['grounded', 'georgian_ok', 'critical_unverified_claim', 'fallback_ok'];
        foreach ($keys as $key) {
            if (!isset($grade[$key]) || !is_bool($grade[$key])) {
                return null;
            }
        }

        return array_intersect_key($grade, array_flip($keys));
    }
}
