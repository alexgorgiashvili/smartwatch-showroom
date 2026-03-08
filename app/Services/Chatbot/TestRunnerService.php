<?php

namespace App\Services\Chatbot;

use App\Models\ChatbotTestResult;
use App\Models\ChatbotTestRun;
use App\Models\Conversation;
use App\Models\Customer;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TestRunnerService
{
    public function __construct(
        private LlmJudgeService $llmJudge,
        private ChatPipelineService $chatPipeline
    ) {
    }

    public function loadDataset(): Collection
    {
        $path = database_path('data/chatbot_golden_dataset.json');

        if (!file_exists($path)) {
            return collect();
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (!is_array($decoded)) {
            return collect();
        }

        return collect($decoded)
            ->filter(fn ($case) => is_array($case))
            ->map(fn (array $case) => $this->normalizeCase($case))
            ->filter(fn (array $case) => $this->isValidCase($case))
            ->values();
    }

    public function executeCase(array $case, int $runId, array $options = []): ChatbotTestResult
    {
        $conversationId = null;
        $finalPipeline = null;
        $question = (string) ($case['question'] ?? '');

        $messages = $case['messages'] ?? null;

        if (is_array($messages) && $messages !== []) {
            foreach ($messages as $message) {
                $content = trim((string) data_get($message, 'content', ''));

                if ($content === '') {
                    continue;
                }

                $question = $content;
                $finalPipeline = $this->callPipeline($content, $conversationId);
                $conversationId = (int) ($finalPipeline['conversation_id'] ?? $conversationId);
            }
        } else {
            $finalPipeline = $this->callPipeline($question, null);
        }

        $response = (string) ($finalPipeline['response'] ?? '');
        $intentSnapshot = $this->extractIntentSnapshot($finalPipeline ?? []);
        $matchers = $this->gradeWithMatchers($case, $response, $finalPipeline ?? []);
        $judge = $this->gradeWithLlmJudge(
            $case,
            $response,
            (string) ($finalPipeline['rag_context_text'] ?? ''),
            $options
        );
        $status = $this->computeOverallStatus($matchers, $judge, $options);

        return ChatbotTestResult::create([
            'test_run_id' => $runId,
            'case_id' => (string) ($case['id'] ?? ''),
            'category' => (string) ($case['category'] ?? 'unknown'),
            'question' => $question,
            'expected_summary' => $matchers['expected_summary'] ?? null,
            'actual_response' => $response,
            'rag_context' => (string) ($finalPipeline['rag_context_text'] ?? ''),
            'intent_json' => $intentSnapshot['intent_json'],
            'standalone_query' => $intentSnapshot['standalone_query'],
            'intent_type' => $intentSnapshot['intent_type'],
            'intent_confidence' => $intentSnapshot['intent_confidence'],
            'intent_latency_ms' => $intentSnapshot['intent_latency_ms'],
            'status' => $status,
            'keyword_match' => (bool) ($matchers['keyword_match'] ?? false),
            'price_match' => $matchers['price_match'],
            'stock_match' => $matchers['stock_match'],
            'guardrail_passed' => $matchers['guardrail_passed'],
            'georgian_qa_passed' => $matchers['georgian_qa_passed'],
            'intent_match' => $matchers['intent_match'],
            'entity_match' => $matchers['entity_match'],
            'llm_accuracy' => $judge['accuracy'],
            'llm_relevance' => $judge['relevance'],
            'llm_grammar' => $judge['georgian_grammar'],
            'llm_completeness' => $judge['completeness'],
            'llm_safety' => $judge['safety'],
            'llm_overall' => $judge['overall'],
            'llm_notes' => $judge['notes'],
            'response_time_ms' => (int) ($finalPipeline['response_time_ms'] ?? 0),
            'fallback_reason' => $finalPipeline['fallback_reason'] ?? null,
            'regeneration_attempted' => (bool) ($finalPipeline['regeneration_attempted'] ?? false),
            'regeneration_succeeded' => (bool) ($finalPipeline['regeneration_succeeded'] ?? false),
            'created_at' => now(),
        ]);
    }

    public function callPipeline(string $question, ?int $conversationId): array
    {
        $conversation = $conversationId ? Conversation::find($conversationId) : null;

        if (!$conversation) {
            $conversation = $this->createTestConversation();
            $conversationId = $conversation->id;
        }

        $result = $this->chatPipeline->process($question, $conversationId);

        return $result->toArray();
    }

    public function gradeWithMatchers(array $case, string $response, array $pipeline): array
    {
        $expected = is_array($case['expected'] ?? null) ? $case['expected'] : [];
        $intentSnapshot = $this->extractIntentSnapshot($pipeline);
        $mustContainAny = collect($expected['must_contain_any'] ?? [])->filter()->values();
        $mustNotContain = collect($expected['must_not_contain'] ?? [])->filter()->values();

        $keywordMatch = $mustContainAny->isEmpty()
            ? true
            : $mustContainAny->contains(fn ($needle) => Str::contains(mb_strtolower($response), mb_strtolower((string) $needle)));

        $forbiddenFound = $mustNotContain->contains(
            fn ($needle) => Str::contains(mb_strtolower($response), mb_strtolower((string) $needle))
        );

        $priceMatch = null;
        if (is_numeric($expected['expected_price'] ?? null)) {
            $expectedPrice = (float) $expected['expected_price'];
            $tolerancePct = (float) ($expected['price_tolerance_pct'] ?? 1);
            $priceMatch = $this->responseContainsPrice($response, $expectedPrice, $tolerancePct);
        }

        $stockMatch = null;
        if (($expected['stock_claim'] ?? null) !== null) {
            $normalized = mb_strtolower($response);
            $stockMatch = str_contains($normalized, 'მარაგ') || str_contains($normalized, 'stock');
        }

        $guardrailPassed = null;
        if (array_key_exists('guardrail_should_pass', $expected)) {
            $guardrailPassed = (bool) ($expected['guardrail_should_pass']) === (bool) ($pipeline['guard_allowed'] ?? false);
        }

        $georgianQaPassed = null;
        if (($expected['georgian_only'] ?? false) === true) {
            $georgianQaPassed = (bool) ($pipeline['georgian_passed'] ?? false);
        }

        $intentMatch = null;
        $expectedIntent = trim((string) ($expected['expected_intent'] ?? ''));
        if ($expectedIntent !== '') {
            $actualIntent = mb_strtolower((string) ($intentSnapshot['intent_type'] ?? ''));
            $intentMatch = $actualIntent !== ''
                && $actualIntent === mb_strtolower($expectedIntent);
        }

        $entityMatch = null;
        $expectedEntities = is_array($expected['expected_entities'] ?? null)
            ? $expected['expected_entities']
            : [];

        if ($expectedEntities !== []) {
            $entityMatch = $this->entitiesMatch($expectedEntities, $intentSnapshot['entities']);
        }

        $productSlugMatch = null;
        $expectedProductSlugs = collect($expected['expected_product_slugs'] ?? [])
            ->filter(fn ($slug) => is_scalar($slug) && trim((string) $slug) !== '')
            ->map(fn ($slug): string => mb_strtolower(trim((string) $slug)))
            ->values();

        if ($expectedProductSlugs->isNotEmpty()) {
            $actualProductSlugs = collect(data_get($pipeline, 'validation_context.products', []))
                ->map(fn (array $product): string => mb_strtolower(trim((string) ($product['slug'] ?? ''))))
                ->filter(fn (string $slug): bool => $slug !== '')
                ->values();

            $productSlugMatch = $expectedProductSlugs->every(
                fn (string $slug): bool => $actualProductSlugs->contains($slug)
            );
        }

        $standaloneMatch = null;
        $expectedStandalone = trim((string) ($expected['expected_standalone_query'] ?? ''));
        if ($expectedStandalone !== '') {
            $actualStandalone = trim((string) ($intentSnapshot['standalone_query'] ?? ''));
            $expectedNormalized = mb_strtolower($expectedStandalone);
            $actualNormalized = mb_strtolower($actualStandalone);

            $standaloneMatch = $actualNormalized !== ''
                && (
                    $actualNormalized === $expectedNormalized
                    || str_contains($actualNormalized, $expectedNormalized)
                    || str_contains($expectedNormalized, $actualNormalized)
                );
        }

        $requiredChecks = collect([$keywordMatch, !$forbiddenFound])
            ->merge($priceMatch !== null ? [$priceMatch] : [])
            ->merge($stockMatch !== null ? [$stockMatch] : [])
            ->merge($guardrailPassed !== null ? [$guardrailPassed] : [])
            ->merge($georgianQaPassed !== null ? [$georgianQaPassed] : [])
            ->merge($productSlugMatch !== null ? [$productSlugMatch] : []);

        $matcherPass = $requiredChecks->every(fn ($check) => $check === true);

        return [
            'keyword_match' => $keywordMatch && !$forbiddenFound,
            'price_match' => $priceMatch,
            'stock_match' => $stockMatch,
            'guardrail_passed' => $guardrailPassed,
            'georgian_qa_passed' => $georgianQaPassed,
            'intent_match' => $intentMatch,
            'entity_match' => $entityMatch,
            'product_slug_match' => $productSlugMatch,
            'standalone_match' => $standaloneMatch,
            'matcher_pass' => $matcherPass,
            'expected_summary' => $this->buildExpectedSummary($expected),
        ];
    }

    public function gradeWithLlmJudge(array $case, string $response, string $ragContext, array $options = []): array
    {
        if (!(bool) ($options['use_llm_judge'] ?? true)) {
            return [
                'accuracy' => null,
                'relevance' => null,
                'georgian_grammar' => null,
                'completeness' => null,
                'safety' => null,
                'overall' => null,
                'notes' => 'LLM judge disabled for this run.',
                'judge_pass' => true,
            ];
        }

        $expected = is_array($case['expected'] ?? null) ? $case['expected'] : [];
        $criteria = (string) ($expected['llm_judge_criteria'] ?? 'Must answer user request accurately and safely.');
        $question = (string) ($case['question'] ?? $case['id'] ?? '');

        $scores = $this->llmJudge->judge($question, $criteria, $response, $ragContext);

        return [
            'accuracy' => $scores['accuracy'] ?? null,
            'relevance' => $scores['relevance'] ?? null,
            'georgian_grammar' => $scores['georgian_grammar'] ?? null,
            'completeness' => $scores['completeness'] ?? null,
            'safety' => $scores['safety'] ?? null,
            'overall' => $scores['overall'] ?? null,
            'notes' => $scores['notes'] ?? null,
            'judge_pass' => $this->llmPasses($scores),
        ];
    }

    public function computeOverallStatus(array $matcherResults, array $llmResults, array $options = []): string
    {
        $matcherPass = (bool) ($matcherResults['matcher_pass'] ?? false);
        $judgePass = (bool) ($llmResults['judge_pass'] ?? false);

        if (!(bool) ($options['use_llm_judge'] ?? true)) {
            return $matcherPass ? 'pass' : 'fail';
        }

        return $matcherPass && $judgePass ? 'pass' : 'fail';
    }

    public function finalizeRun(int $runId): void
    {
        $run = ChatbotTestRun::findOrFail($runId);
        $results = $run->results();

        $total = (int) $results->count();
        $passed = (int) $results->where('status', 'pass')->count();
        $failed = (int) $results->where('status', 'fail')->count();
        $skipped = (int) $results->where('status', 'skip')->count();

        $accuracy = $total > 0 ? round(($passed / $total) * 100, 2) : 0.0;

        $avgLlm = (float) (ChatbotTestResult::query()
            ->where('test_run_id', $runId)
            ->whereNotNull('llm_overall')
            ->avg('llm_overall') ?? 0.0);

        $guardrailRated = ChatbotTestResult::query()
            ->where('test_run_id', $runId)
            ->whereNotNull('guardrail_passed');

        $guardrailTotal = (int) $guardrailRated->count();
        $guardrailPassed = (int) $guardrailRated->where('guardrail_passed', true)->count();
        $guardrailRate = $guardrailTotal > 0 ? round(($guardrailPassed / $guardrailTotal) * 100, 2) : null;

        $startedAt = $run->started_at;
        $completedAt = now();
        $duration = $startedAt ? round((float) $startedAt->diffInMilliseconds($completedAt) / 1000, 2) : null;

        $run->update([
            'status' => 'completed',
            'total_cases' => $total,
            'passed_cases' => $passed,
            'failed_cases' => $failed,
            'skipped_cases' => $skipped,
            'accuracy_pct' => $accuracy,
            'avg_llm_score' => $avgLlm > 0 ? round($avgLlm, 1) : null,
            'guardrail_pass_rate' => $guardrailRate,
            'duration_seconds' => $duration,
            'completed_at' => $completedAt,
        ]);
    }

    private function normalizeCase(array $case): array
    {
        if (!isset($case['expected']) || !is_array($case['expected'])) {
            $case['expected'] = [];
        }

        return $case;
    }

    private function isValidCase(array $case): bool
    {
        if (!is_string($case['id'] ?? null) || trim((string) $case['id']) === '') {
            return false;
        }

        if (!is_string($case['category'] ?? null) || trim((string) $case['category']) === '') {
            return false;
        }

        $hasQuestion = is_string($case['question'] ?? null) && trim((string) $case['question']) !== '';
        $hasMessages = is_array($case['messages'] ?? null) && ($case['messages'] ?? []) !== [];

        return $hasQuestion || $hasMessages;
    }

    private function createTestConversation(): Conversation
    {
        $customer = Customer::create([
            'name' => 'Test Runner Customer',
            'platform_user_ids' => ['test' => 'runner_' . Str::uuid()],
            'metadata' => ['source' => 'chatbot_test_runner'],
        ]);

        return Conversation::create([
            'customer_id' => $customer->id,
            'platform' => 'home',
            'platform_conversation_id' => 'test_' . Str::uuid(),
            'subject' => 'Chatbot Test Suite',
            'status' => 'active',
            'unread_count' => 0,
            'last_message_at' => now(),
        ]);
    }

    private function responseContainsPrice(string $response, float $expectedPrice, float $tolerancePct): bool
    {
        preg_match_all('/(\d+(?:[\.,]\d+)?)\s*(?:₾|ლარ(?:ი|ამდე)?|lari|gel)/iu', $response, $matches);

        $prices = collect($matches[1] ?? [])
            ->map(fn (string $value): float => (float) str_replace(',', '.', $value))
            ->filter(fn (float $value): bool => $value > 0)
            ->values();

        if ($prices->isEmpty()) {
            return false;
        }

        $tolerance = max($expectedPrice * ($tolerancePct / 100), 0.01);

        return $prices->contains(fn (float $price): bool => abs($price - $expectedPrice) <= $tolerance);
    }

    private function buildExpectedSummary(array $expected): string
    {
        $parts = [];

        if (is_numeric($expected['expected_price'] ?? null)) {
            $parts[] = 'Price: ' . $expected['expected_price'];
        }

        if (!empty($expected['stock_claim'])) {
            $parts[] = 'Stock: ' . $expected['stock_claim'];
        }

        $mustContain = collect($expected['must_contain_any'] ?? [])->filter()->values()->all();
        if ($mustContain !== []) {
            $parts[] = 'Keywords: ' . implode(', ', $mustContain);
        }

        if (!empty($expected['expected_intent'])) {
            $parts[] = 'Intent: ' . $expected['expected_intent'];
        }

        if (!empty($expected['expected_standalone_query'])) {
            $parts[] = 'Standalone: ' . $expected['expected_standalone_query'];
        }

        $productSlugs = collect($expected['expected_product_slugs'] ?? [])->filter()->values()->all();
        if ($productSlugs !== []) {
            $parts[] = 'Slugs: ' . implode(', ', $productSlugs);
        }

        return implode(' | ', $parts);
    }

    private function extractIntentSnapshot(array $pipeline): array
    {
        $intent = $pipeline['intent_result'] ?? null;

        if ($intent instanceof IntentResult) {
            $entities = [
                'brand' => $intent->brand(),
                'model' => $intent->model(),
                'product_slug_hint' => $intent->productSlugHint(),
                'color' => $intent->color(),
                'category' => $intent->category(),
                'search_keywords' => $intent->searchKeywords(),
            ];

            return [
                'intent_json' => [
                    'standalone_query' => $intent->standaloneQuery(),
                    'intent' => $intent->intent(),
                    'confidence' => $intent->confidence(),
                    'latency_ms' => $intent->latencyMs(),
                    'is_fallback' => $intent->isFallback(),
                    'needs_product_data' => $intent->needsProductData(),
                    'is_out_of_domain' => $intent->isOutOfDomain(),
                    'entities' => $entities,
                ],
                'standalone_query' => $intent->standaloneQuery(),
                'intent_type' => $intent->intent(),
                'intent_confidence' => $intent->confidence(),
                'intent_latency_ms' => $intent->latencyMs(),
                'entities' => $entities,
            ];
        }

        if (is_array($intent)) {
            $entities = is_array($intent['entities'] ?? null) ? $intent['entities'] : [];

            return [
                'intent_json' => $intent,
                'standalone_query' => (string) ($intent['standalone_query'] ?? ''),
                'intent_type' => (string) ($intent['intent'] ?? ''),
                'intent_confidence' => is_numeric($intent['confidence'] ?? null) ? (float) $intent['confidence'] : null,
                'intent_latency_ms' => is_numeric($intent['latency_ms'] ?? null) ? (int) $intent['latency_ms'] : null,
                'entities' => $entities,
            ];
        }

        return [
            'intent_json' => null,
            'standalone_query' => null,
            'intent_type' => null,
            'intent_confidence' => null,
            'intent_latency_ms' => null,
            'entities' => [],
        ];
    }

    private function entitiesMatch(array $expectedEntities, array $actualEntities): bool
    {
        foreach ($expectedEntities as $key => $expectedValue) {
            $actualValue = data_get($actualEntities, (string) $key);

            if (is_array($expectedValue)) {
                $expectedNormalized = collect($expectedValue)
                    ->filter(fn ($value) => is_scalar($value))
                    ->map(fn ($value) => mb_strtolower(trim((string) $value)))
                    ->filter(fn (string $value) => $value !== '')
                    ->values();

                $actualNormalized = collect(is_array($actualValue) ? $actualValue : [$actualValue])
                    ->filter(fn ($value) => is_scalar($value))
                    ->map(fn ($value) => mb_strtolower(trim((string) $value)))
                    ->filter(fn (string $value) => $value !== '')
                    ->values();

                if ($expectedNormalized->diff($actualNormalized)->isNotEmpty()) {
                    return false;
                }

                continue;
            }

            $expectedScalar = mb_strtolower(trim((string) $expectedValue));
            $actualScalar = mb_strtolower(trim((string) $actualValue));

            if ($expectedScalar !== $actualScalar) {
                return false;
            }
        }

        return true;
    }

    private function llmPasses(?array $scores): bool
    {
        if (!is_array($scores)) {
            return false;
        }

        $overall = $scores['overall'] ?? null;

        if (!is_numeric($overall) || (float) $overall < 3.5) {
            return false;
        }

        foreach (['accuracy', 'relevance', 'georgian_grammar', 'completeness', 'safety'] as $field) {
            if (!isset($scores[$field]) || !is_numeric($scores[$field]) || (int) $scores[$field] < 2) {
                return false;
            }
        }

        return true;
    }
}
