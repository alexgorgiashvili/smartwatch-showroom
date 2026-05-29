<?php

namespace App\Services\ProductQuality;

use App\Models\ProductQualityAnalysis;
use App\Services\Chatbot\ModelCompletionService;
use Illuminate\Support\Collection;

class ProductQualityComparisonService
{
    public function __construct(
        private readonly ModelCompletionService $modelCompletion,
    ) {
    }

    public function compare(iterable $analyses): array
    {
        $analysisCollection = collect($analyses)
            ->filter(fn ($analysis) => $analysis instanceof ProductQualityAnalysis && $analysis->status === 'completed')
            ->values();

        if ($analysisCollection->count() < 2) {
            return [
                'winner_target_id' => null,
                'firmness' => 'weak',
                'comparison_summary' => 'At least two completed analyses are required for comparison.',
                'key_differences' => [],
                'winner_weaker_areas' => [],
                'risk_notes' => [],
                'model_used' => null,
            ];
        }

        $fallback = $this->buildFallbackComparison($analysisCollection);
        $reply = $this->modelCompletion->complete(
            config('services.openai.model', 'gpt-4.1-mini'),
            [
                [
                    'role' => 'system',
                    'content' => 'Compare analyzed products using stored evidence payloads only. Return valid JSON only. Name a winner only if the evidence actually supports it and state whether the conclusion is firm or provisional.',
                ],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'expected_schema' => [
                            'winner_target_id' => 'int|null',
                            'firmness' => 'firm|provisional|weak',
                            'comparison_summary' => 'string',
                            'key_differences' => ['string'],
                            'winner_weaker_areas' => ['string'],
                            'risk_notes' => ['string'],
                        ],
                        'analyses' => $analysisCollection->map(fn (ProductQualityAnalysis $analysis) => [
                            'research_target_id' => $analysis->research_target_id,
                            'comparison_ready_payload' => $analysis->comparison_ready_payload,
                        ])->all(),
                        'fallback' => $fallback,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
            ],
            [
                'temperature' => 0.1,
                'max_tokens' => 1000,
                'langfuse_name' => 'product_quality.comparison',
                'langfuse_metadata' => [
                    'analysis_ids' => $analysisCollection->pluck('id')->all(),
                ],
            ]
        );

        if (($reply['reason'] ?? null) === null && ($reply['reply'] ?? '') !== '') {
            $parsed = $this->decodeJsonReply((string) $reply['reply']);

            if (is_array($parsed)) {
                return array_merge($fallback, [
                    'winner_target_id' => $parsed['winner_target_id'] ?? $fallback['winner_target_id'],
                    'firmness' => $parsed['firmness'] ?? $fallback['firmness'],
                    'comparison_summary' => $parsed['comparison_summary'] ?? $fallback['comparison_summary'],
                    'key_differences' => isset($parsed['key_differences']) && is_array($parsed['key_differences']) ? array_values(array_map('strval', $parsed['key_differences'])) : $fallback['key_differences'],
                    'winner_weaker_areas' => isset($parsed['winner_weaker_areas']) && is_array($parsed['winner_weaker_areas']) ? array_values(array_map('strval', $parsed['winner_weaker_areas'])) : $fallback['winner_weaker_areas'],
                    'risk_notes' => isset($parsed['risk_notes']) && is_array($parsed['risk_notes']) ? array_values(array_map('strval', $parsed['risk_notes'])) : $fallback['risk_notes'],
                    'model_used' => config('services.openai.model', 'gpt-4.1-mini'),
                ]);
            }
        }

        return $fallback;
    }

    private function buildFallbackComparison(Collection $analyses): array
    {
        $ranked = $analyses->sortByDesc(function (ProductQualityAnalysis $analysis) {
            $payload = is_array($analysis->comparison_ready_payload) ? $analysis->comparison_ready_payload : [];
            $verdictScore = match ($analysis->verdict) {
                'strong_buy' => 3,
                'conditional_buy' => 2,
                default => 1,
            };

            return ($verdictScore * 1000)
                + ((float) ($analysis->confidence_score ?? 0) * 10)
                + ((int) ($payload['evidence_count'] ?? 0));
        })->values();

        /** @var ProductQualityAnalysis $winner */
        $winner = $ranked->first();
        $runnerUp = $ranked->get(1);
        $winnerPayload = is_array($winner->comparison_ready_payload) ? $winner->comparison_ready_payload : [];
        $runnerPayload = is_array($runnerUp?->comparison_ready_payload) ? $runnerUp->comparison_ready_payload : [];

        $firmness = ((float) ($winner->confidence_score ?? 0) >= 65 && ($winnerPayload['evidence_count'] ?? 0) >= 4)
            ? 'firm'
            : 'provisional';

        return [
            'winner_target_id' => $winner->research_target_id,
            'firmness' => $firmness,
            'comparison_summary' => $this->buildFallbackSummary($winnerPayload, $runnerPayload, $firmness),
            'key_differences' => array_values(array_filter([
                $this->differenceLine($winnerPayload, $runnerPayload, 'reliability', 'Reliability'),
                $this->differenceLine($winnerPayload, $runnerPayload, 'battery', 'Battery experience'),
                $this->differenceLine($winnerPayload, $runnerPayload, 'gps', 'GPS reliability'),
                $this->differenceLine($winnerPayload, $runnerPayload, 'app', 'App/setup experience'),
            ])),
            'winner_weaker_areas' => array_values(array_slice((array) ($winnerPayload['weaknesses'] ?? []), 0, 3)),
            'risk_notes' => array_values(array_unique(array_merge(
                (array) ($winnerPayload['risk_flags'] ?? []),
                (array) ($winnerPayload['evidence_gaps'] ?? []),
                (array) ($runnerPayload['risk_flags'] ?? [])
            ))),
            'model_used' => null,
        ];
    }

    private function buildFallbackSummary(array $winnerPayload, array $runnerPayload, string $firmness): string
    {
        $winnerName = (string) ($winnerPayload['display_name'] ?? 'The leading product');
        $runnerName = (string) ($runnerPayload['display_name'] ?? 'the alternative');
        $winnerConfidence = (float) ($winnerPayload['confidence_score'] ?? 0);
        $winnerEvidence = (int) ($winnerPayload['evidence_count'] ?? 0);

        return "{$winnerName} currently ranks ahead of {$runnerName} because its stored analysis shows a better verdict, stronger confidence, or broader evidence coverage. This conclusion is {$firmness}, with {$winnerConfidence}/100 confidence built from {$winnerEvidence} evidence items.";
    }

    private function differenceLine(array $winnerPayload, array $runnerPayload, string $rubricKey, string $label): ?string
    {
        $winnerScore = (float) data_get($winnerPayload, "rubric.{$rubricKey}", 0);
        $runnerScore = (float) data_get($runnerPayload, "rubric.{$rubricKey}", 0);

        if ($winnerScore === 0 && $runnerScore === 0) {
            return null;
        }

        $delta = round($winnerScore - $runnerScore, 2);

        if (abs($delta) < 0.3) {
            return "{$label} is too close to call from current evidence.";
        }

        return $delta > 0
            ? "{$label} looks stronger for the current winner."
            : "{$label} is a point where the winner still trails.";
    }

    private function decodeJsonReply(string $reply): ?array
    {
        $decoded = json_decode($reply, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $reply, $match) === 1) {
            $decoded = json_decode($match[0], true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }
}
