<?php

namespace App\Services\Chatbot;

use App\Models\ChatbotTestResult;
use App\Models\ChatbotTrainingCase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\Schema;

class ChatbotTrainingCaseService
{
    /**
     * @param array<string, mixed> $filters
     */
    public function listCases(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        if (!$this->isReady()) {
            return new Paginator([], 0, $perPage, 1, [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]);
        }

        $query = ChatbotTrainingCase::query()->latest('id');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('title', 'like', '%' . $search . '%')
                    ->orWhere('prompt', 'like', '%' . $search . '%')
                    ->orWhere('reviewer_notes', 'like', '%' . $search . '%');
            });
        }

        $status = (string) ($filters['status'] ?? 'all');
        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'inactive') {
            $query->inactive();
        }

        $tag = trim((string) ($filters['tag'] ?? ''));
        if ($tag !== '') {
            $query->whereJsonContains('tags_json', $tag);
        }

        return $query->paginate($perPage)->appends([
            'search' => $search,
            'status' => $status,
            'tag' => $tag,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createCase(array $data, ?int $createdBy = null): ChatbotTrainingCase
    {
        return ChatbotTrainingCase::create($this->normalizePayload($data, $createdBy, true));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateCase(ChatbotTrainingCase $trainingCase, array $data): ChatbotTrainingCase
    {
        $trainingCase->update($this->normalizePayload($data, $trainingCase->created_by, false));

        return $trainingCase->fresh();
    }

    public function createFromResult(ChatbotTestResult $result, ?int $createdBy = null): ChatbotTrainingCase
    {
        $existing = ChatbotTrainingCase::query()
            ->where('source', 'lab_run_result')
            ->where('source_reference', (string) $result->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $intentJson = is_array($result->intent_json ?? null) ? $result->intent_json : [];
        $entities = is_array($intentJson['entities'] ?? null) ? $intentJson['entities'] : [];
        $productSlugHint = trim((string) ($entities['product_slug_hint'] ?? ''));
        $notes = array_filter([
            'Promoted from Chatbot Lab run result #' . $result->id,
            'Source run #' . $result->test_run_id,
            $result->admin_feedback ? 'Observation: ' . trim((string) $result->admin_feedback) : null,
            $result->llm_notes ? 'Judge notes: ' . trim((string) $result->llm_notes) : null,
            $result->actual_response ? 'Last bot response: ' . trim((string) $result->actual_response) : null,
        ]);

        return ChatbotTrainingCase::create([
            'title' => '[' . $result->case_id . '] ' . mb_strimwidth(trim((string) $result->question), 0, 120, '...'),
            'prompt' => trim((string) $result->question),
            'conversation_context_json' => [],
            'expected_intent' => $this->nullableString($result->intent_type),
            'expected_keywords_json' => [],
            'expected_product_slugs_json' => $productSlugHint !== '' ? [$productSlugHint] : [],
            'expected_price_behavior' => null,
            'expected_stock_behavior' => null,
            'reviewer_notes' => implode("\n\n", $notes),
            'tags_json' => array_values(array_filter(array_unique([
                'promoted-from-run',
                trim((string) $result->category),
                trim((string) $result->status),
            ]))),
            'is_active' => true,
            'source' => 'lab_run_result',
            'source_reference' => (string) $result->id,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * @return array<string, int>
     */
    public function stats(): array
    {
        if (!$this->isReady()) {
            return [
                'total' => 0,
                'active' => 0,
                'inactive' => 0,
            ];
        }

        return [
            'total' => ChatbotTrainingCase::query()->count(),
            'active' => ChatbotTrainingCase::query()->where('is_active', true)->count(),
            'inactive' => ChatbotTrainingCase::query()->where('is_active', false)->count(),
        ];
    }

    public function isReady(): bool
    {
        return Schema::hasTable('chatbot_training_cases');
    }

    /**
     * @param iterable<int, ChatbotTrainingCase> $cases
     * @return array<int, array<string, mixed>>
     */
    public function diagnosticsForCases(iterable $cases): array
    {
        $diagnostics = [];

        foreach ($cases as $case) {
            $diagnostics[$case->id] = $this->diagnoseCase($case);
        }

        return $diagnostics;
    }

    /**
     * @param array<int, int> $caseIds
     * @return array{blocking_count:int,warning_count:int,diagnostics:array<int, array<string, mixed>>,blocking_messages:list<string>,warning_messages:list<string>}
     */
    public function preflightSelection(array $caseIds): array
    {
        if ($caseIds === []) {
            return [
                'blocking_count' => 0,
                'warning_count' => 0,
                'diagnostics' => [],
                'blocking_messages' => [],
                'warning_messages' => [],
                'blocking_case_titles' => [],
                'warning_case_titles' => [],
            ];
        }

        $cases = ChatbotTrainingCase::query()
            ->whereIn('id', $caseIds)
            ->orderBy('id')
            ->get();

        $diagnostics = $this->diagnosticsForCases($cases);
        $blockingMessages = [];
        $warningMessages = [];
        $blockingCaseTitles = [];
        $warningCaseTitles = [];
        $blockingCount = 0;
        $warningCount = 0;

        foreach ($cases as $case) {
            $diagnostic = $diagnostics[$case->id] ?? [];
            $blockingIssues = $diagnostic['blocking_issues'] ?? [];
            $warningIssues = $diagnostic['warning_issues'] ?? [];

            if ($blockingIssues !== []) {
                $blockingCount++;
                $blockingMessages[] = $case->title . ': ' . implode('; ', $blockingIssues);
                $blockingCaseTitles[] = $case->title;
            }

            if ($warningIssues !== []) {
                $warningCount++;
                $warningMessages[] = $case->title . ': ' . implode('; ', $warningIssues);
                $warningCaseTitles[] = $case->title;
            }
        }

        return [
            'blocking_count' => $blockingCount,
            'warning_count' => $warningCount,
            'diagnostics' => $diagnostics,
            'blocking_messages' => $blockingMessages,
            'warning_messages' => $warningMessages,
            'blocking_case_titles' => $blockingCaseTitles,
            'warning_case_titles' => $warningCaseTitles,
        ];
    }

    /**
     * @return array{blocking_issues:list<string>,warning_issues:list<string>,duplicate_case_ids:list<int>,health:string}
     */
    public function diagnoseCase(ChatbotTrainingCase $case): array
    {
        return $this->diagnosePayload([
            'prompt' => (string) $case->prompt,
            'expected_intent' => $case->expected_intent,
            'expected_keywords_json' => $case->expected_keywords_json ?? [],
            'expected_product_slugs_json' => $case->expected_product_slugs_json ?? [],
            'expected_price_behavior' => $case->expected_price_behavior,
            'expected_stock_behavior' => $case->expected_stock_behavior,
            'reviewer_notes' => $case->reviewer_notes,
        ], $case->id);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{blocking_issues:list<string>,warning_issues:list<string>,duplicate_case_ids:list<int>,health:string}
     */
    public function previewDiagnostics(array $data, ?int $ignoreCaseId = null): array
    {
        $normalized = $this->normalizePayload($data, null, false);

        return $this->diagnosePayload($normalized, $ignoreCaseId);
    }

    /**
     * @return list<int>
     */
    private function findDuplicateCaseIdsForPrompt(string $prompt, ?int $ignoreCaseId = null): array
    {
        $normalizedPrompt = $this->normalizePromptForComparison($prompt);
        if ($normalizedPrompt === '') {
            return [];
        }

        return ChatbotTrainingCase::query()
            ->when($ignoreCaseId !== null, fn ($query) => $query->where('id', '!=', $ignoreCaseId))
            ->get(['id', 'prompt'])
            ->filter(function (ChatbotTrainingCase $candidate) use ($normalizedPrompt): bool {
                $candidatePrompt = $this->normalizePromptForComparison((string) $candidate->prompt);

                if ($candidatePrompt === '') {
                    return false;
                }

                if ($candidatePrompt === $normalizedPrompt) {
                    return true;
                }

                return $this->promptSimilarity($normalizedPrompt, $candidatePrompt) >= 0.82;
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{blocking_issues:list<string>,warning_issues:list<string>,duplicate_case_ids:list<int>,health:string}
     */
    private function diagnosePayload(array $payload, ?int $ignoreCaseId = null): array
    {
        $blockingIssues = [];
        $warningIssues = [];

        $prompt = trim((string) ($payload['prompt'] ?? ''));
        $expectedIntent = $this->nullableString($payload['expected_intent'] ?? null);
        $expectedKeywords = collect($payload['expected_keywords_json'] ?? [])->filter(fn ($item): bool => is_string($item) && trim($item) !== '');
        $expectedSlugs = collect($payload['expected_product_slugs_json'] ?? [])->filter(fn ($item): bool => is_string($item) && trim($item) !== '');
        $expectedPriceBehavior = $this->nullableString($payload['expected_price_behavior'] ?? null);
        $expectedStockBehavior = $this->nullableString($payload['expected_stock_behavior'] ?? null);
        $reviewerNotes = $this->nullableString($payload['reviewer_notes'] ?? null);

        $hasExpectation = $expectedIntent !== null
            || $expectedKeywords->isNotEmpty()
            || $expectedSlugs->isNotEmpty()
            || $expectedPriceBehavior !== null
            || $expectedStockBehavior !== null;

        if (!$hasExpectation) {
            $blockingIssues[] = 'No expected intent, keywords, product slugs, price behavior, or stock behavior is configured.';
        }

        $intentNeedsGrounding = in_array((string) $expectedIntent, ['recommendation', 'price_query', 'product_query', 'stock_query'], true);
        if ($intentNeedsGrounding && $expectedKeywords->isEmpty() && $expectedSlugs->isEmpty() && $expectedPriceBehavior === null && $expectedStockBehavior === null) {
            $warningIssues[] = 'Intent suggests grounded assertions, but keyword/product/price/stock expectations are still empty.';
        }

        if (mb_strlen($prompt) < 8) {
            $warningIssues[] = 'Prompt is very short and may not represent a realistic operator test.';
        }

        if ($reviewerNotes === null) {
            $warningIssues[] = 'Reviewer notes are empty, so future operators may not know what this case protects.';
        }

        $duplicateCaseIds = $this->findDuplicateCaseIdsForPrompt($prompt, $ignoreCaseId);
        if ($duplicateCaseIds !== []) {
            $warningIssues[] = 'Possible duplicate prompt detected in case #' . implode(', #', $duplicateCaseIds) . '.';
        }

        $health = $blockingIssues !== [] ? 'blocking' : ($warningIssues !== [] ? 'warning' : 'healthy');

        return [
            'blocking_issues' => $blockingIssues,
            'warning_issues' => $warningIssues,
            'duplicate_case_ids' => $duplicateCaseIds,
            'health' => $health,
        ];
    }

    private function normalizePromptForComparison(string $prompt): string
    {
        $normalized = mb_strtolower(trim($prompt));
        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function promptSimilarity(string $left, string $right): float
    {
        $leftTokens = array_values(array_filter(explode(' ', $left), fn (string $token): bool => $token !== ''));
        $rightTokens = array_values(array_filter(explode(' ', $right), fn (string $token): bool => $token !== ''));

        if ($leftTokens === [] || $rightTokens === []) {
            return 0.0;
        }

        $leftUnique = array_values(array_unique($leftTokens));
        $rightUnique = array_values(array_unique($rightTokens));
        $intersection = array_intersect($leftUnique, $rightUnique);
        $union = array_unique(array_merge($leftUnique, $rightUnique));

        if ($union === []) {
            return 0.0;
        }

        return count($intersection) / count($union);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizePayload(array $data, ?int $createdBy, bool $creating): array
    {
        $payload = [
            'title' => trim((string) ($data['title'] ?? '')),
            'prompt' => trim((string) ($data['prompt'] ?? '')),
            'conversation_context_json' => $this->parseLines((string) ($data['conversation_context'] ?? '')),
            'expected_intent' => $this->nullableString($data['expected_intent'] ?? null),
            'expected_keywords_json' => $this->parseFlexibleList((string) ($data['expected_keywords'] ?? '')),
            'expected_product_slugs_json' => $this->parseFlexibleList((string) ($data['expected_product_slugs'] ?? '')),
            'expected_price_behavior' => $this->nullableString($data['expected_price_behavior'] ?? null),
            'expected_stock_behavior' => $this->nullableString($data['expected_stock_behavior'] ?? null),
            'reviewer_notes' => $this->nullableString($data['reviewer_notes'] ?? null),
            'tags_json' => $this->parseFlexibleList((string) ($data['tags'] ?? '')),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'source' => $this->nullableString($data['source'] ?? null) ?? 'manual',
            'source_reference' => $this->nullableString($data['source_reference'] ?? null),
        ];

        if ($creating) {
            $payload['created_by'] = $createdBy;
        }

        return $payload;
    }

    /**
     * @return list<string>
     */
    private function parseLines(string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/u', $value) ?: [])
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn (string $item): bool => $item !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function parseFlexibleList(string $value): array
    {
        return collect(preg_split('/[\r\n,]+/u', $value) ?: [])
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn (string $item): bool => $item !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
