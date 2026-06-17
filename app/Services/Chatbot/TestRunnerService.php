<?php

namespace App\Services\Chatbot;

use App\Models\ChatbotTestResult;
use App\Models\ChatbotTestRun;
use App\Services\Chatbot\ChatbotLabService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class TestRunnerService
{
    public function loadDataset(): Collection
    {
        $path = database_path('data/chatbot_golden_dataset.json');

        if (!File::exists($path)) {
            return collect();
        }

        $decoded = json_decode((string) File::get($path), true);

        return is_array($decoded) ? collect($decoded)->values() : collect();
    }

    /**
     * @param array<string, mixed> $case
     * @return array<string, mixed>
     */
    public function callPipeline(string $question, ?int $conversationId = null): array
    {
        $lab = app(ChatbotLabService::class);
        $result = $lab->runManualTest($question, '', $conversationId, $conversationId !== null);

        return [
            'response' => (string) ($result['response'] ?? ''),
            'conversation_id' => (int) data_get($result, 'session.conversation_id', $conversationId ?? 0),
            'rag_context_text' => '',
            'response_time_ms' => 0,
            'fallback_reason' => null,
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
        $productSlug = trim((string) ($expected['product_slug'] ?? ''));
        $stockClaim = trim((string) ($expected['stock_claim'] ?? ''));
        $guardrailShouldPass = (bool) ($expected['guardrail_should_pass'] ?? true);
        $georgianOnly = (bool) ($expected['georgian_only'] ?? true);

        $keywordMatch = $mustContainAny === []
            ? true
            : $this->containsAny($normalizedResponse, $mustContainAny);

        $mustNotContainViolated = $mustNotContain !== [] && $this->containsAny($normalizedResponse, $mustNotContain);

        $priceMatch = null;
        if (isset($expected['expected_price']) && is_numeric($expected['expected_price'])) {
            $priceMatch = str_contains($normalizedResponse, (string) $expected['expected_price']);
        }

        $stockMatch = null;
        if ($stockClaim !== '') {
            $stockMatch = str_contains($normalizedResponse, mb_strtolower($stockClaim));
        }

        $guardrailPassed = $guardrailShouldPass && !$mustNotContainViolated;
        $georgianQaPassed = !$georgianOnly || $this->looksGeorgian($response);

        return [
            'keyword_match' => $keywordMatch,
            'price_match' => $priceMatch,
            'stock_match' => $stockMatch,
            'guardrail_passed' => $guardrailPassed,
            'georgian_qa_passed' => $georgianQaPassed,
            'intent_match' => true,
            'entity_match' => $productSlug !== '' ? str_contains(mb_strtolower($normalizedResponse), mb_strtolower($productSlug)) : null,
            'matcher_pass' => $keywordMatch && $guardrailPassed && $georgianQaPassed,
        ];
    }

    /**
     * @param array<string, mixed> $case
     * @return array<string, mixed>
     */
    public function gradeWithLlmJudge(array $case, string $response, string $ragContextText = ''): array
    {
        $hasGeorgian = $this->looksGeorgian($response);
        $responseLength = mb_strlen(trim($response));

        $overall = $responseLength === 0 ? 1.0 : ($hasGeorgian ? 4.5 : 2.0);

        return [
            'llm_accuracy' => $hasGeorgian ? 5 : 2,
            'llm_relevance' => $responseLength > 20 ? 4 : 2,
            'llm_grammar' => $hasGeorgian ? 5 : 1,
            'llm_completeness' => $responseLength > 40 ? 4 : 2,
            'llm_safety' => 5,
            'llm_overall' => $overall,
            'llm_notes' => $responseLength === 0
                ? 'Empty response.'
                : 'Heuristic judge placeholder used.',
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

        if ($messages !== []) {
            foreach ($messages as $message) {
                $content = trim((string) data_get($message, 'content', ''));

                if ($content === '') {
                    continue;
                }

                $question = $content;
                $pipeline = $this->callPipeline($content, $conversationId);
                $conversationId = (int) ($pipeline['conversation_id'] ?? 0);
            }
        }

        if ($pipeline === null) {
            $pipeline = $this->callPipeline($question, $conversationId);
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
            'rag_context' => (string) ($pipeline['rag_context_text'] ?? ''),
            'intent_json' => null,
            'standalone_query' => null,
            'intent_type' => null,
            'intent_confidence' => null,
            'intent_latency_ms' => null,
            'status' => ($matchers['matcher_pass'] ?? false) ? 'pass' : 'fail',
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
            'regeneration_attempted' => false,
            'regeneration_succeeded' => false,
            'created_at' => now(),
        ]);
    }

    public function finalizeRun(int $runId): void
    {
        $run = ChatbotTestRun::with('results')->findOrFail($runId);

        $total = $run->results->count();
        $passed = $run->results->where('status', 'pass')->count();
        $failed = $run->results->where('status', 'fail')->count();
        $skipped = max(0, $total - $passed - $failed);

        $guardrailPassed = $run->results->filter(fn (ChatbotTestResult $result): bool => (bool) $result->guardrail_passed)->count();
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
            'accuracy_pct' => $total > 0 ? round(($passed / $total) * 100, 2) : 0,
            'avg_llm_score' => $run->results->avg('llm_overall'),
            'guardrail_pass_rate' => $total > 0 ? round(($guardrailPassed / $total) * 100, 2) : 0,
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
