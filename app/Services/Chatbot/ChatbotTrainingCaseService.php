<?php

namespace App\Services\Chatbot;

use App\Models\ChatbotTrainingCase;
use App\Models\ChatbotTestResult;
use Illuminate\Support\Facades\Log;

class ChatbotTrainingCaseService
{
    public function stats()
    {
        return [
            'total_cases' => ChatbotTrainingCase::count(),
            'active_cases' => ChatbotTrainingCase::where('is_active', true)->count(),
            'by_category' => [],
        ];
    }

    public function isReady()
    {
        return true;
    }

    public function listCases(array $filters = [])
    {
        $query = ChatbotTrainingCase::query();

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('prompt', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('title', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('is_active', $filters['status'] === 'active');
        }

        if (!empty($filters['tag'])) {
            $query->whereJsonContains('tags_json', $filters['tag']);
        }

        return $query->orderBy('created_at', 'desc')->paginate(20);
    }

    public function diagnosticsForCases($cases)
    {
        return collect($cases)->mapWithKeys(fn ($case) => [
            $case->id ?? $case['id'] ?? 0 => [
                'health' => 'healthy',
                'blocking_issues' => [],
                'warning_issues' => [],
                'duplicate_case_ids' => [],
                'recent_pass_rate' => 0,
                'total_runs' => 0,
            ]
        ])->toArray();
    }

    public function preflightSelection(array $caseIds)
    {
        return [
            'blocking_count' => 0,
            'blocking_messages' => [],
            'warning_count' => 0,
            'warning_messages' => [],
        ];
    }

    public function createCase(array $data, $userId = null)
    {
        return ChatbotTrainingCase::create($this->normalizeCasePayload($data, $userId));
    }

    public function previewDiagnostics(array $payload, $caseId = null)
    {
        return [
            'validation_passed' => true,
            'warnings' => [],
        ];
    }

    public function selectableCases()
    {
        return ChatbotTrainingCase::where('is_active', true)
            ->orderBy('title')
            ->orderBy('prompt')
            ->get()
            ->map(fn ($case) => [
                'id' => $case->id,
                'title' => $case->title,
                'prompt' => $case->prompt,
                'tags' => $case->tags_json ?? [],
            ]);
    }

    public function createFromResult($resultOrData, $userId = null)
    {
        try {
            if (is_array($resultOrData)) {
                $data = $resultOrData;
            } else {
                $data = [
                    'title' => $resultOrData->category ?? 'Test Case',
                    'prompt' => $resultOrData->question ?? '',
                    'expected_intent' => null,
                    'expected_keywords_json' => [],
                    'tags_json' => [],
                    'is_active' => true,
                ];
            }

            $case = ChatbotTrainingCase::create([
                'title' => $data['title'] ?? 'Test Case',
                'prompt' => $data['prompt'] ?? $data['question'] ?? '',
                'expected_intent' => $data['expected_intent'] ?? null,
                'expected_keywords_json' => $data['expected_keywords_json'] ?? $data['expected_keywords'] ?? [],
                'expected_product_slugs_json' => $data['expected_product_slugs_json'] ?? $data['expected_product_ids'] ?? [],
                'tags_json' => $data['tags_json'] ?? $data['tags'] ?? [],
                'is_active' => $data['is_active'] ?? true,
                'reviewer_notes' => $data['reviewer_notes'] ?? $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            return $case;
        } catch (\Exception $e) {
            Log::error('ChatbotTrainingCaseService createFromResult failed', [
                'error' => $e->getMessage(),
                'data' => $data ?? [],
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getCaseDiagnostics($caseId)
    {
        $case = ChatbotTrainingCase::findOrFail($caseId);

        $recentResults = ChatbotTestResult::where('training_case_id', $caseId)
            ->with('testRun')
            ->latest()
            ->limit(10)
            ->get();

        return [
            'case' => $case,
            'recent_results' => $recentResults,
            'success_rate' => $recentResults->isEmpty()
                ? 0
                : round(($recentResults->where('status', 'passed')->count() / $recentResults->count()) * 100, 1),
        ];
    }

    public function updateCase($caseId, array $data)
    {
        try {
            $case = ChatbotTrainingCase::findOrFail($caseId);
            $case->update($this->normalizeCasePayload($data, $case->created_by));

            return ['success' => true];
        } catch (\Exception $e) {
            Log::error('ChatbotTrainingCaseService updateCase failed', [
                'error' => $e->getMessage(),
                'case_id' => $caseId,
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function deleteCase($caseId)
    {
        try {
            ChatbotTrainingCase::findOrFail($caseId)->delete();
            return ['success' => true];
        } catch (\Exception $e) {
            Log::error('ChatbotTrainingCaseService deleteCase failed', [
                'error' => $e->getMessage(),
                'case_id' => $caseId,
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeCasePayload(array $data, $userId = null): array
    {
        return [
            'title' => trim((string) ($data['title'] ?? '')),
            'prompt' => trim((string) ($data['prompt'] ?? '')),
            'conversation_context_json' => $this->linesFromInput($data['conversation_context'] ?? $data['conversation_context_json'] ?? null),
            'expected_intent' => $this->nullableString($data['expected_intent'] ?? null),
            'expected_keywords_json' => $this->listFromInput($data['expected_keywords'] ?? $data['expected_keywords_json'] ?? null),
            'expected_product_slugs_json' => $this->listFromInput($data['expected_product_slugs'] ?? $data['expected_product_slugs_json'] ?? null),
            'expected_price_behavior' => $this->nullableString($data['expected_price_behavior'] ?? null),
            'expected_stock_behavior' => $this->nullableString($data['expected_stock_behavior'] ?? null),
            'reviewer_notes' => $this->nullableString($data['reviewer_notes'] ?? null),
            'tags_json' => $this->listFromInput($data['tags'] ?? $data['tags_json'] ?? null),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'source' => $this->nullableString($data['source'] ?? null) ?: 'manual',
            'source_reference' => $this->nullableString($data['source_reference'] ?? null),
            'created_by' => $userId,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function listFromInput(mixed $value): array
    {
        if (is_array($value)) {
            return collect($value)
                ->map(fn ($item): string => trim((string) $item))
                ->filter(fn (string $item): bool => $item !== '')
                ->values()
                ->all();
        }

        $text = trim((string) $value);
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
     * @return array<int, string>
     */
    private function linesFromInput(mixed $value): array
    {
        if (is_array($value)) {
            return collect($value)
                ->map(fn ($item): string => trim((string) $item))
                ->filter(fn (string $item): bool => $item !== '')
                ->values()
                ->all();
        }

        $text = trim((string) $value);
        if ($text === '') {
            return [];
        }

        return collect(preg_split('/\r?\n/u', $text) ?: [])
            ->map(fn ($item): string => trim((string) $item))
            ->filter(fn (string $item): bool => $item !== '')
            ->values()
            ->all();
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
