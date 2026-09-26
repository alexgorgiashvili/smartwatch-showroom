<?php

namespace App\Services\Chatbot;

use App\Models\ChatbotTestResult;
use App\Models\ChatbotTestRun;
use App\Services\Chatbot\ChatbotLabService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class TestRunnerService
{
    public function loadDataset(?string $dataset = null): Collection
    {
        $path = $this->resolveDatasetPath($dataset);

        if (!File::exists($path)) {
            return collect();
        }

        $decoded = json_decode((string) File::get($path), true);

        return is_array($decoded) ? collect($decoded)->values() : collect();
    }

    private function resolveDatasetPath(?string $dataset): string
    {
        $defaultPath = database_path('data/chatbot_golden_dataset.json');
        $normalized = trim((string) $dataset);

        if ($normalized === '') {
            return $defaultPath;
        }

        $candidates = [];

        if ($this->isAbsolutePath($normalized)) {
            $candidates[] = $normalized;
        } else {
            $candidates[] = base_path($normalized);
            $candidates[] = database_path($normalized);
            $candidates[] = database_path('data/' . ltrim($normalized, '\\/'));
            $candidates[] = database_path('data/' . basename($normalized));

            if (!str_ends_with(strtolower($normalized), '.json')) {
                $candidates[] = database_path('data/' . $normalized . '.json');
                $candidates[] = database_path('data/' . basename($normalized) . '.json');
            }
        }

        foreach (array_values(array_unique($candidates)) as $candidate) {
            if (is_string($candidate) && $candidate !== '' && File::exists($candidate)) {
                return $candidate;
            }
        }

        return $defaultPath;
    }

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('/^(?:[A-Za-z]:[\\\\\\/]|\\\\\\\\|\\/)/', $path) === 1;
    }

    /**
     * @param array<string, mixed> $case
     * @return array<string, mixed>
     */
    public function callPipeline(string $question, ?int $conversationId = null, bool $keepSession = false): array
    {
        $lab = app(ChatbotLabService::class);
        $startedAt = hrtime(true);
        $result = $lab->runManualTest($question, '', $conversationId, $keepSession);
        $metadata = is_array($result['metadata'] ?? null) ? $result['metadata'] : [];
        $measuredMs = max(1, (int) round((hrtime(true) - $startedAt) / 1_000_000));

        if (!($result['success'] ?? false)) {
            return [
                'response' => '',
                'conversation_id' => $conversationId,
                'rag_context_text' => null,
                'response_time_ms' => $measuredMs,
                'fallback_reason' => 'lab_runtime_exception',
                'error' => true,
                'intent' => null,
            ];
        }

        return [
            'response' => (string) ($result['response'] ?? ''),
            'conversation_id' => data_get($result, 'session.conversation_id', $conversationId),
            'rag_context_text' => $metadata['rag_context_text'] ?? null,
            'response_time_ms' => $measuredMs,
            'fallback_reason' => $metadata['fallback_reason'] ?? null,
            'validation_passed' => $metadata['validation_passed'] ?? null,
            'georgian_passed' => $metadata['georgian_passed'] ?? null,
            'regeneration_attempted' => $metadata['regeneration_attempted'] ?? null,
            'regeneration_succeeded' => $metadata['regeneration_succeeded'] ?? null,
            'intent' => $metadata['intent'] ?? null,
            'error' => false,
        ];
    }

    /**
     * @param array<string, mixed> $case
     * @return array<string, mixed>
     */
    public function gradeWithMatchers(array $case, string $response, array $pipeline = []): array
    {
        $expected = is_array($case['expected'] ?? null) ? $case['expected'] : [];
        $normalizedResponse = mb_strtolower($response);
        $mustContainAny = $this->stringList($expected['must_contain_any'] ?? []);
        $mustNotContain = $this->stringList($expected['must_not_contain'] ?? []);
        $stockClaim = trim((string) ($expected['stock_claim'] ?? ''));
        $guardrailShouldPass = (bool) ($expected['guardrail_should_pass'] ?? true);
        $georgianOnly = (bool) ($expected['georgian_only'] ?? true);
        $expectedIntent = trim((string) ($expected['expected_intent'] ?? ''));
        $actualIntent = trim((string) data_get($pipeline, 'intent.type', ''));

        $keywordMatch = $mustContainAny === []
            ? true
            : $this->containsAny($normalizedResponse, $mustContainAny);

        $mustNotContainViolated = $mustNotContain !== [] && $this->containsAny($normalizedResponse, $mustNotContain);

        $priceMatch = null;
        if (isset($expected['expected_price']) && is_numeric($expected['expected_price'])) {
            $priceMatch = preg_match('/(?<!\d)' . preg_quote((string) $expected['expected_price'], '/') . '(?!\d)/u', $normalizedResponse) === 1;
        }

        $stockMatch = null;
        if ($stockClaim !== '') {
            $stockMatch = str_contains($normalizedResponse, mb_strtolower($stockClaim));
        }

        $validationPassed = $pipeline['validation_passed'] ?? null;
        $guardrailPassed = is_bool($validationPassed)
            ? ($validationPassed === $guardrailShouldPass && !$mustNotContainViolated)
            : null;
        $georgianQaPassed = !$georgianOnly
            ? true
            : (is_bool($pipeline['georgian_passed'] ?? null)
                ? $pipeline['georgian_passed']
                : $this->looksGeorgian($response));
        $intentMatch = $expectedIntent === '' || $actualIntent === ''
            ? null
            : $expectedIntent === $actualIntent;
        $hasAnswerExpectation = $mustContainAny !== []
            || $mustNotContain !== []
            || $priceMatch !== null
            || $stockMatch !== null;
        $canGrade = $hasAnswerExpectation
            && $guardrailPassed !== null
            && ($expectedIntent === '' || $intentMatch !== null);
        $matcherPass = $canGrade
            ? $keywordMatch
                && !$mustNotContainViolated
                && $priceMatch !== false
                && $stockMatch !== false
                && $guardrailPassed
                && $georgianQaPassed
                && $intentMatch !== false
            : null;

        return [
            'keyword_match' => $keywordMatch,
            'price_match' => $priceMatch,
            'stock_match' => $stockMatch,
            'guardrail_passed' => $guardrailPassed,
            'georgian_qa_passed' => $georgianQaPassed,
            'intent_match' => $intentMatch,
            'entity_match' => null,
            'matcher_pass' => $matcherPass,
        ];
    }

    /**
     * @param array<string, mixed> $case
     * @return array<string, mixed>
     */
    public function gradeWithLlmJudge(array $case, string $response, string $ragContextText = ''): array
    {
        // The lab has no per-run provider budget. Do not disguise a heuristic
        // as an LLM verdict or silently make an uncapped paid call.
        return [
            'llm_accuracy' => null,
            'llm_relevance' => null,
            'llm_grammar' => null,
            'llm_completeness' => null,
            'llm_safety' => null,
            'llm_overall' => null,
            'llm_notes' => 'Ungraded: use the capped paired benchmark for a real judge.',
        ];
    }

    /**
     * @param array<string, mixed> $case
     */
    public function executeCase(array $case, int $runId, array $options = []): ChatbotTestResult
    {
        $question = trim((string) ($case['question'] ?? ''));
        $messages = is_array($case['messages'] ?? null) ? $case['messages'] : [];
        $conversationId = null;
        $pipeline = null;

        try {
            if ($messages !== []) {
                foreach ($messages as $message) {
                    $content = trim((string) data_get($message, 'content', ''));

                    if ($content === '') {
                        continue;
                    }

                    $question = $content;
                    $pipeline = $this->callPipeline($content, $conversationId, true);
                    $conversationId = is_numeric($pipeline['conversation_id'] ?? null)
                        ? (int) $pipeline['conversation_id']
                        : null;
                    if ($pipeline['error'] ?? false) {
                        break;
                    }
                }
            }

            if ($pipeline === null) {
                $pipeline = $this->callPipeline($question, $conversationId);
            }
        } finally {
            if ($conversationId !== null && $conversationId > 0) {
                app(ChatbotLabService::class)->resetSession($conversationId);
            }
        }

        $response = (string) ($pipeline['response'] ?? '');
        $matchers = $this->gradeWithMatchers($case, $response, $pipeline);
        $judge = !empty($options['use_llm_judge'])
            ? $this->gradeWithLlmJudge($case, $response, (string) ($pipeline['rag_context_text'] ?? ''))
            : [
                'llm_accuracy' => null,
                'llm_relevance' => null,
                'llm_grammar' => null,
                'llm_completeness' => null,
                'llm_safety' => null,
                'llm_overall' => null,
                'llm_notes' => 'LLM judge disabled for this run.',
            ];

        return ChatbotTestResult::create([
            'test_run_id' => $runId,
            'case_id' => (string) ($case['id'] ?? ''),
            'category' => (string) ($case['category'] ?? ''),
            'question' => $question,
            'expected_summary' => $this->buildExpectedSummary($case),
            'actual_response' => $response,
            'rag_context' => $pipeline['rag_context_text'] ?? null,
            'intent_json' => $pipeline['intent'] ?? null,
            'standalone_query' => data_get($pipeline, 'intent.standalone_query'),
            'intent_type' => data_get($pipeline, 'intent.type'),
            'intent_confidence' => data_get($pipeline, 'intent.confidence'),
            'intent_latency_ms' => data_get($pipeline, 'intent.latency_ms'),
            'status' => ($pipeline['error'] ?? false)
                ? 'error'
                : (($matchers['matcher_pass'] ?? null) === null
                    ? 'skip'
                    : ($matchers['matcher_pass'] ? 'pass' : 'fail')),
            'keyword_match' => $matchers['keyword_match'] ?? null,
            'price_match' => $matchers['price_match'] ?? null,
            'stock_match' => $matchers['stock_match'] ?? null,
            'guardrail_passed' => $matchers['guardrail_passed'] ?? null,
            'georgian_qa_passed' => $matchers['georgian_qa_passed'] ?? null,
            'intent_match' => $matchers['intent_match'] ?? null,
            'entity_match' => $matchers['entity_match'] ?? null,
            'llm_accuracy' => $judge['llm_accuracy'] ?? null,
            'llm_relevance' => $judge['llm_relevance'] ?? null,
            'llm_grammar' => $judge['llm_grammar'] ?? null,
            'llm_completeness' => $judge['llm_completeness'] ?? null,
            'llm_safety' => $judge['llm_safety'] ?? null,
            'llm_overall' => $judge['llm_overall'] ?? null,
            'llm_notes' => $judge['llm_notes'] ?? null,
            'response_time_ms' => (int) ($pipeline['response_time_ms'] ?? 0),
            'fallback_reason' => $pipeline['fallback_reason'] ?? null,
            'regeneration_attempted' => $pipeline['regeneration_attempted'] ?? null,
            'regeneration_succeeded' => $pipeline['regeneration_succeeded'] ?? null,
            'created_at' => now(),
        ]);
    }

    public function finalizeRun(int $runId): void
    {
        $run = ChatbotTestRun::with('results')->findOrFail($runId);

        $total = $run->results->count();
        $passed = $run->results->where('status', 'pass')->count();
        $failed = $run->results->whereIn('status', ['fail', 'error'])->count();
        $skipped = $run->results->where('status', 'skip')->count();

        $guardrailGraded = $run->results->filter(fn (ChatbotTestResult $result): bool => $result->guardrail_passed !== null);
        $guardrailPassed = $guardrailGraded->filter(fn (ChatbotTestResult $result): bool => $result->guardrail_passed === true)->count();
        $durationSeconds = null;
        if ($run->started_at) {
            $durationSeconds = round(max(0, now()->diffInMilliseconds($run->started_at)) / 1000, 2);
        }

        $run->update([
            'status' => 'completed',
            'total_cases' => $total,
            'passed_cases' => $passed,
            'failed_cases' => $failed,
            'skipped_cases' => $skipped,
            'accuracy_pct' => ($passed + $failed) > 0 ? round(($passed / ($passed + $failed)) * 100, 2) : null,
            'avg_llm_score' => $run->results->avg('llm_overall'),
            'guardrail_pass_rate' => $guardrailGraded->isNotEmpty()
                ? round(($guardrailPassed / $guardrailGraded->count()) * 100, 2)
                : null,
            'duration_seconds' => $durationSeconds,
            'completed_at' => now(),
        ]);
    }

    /**
     * @param array<int, string> $items
     * @return array<int, string>
     */
    private function stringList(mixed $items): array
    {
        if (is_array($items)) {
            return collect($items)
                ->map(fn ($item): string => trim((string) $item))
                ->filter(fn (string $item): bool => $item !== '')
                ->values()
                ->all();
        }

        $text = trim((string) $items);
        if ($text === '') {
            return [];
        }

        return collect(preg_split('/[\r\n,]+/u', $text) ?: [])
            ->map(fn ($item): string => trim((string) $item))
            ->filter(fn (string $item): bool => $item !== '')
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    private function looksGeorgian(string $text): bool
    {
        return preg_match('/\p{Georgian}/u', $text) === 1;
    }

    /**
     * @param array<string, mixed> $case
     */
    private function buildExpectedSummary(array $case): string
    {
        $expected = is_array($case['expected'] ?? null) ? $case['expected'] : [];
        $parts = [];

        if (!empty($case['category'])) {
            $parts[] = 'Category: ' . $case['category'];
        }

        if (!empty($expected['product_slug'])) {
            $parts[] = 'Product: ' . $expected['product_slug'];
        }

        if (!empty($expected['must_contain_any'])) {
            $parts[] = 'Must contain: ' . implode(', ', $this->stringList($expected['must_contain_any']));
        }

        return implode(' | ', $parts);
    }
}
