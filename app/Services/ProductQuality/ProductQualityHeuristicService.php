<?php

namespace App\Services\ProductQuality;

use App\Models\ProductEvidenceItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProductQualityHeuristicService
{
    private const POSITIVE_KEYWORDS = [
        'good',
        'great',
        'reliable',
        'accurate',
        'durable',
        'clear',
        'easy',
        'fast',
        'love',
        'solid',
        'stable',
        'useful',
        'comfortable',
    ];

    private const NEGATIVE_KEYWORDS = [
        'bad',
        'poor',
        'broken',
        'defect',
        'issue',
        'problem',
        'drain',
        'inaccurate',
        'delay',
        'disconnect',
        'fail',
        'refund',
        'return',
        'cheap',
        'crash',
    ];

    private const CATEGORY_KEYWORDS = [
        'reliability' => ['reliable', 'reliability', 'broken', 'defect', 'stopped', 'lasted', 'durable', 'failure'],
        'battery' => ['battery', 'charge', 'charging', 'drain', 'power'],
        'gps' => ['gps', 'location', 'tracking', 'position'],
        'call' => ['call', 'mic', 'speaker', 'voice', 'audio'],
        'app' => ['app', 'setup', 'pair', 'connect', 'sync', 'software'],
        'kid_use_practicality' => ['kid', 'child', 'school', 'sos', 'button', 'comfort', 'strap'],
        'build_quality' => ['build', 'screen', 'material', 'strap', 'waterproof', 'durable'],
    ];

    public function normalizeEvidenceCandidate(array $candidate): array
    {
        $title = $this->sanitizeText($candidate['title'] ?? null);
        $body = $this->sanitizeText($candidate['body_text'] ?? null);
        $combined = mb_strtolower(trim($title . ' ' . $body));
        $featureMentions = [];
        $issueTags = [];
        $sentimentScore = 0;

        foreach (self::CATEGORY_KEYWORDS as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($combined, $keyword)) {
                    $featureMentions[] = $category;
                    if (in_array($keyword, self::NEGATIVE_KEYWORDS, true)) {
                        $issueTags[] = $category;
                    }
                }
            }
        }

        foreach (self::POSITIVE_KEYWORDS as $keyword) {
            if (str_contains($combined, $keyword)) {
                $sentimentScore++;
            }
        }

        foreach (self::NEGATIVE_KEYWORDS as $keyword) {
            if (str_contains($combined, $keyword)) {
                $sentimentScore--;
                $issueTags[] = $keyword;
            }
        }

        $credibilityWeight = isset($candidate['credibility_weight']) && is_numeric($candidate['credibility_weight'])
            ? round((float) $candidate['credibility_weight'], 2)
            : $this->defaultCredibilityWeight((string) ($candidate['author_type'] ?? 'unknown'));

        $dedupeText = mb_strtolower(trim(implode(' | ', array_filter([
            $candidate['source_type'] ?? null,
            $candidate['source_item_id'] ?? null,
            $title,
            $body,
        ]))));

        return array_merge($candidate, [
            'title' => $title,
            'body_text' => $body,
            'credibility_weight' => $credibilityWeight,
            'dedupe_hash' => sha1($dedupeText),
            'normalized_payload' => [
                'feature_mentions' => array_values(array_unique($featureMentions)),
                'issue_tags' => array_values(array_unique($issueTags)),
                'sentiment_score' => $sentimentScore,
                'sentiment_label' => $sentimentScore > 0 ? 'positive' : ($sentimentScore < 0 ? 'negative' : 'mixed'),
                'excerpt' => Str::limit($body, 220),
            ],
        ]);
    }

    public function buildEvidenceInsights(Collection $evidenceItems, string $displayName): array
    {
        $rubric = [
            'reliability' => 3.0,
            'battery' => 3.0,
            'gps' => 3.0,
            'call' => 3.0,
            'app' => 3.0,
            'kid_use_practicality' => 3.0,
            'build_quality' => 3.0,
            'source_confidence' => 2.5,
        ];

        $positiveByCategory = [];
        $negativeByCategory = [];
        $evidenceExamples = [
            'battery' => [],
            'gps' => [],
            'call' => [],
            'app' => [],
            'risk' => [],
            'practicality' => [],
            'reliability' => [],
            'build' => [],
        ];

        $endUserCount = 0;
        $supplierCount = 0;
        $otherCount = 0;
        $positiveSignals = 0.0;
        $negativeSignals = 0.0;

        /** @var ProductEvidenceItem $item */
        foreach ($evidenceItems as $item) {
            $normalized = is_array($item->normalized_payload) ? $item->normalized_payload : [];
            $text = mb_strtolower(trim(($item->title ?? '') . ' ' . $item->body_text));
            $weight = (float) ($item->credibility_weight ?? $this->defaultCredibilityWeight((string) $item->author_type));
            $sentiment = (int) ($normalized['sentiment_score'] ?? 0);

            if ($item->author_type === 'end_user') {
                $endUserCount++;
            } elseif ($item->author_type === 'supplier') {
                $supplierCount++;
            } else {
                $otherCount++;
            }

            foreach (self::CATEGORY_KEYWORDS as $category => $keywords) {
                $matched = false;

                foreach ($keywords as $keyword) {
                    if (str_contains($text, $keyword)) {
                        $matched = true;
                        break;
                    }
                }

                if (!$matched) {
                    continue;
                }

                if ($sentiment > 0) {
                    $rubric[$category] += 0.20 * max($weight, 0.3);
                    $positiveByCategory[$category][] = Str::limit($item->body_text, 160);
                }

                if ($sentiment < 0) {
                    $rubric[$category] -= 0.30 * max($weight, 0.3);
                    $negativeByCategory[$category][] = Str::limit($item->body_text, 160);
                }
            }

            if ($sentiment > 0) {
                $positiveSignals += $weight;
            } elseif ($sentiment < 0) {
                $negativeSignals += $weight;
            }

            $this->collectExamples($evidenceExamples, $item);
        }

        foreach ($rubric as $key => $value) {
            $rubric[$key] = round(max(1.0, min(5.0, $value)), 2);
        }

        $evidenceCount = $evidenceItems->count();
        $confidenceScore = $this->calculateConfidenceScore($evidenceCount, $endUserCount, $supplierCount, $otherCount);
        $rubric['source_confidence'] = round(max(1.0, min(5.0, $confidenceScore / 20)), 2);
        $verdict = $this->determineVerdict($rubric, $confidenceScore, $positiveSignals, $negativeSignals);
        $strengths = $this->topCategories($positiveByCategory, 'positive');
        $weaknesses = $this->topCategories($negativeByCategory, 'negative');
        $recurringPraise = $this->humanizeCategoryCounts($positiveByCategory);
        $recurringComplaints = $this->humanizeCategoryCounts($negativeByCategory);
        $evidenceGaps = $this->buildEvidenceGaps($evidenceCount, $endUserCount, $supplierCount);
        $riskFlags = $this->buildRiskFlags($rubric, $confidenceScore, $supplierCount, $endUserCount);

        $summary = [
            'strengths' => $strengths,
            'weaknesses' => $weaknesses,
            'recurring_praise' => $recurringPraise,
            'recurring_complaints' => $recurringComplaints,
            'reliability_signals' => array_slice($this->uniqueValues($positiveByCategory['reliability'] ?? []), 0, 3),
            'practicality_notes' => array_slice($this->uniqueValues($positiveByCategory['kid_use_practicality'] ?? []), 0, 3),
            'battery_issues' => array_slice($this->uniqueValues($negativeByCategory['battery'] ?? []), 0, 3),
            'gps_issues' => array_slice($this->uniqueValues($negativeByCategory['gps'] ?? []), 0, 3),
            'call_issues' => array_slice($this->uniqueValues($negativeByCategory['call'] ?? []), 0, 3),
            'app_issues' => array_slice($this->uniqueValues($negativeByCategory['app'] ?? []), 0, 3),
            'risk_flags' => $riskFlags,
            'evidence_gaps' => $evidenceGaps,
            'verdict_rationale' => $this->buildVerdictRationale($displayName, $verdict, $confidenceScore, $strengths, $weaknesses),
            'comparison_notes' => $this->buildComparisonNotes($rubric, $confidenceScore),
            'rubric' => $rubric,
        ];

        return [
            'summary' => $summary,
            'comparison_ready_payload' => [
                'display_name' => $displayName,
                'verdict' => $verdict,
                'confidence_score' => $confidenceScore,
                'evidence_count' => $evidenceCount,
                'end_user_evidence_count' => $endUserCount,
                'supplier_evidence_count' => $supplierCount,
                'strengths' => $strengths,
                'weaknesses' => $weaknesses,
                'recurring_praise' => $recurringPraise,
                'recurring_complaints' => $recurringComplaints,
                'risk_flags' => $riskFlags,
                'evidence_gaps' => $evidenceGaps,
                'rubric' => $rubric,
            ],
            'confidence_score' => $confidenceScore,
            'verdict' => $verdict,
            'counts' => [
                'evidence_count' => $evidenceCount,
                'end_user_evidence_count' => $endUserCount,
                'supplier_evidence_count' => $supplierCount,
            ],
            'evidence_examples' => $evidenceExamples,
        ];
    }

    private function collectExamples(array &$examples, ProductEvidenceItem $item): void
    {
        $text = mb_strtolower($item->body_text);
        $excerpt = Str::limit($item->body_text, 160);

        if (str_contains($text, 'battery') || str_contains($text, 'charge')) {
            $examples['battery'][] = $excerpt;
        }

        if (str_contains($text, 'gps') || str_contains($text, 'location')) {
            $examples['gps'][] = $excerpt;
        }

        if (str_contains($text, 'call') || str_contains($text, 'speaker') || str_contains($text, 'mic')) {
            $examples['call'][] = $excerpt;
        }

        if (str_contains($text, 'app') || str_contains($text, 'setup') || str_contains($text, 'pair')) {
            $examples['app'][] = $excerpt;
        }

        if (str_contains($text, 'kid') || str_contains($text, 'child') || str_contains($text, 'school')) {
            $examples['practicality'][] = $excerpt;
        }

        if (str_contains($text, 'broken') || str_contains($text, 'defect') || str_contains($text, 'refund')) {
            $examples['risk'][] = $excerpt;
            $examples['reliability'][] = $excerpt;
        }

        if (str_contains($text, 'strap') || str_contains($text, 'screen') || str_contains($text, 'build')) {
            $examples['build'][] = $excerpt;
        }
    }

    private function calculateConfidenceScore(int $evidenceCount, int $endUserCount, int $supplierCount, int $otherCount): float
    {
        $score = min(80, $evidenceCount * 10);
        $score += min(15, $endUserCount * 5);
        $score += min(8, $otherCount * 2);
        $score -= min(20, max(0, $supplierCount - $endUserCount) * 4);

        return round(max(5, min(100, $score)), 2);
    }

    private function determineVerdict(array $rubric, float $confidenceScore, float $positiveSignals, float $negativeSignals): string
    {
        $average = array_sum(array_intersect_key($rubric, array_flip([
            'reliability',
            'battery',
            'gps',
            'call',
            'app',
            'kid_use_practicality',
            'build_quality',
        ]))) / 7;

        if ($confidenceScore < 35 || $average < 2.4 || $negativeSignals > ($positiveSignals + 1.0)) {
            return 'avoid_or_test_more';
        }

        if ($confidenceScore >= 60 && $average >= 3.3 && $positiveSignals >= $negativeSignals) {
            return 'strong_buy';
        }

        return 'conditional_buy';
    }

    private function topCategories(array $bucket, string $direction): array
    {
        uasort($bucket, static fn (array $left, array $right) => count($right) <=> count($left));
        $results = [];

        foreach ($bucket as $category => $items) {
            if (count($items) === 0) {
                continue;
            }

            $results[] = $direction === 'positive'
                ? $this->labelForCategory($category) . ' appears repeatedly positive'
                : $this->labelForCategory($category) . ' shows repeated complaints';
        }

        return array_slice($results, 0, 4);
    }

    private function humanizeCategoryCounts(array $bucket): array
    {
        uasort($bucket, static fn (array $left, array $right) => count($right) <=> count($left));
        $results = [];

        foreach ($bucket as $category => $items) {
            if (count($items) === 0) {
                continue;
            }

            $results[] = $this->labelForCategory($category) . ' (' . count($items) . ')';
        }

        return array_slice($results, 0, 5);
    }

    private function buildEvidenceGaps(int $evidenceCount, int $endUserCount, int $supplierCount): array
    {
        $gaps = [];

        if ($evidenceCount === 0) {
            $gaps[] = 'No public evidence was ingested yet.';
        }

        if ($evidenceCount > 0 && $evidenceCount < 3) {
            $gaps[] = 'Coverage is thin, so any verdict remains provisional.';
        }

        if ($endUserCount === 0 && $supplierCount > 0) {
            $gaps[] = 'Evidence is supplier-heavy and lacks end-user validation.';
        }

        if ($endUserCount > 0 && $endUserCount < 2) {
            $gaps[] = 'There are too few end-user voices to call the conclusion firm.';
        }

        return $gaps;
    }

    private function buildRiskFlags(array $rubric, float $confidenceScore, int $supplierCount, int $endUserCount): array
    {
        $flags = [];

        if ($rubric['reliability'] < 2.6) {
            $flags[] = 'Reliability/defect risk looks elevated.';
        }

        if ($rubric['gps'] < 2.6) {
            $flags[] = 'Location accuracy looks inconsistent.';
        }

        if ($rubric['app'] < 2.6) {
            $flags[] = 'Setup or companion app pain repeats in evidence.';
        }

        if ($confidenceScore < 40) {
            $flags[] = 'Confidence is low because evidence is sparse or uneven.';
        }

        if ($supplierCount > $endUserCount) {
            $flags[] = 'Supplier feedback outweighs end-user feedback.';
        }

        return $flags;
    }

    private function buildVerdictRationale(string $displayName, string $verdict, float $confidenceScore, array $strengths, array $weaknesses): string
    {
        $verdictLabel = match ($verdict) {
            'strong_buy' => 'looks like a strong buy',
            'conditional_buy' => 'looks like a conditional buy',
            default => 'should be treated as avoid or test more',
        };

        $strengthNote = $strengths !== [] ? implode('; ', array_slice($strengths, 0, 2)) : 'clear repeatable strengths are limited';
        $weaknessNote = $weaknesses !== [] ? implode('; ', array_slice($weaknesses, 0, 2)) : 'major repeated complaints are limited';

        return "{$displayName} {$verdictLabel}. Confidence is {$confidenceScore}/100 based on stored evidence. Stronger signals: {$strengthNote}. Main caution points: {$weaknessNote}.";
    }

    private function buildComparisonNotes(array $rubric, float $confidenceScore): array
    {
        $notes = [];

        foreach (['reliability', 'battery', 'gps', 'call', 'app', 'kid_use_practicality', 'build_quality'] as $category) {
            if ($rubric[$category] >= 3.5) {
                $notes[] = $this->labelForCategory($category) . ' is a relative strength.';
            } elseif ($rubric[$category] <= 2.5) {
                $notes[] = $this->labelForCategory($category) . ' is a relative weakness.';
            }
        }

        if ($confidenceScore < 45) {
            $notes[] = 'Any ranking should stay provisional because evidence coverage is limited.';
        }

        return array_slice($notes, 0, 6);
    }

    private function labelForCategory(string $category): string
    {
        return match ($category) {
            'kid_use_practicality' => 'Kid-use practicality',
            'build_quality' => 'Build quality',
            'source_confidence' => 'Source confidence',
            default => ucfirst($category),
        };
    }

    private function uniqueValues(array $values): array
    {
        return array_values(array_unique(array_filter($values)));
    }

    private function sanitizeText(mixed $value): string
    {
        $text = is_scalar($value) ? trim((string) $value) : '';
        $text = preg_replace('/\s+/u', ' ', $text) ?: '';

        return trim($text);
    }

    private function defaultCredibilityWeight(string $authorType): float
    {
        return match ($authorType) {
            'end_user' => 0.90,
            'supplier' => 0.45,
            default => 0.65,
        };
    }
}
