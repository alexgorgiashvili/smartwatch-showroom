<?php

namespace App\Services\ProductQuality;

use App\Models\ResearchTarget;
use App\Services\Chatbot\ModelCompletionService;
use Illuminate\Support\Collection;

class ProductQualityAnalysisService
{
    public function __construct(
        private readonly ModelCompletionService $modelCompletion,
        private readonly ProductQualityHeuristicService $heuristics,
    ) {
    }

    public function analyze(ResearchTarget $target): array
    {
        $evidenceItems = $target->evidenceItems()->orderByDesc('published_at')->orderByDesc('id')->get();
        $fallback = $this->heuristics->buildEvidenceInsights($evidenceItems, $target->display_name);
        $summary = $fallback['summary'];
        $comparisonReadyPayload = $fallback['comparison_ready_payload'];
        $modelUsed = null;

        $reply = $this->modelCompletion->complete(
            config('services.openai.model', 'gpt-4.1-mini'),
            $this->buildMessages($target, $evidenceItems, $fallback),
            [
                'temperature' => 0.1,
                'max_tokens' => 1400,
                'langfuse_name' => 'product_quality.analysis',
                'langfuse_metadata' => [
                    'research_target_id' => $target->id,
                    'mode' => $target->mode,
                    'evidence_count' => $evidenceItems->count(),
                ],
            ]
        );

        if (($reply['reason'] ?? null) === null && ($reply['reply'] ?? '') !== '') {
            $parsed = $this->decodeJsonReply((string) $reply['reply']);

            if (is_array($parsed)) {
                foreach ([
                    'strengths',
                    'weaknesses',
                    'recurring_praise',
                    'recurring_complaints',
                    'reliability_signals',
                    'practicality_notes',
                    'battery_issues',
                    'gps_issues',
                    'call_issues',
                    'app_issues',
                    'risk_flags',
                    'evidence_gaps',
                    'comparison_notes',
                ] as $key) {
                    if (isset($parsed[$key]) && is_array($parsed[$key])) {
                        $summary[$key] = array_values(array_filter(array_map('strval', $parsed[$key])));
                    }
                }

                if (isset($parsed['verdict_rationale']) && is_string($parsed['verdict_rationale']) && trim($parsed['verdict_rationale']) !== '') {
                    $summary['verdict_rationale'] = trim($parsed['verdict_rationale']);
                }

                $comparisonReadyPayload['ai_summary_used'] = true;
                $modelUsed = config('services.openai.model', 'gpt-4.1-mini');
            }
        }

        return [
            'model_used' => $modelUsed,
            'summary_json' => $summary,
            'comparison_ready_payload' => $comparisonReadyPayload,
            'confidence_score' => $fallback['confidence_score'],
            'verdict' => $fallback['verdict'],
            'evidence_count' => $fallback['counts']['evidence_count'],
            'end_user_evidence_count' => $fallback['counts']['end_user_evidence_count'],
            'supplier_evidence_count' => $fallback['counts']['supplier_evidence_count'],
        ];
    }

    private function buildMessages(ResearchTarget $target, Collection $evidenceItems, array $fallback): array
    {
        $samples = $evidenceItems->take(12)->map(function ($item) {
            return [
                'source_type' => $item->source_type,
                'author_type' => $item->author_type,
                'rating_raw' => $item->rating_raw,
                'title' => $item->title,
                'body_text' => $item->body_text,
                'published_at' => optional($item->published_at)->toIso8601String(),
                'normalized_payload' => $item->normalized_payload,
            ];
        })->values()->all();

        $schema = [
            'strengths' => ['string'],
            'weaknesses' => ['string'],
            'recurring_praise' => ['string'],
            'recurring_complaints' => ['string'],
            'reliability_signals' => ['string'],
            'practicality_notes' => ['string'],
            'battery_issues' => ['string'],
            'gps_issues' => ['string'],
            'call_issues' => ['string'],
            'app_issues' => ['string'],
            'risk_flags' => ['string'],
            'evidence_gaps' => ['string'],
            'verdict_rationale' => 'string',
            'comparison_notes' => ['string'],
        ];

        return [
            [
                'role' => 'system',
                'content' => 'You analyze stored product evidence. Return valid JSON only. Do not treat product specs or marketing copy as user evidence. Supplier feedback must be weighted lower than end-user evidence. If evidence is thin, say so plainly.',
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'target' => [
                        'id' => $target->id,
                        'mode' => $target->mode,
                        'display_name' => $target->display_name,
                        'brand' => $target->brand,
                        'model' => $target->model,
                    ],
                    'expected_schema' => $schema,
                    'fallback_summary' => $fallback['summary'],
                    'stored_evidence_samples' => $samples,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
        ];
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
