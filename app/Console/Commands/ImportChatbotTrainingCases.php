<?php

namespace App\Console\Commands;

use App\Models\ChatbotTrainingCase;
use App\Services\Chatbot\TestRunnerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ImportChatbotTrainingCases extends Command
{
    protected $signature = 'chatbot:import-training-cases
        {--limit= : Limit number of legacy cases to import}
        {--dry-run : Preview import summary without writing to the database}';

    protected $description = 'Import legacy chatbot golden dataset cases into the DB-backed chatbot training cases table.';

    public function handle(TestRunnerService $runner): int
    {
        if (!Schema::hasTable('chatbot_training_cases')) {
            $this->error('chatbot_training_cases table does not exist. Run php artisan migrate first.');

            return Command::FAILURE;
        }

        $dataset = $runner->loadDataset();

        if ($dataset->isEmpty()) {
            $this->warn('Legacy dataset is empty or missing.');

            return Command::SUCCESS;
        }

        $limit = $this->option('limit');
        if ($limit !== null) {
            $dataset = $dataset->take(max(1, (int) $limit))->values();
        }

        $isDryRun = (bool) $this->option('dry-run');
        $created = 0;
        $updated = 0;

        $bar = $this->output->createProgressBar($dataset->count());
        $bar->start();

        foreach ($dataset as $case) {
            $payload = $this->mapLegacyCase($case);

            if (!$isDryRun) {
                $existing = ChatbotTrainingCase::query()
                    ->where('source', 'legacy_json')
                    ->where('source_reference', $payload['source_reference'])
                    ->first();

                if ($existing) {
                    $existing->update($payload);
                    $updated++;
                } else {
                    ChatbotTrainingCase::create($payload);
                    $created++;
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $summaryRows = [
            ['Dataset cases', (string) $dataset->count()],
            ['Dry run', $isDryRun ? 'yes' : 'no'],
        ];

        if (!$isDryRun) {
            $summaryRows[] = ['Created', (string) $created];
            $summaryRows[] = ['Updated', (string) $updated];
            $summaryRows[] = ['Legacy cases in DB', (string) ChatbotTrainingCase::query()->where('source', 'legacy_json')->count()];
        }

        $this->table(['Metric', 'Value'], $summaryRows);

        $sample = $dataset->take(5)->map(function (array $case): array {
            return [
                (string) ($case['id'] ?? ''),
                (string) ($case['category'] ?? ''),
                mb_strimwidth((string) ($case['question'] ?? ''), 0, 70, '...'),
            ];
        })->all();

        $this->table(['Legacy ID', 'Category', 'Question'], $sample);

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $case
     * @return array<string, mixed>
     */
    private function mapLegacyCase(array $case): array
    {
        $expected = is_array($case['expected'] ?? null) ? $case['expected'] : [];
        $messages = collect($case['messages'] ?? [])
            ->filter(fn ($message): bool => is_array($message))
            ->map(fn (array $message): string => trim((string) ($message['content'] ?? '')))
            ->filter(fn (string $content): bool => $content !== '')
            ->values();

        $prompt = $messages->last() ?: trim((string) ($case['question'] ?? ''));
        $context = $messages->slice(0, -1)->values()->all();

        $productSlug = trim((string) ($expected['product_slug'] ?? ''));
        $expectedProductSlugs = $productSlug !== '' ? [$productSlug] : [];

        $reviewerNotes = $this->buildReviewerNotes($case, $expected);
        $titlePrefix = trim((string) ($case['id'] ?? ''));

        return [
            'title' => $titlePrefix !== '' ? '[' . $titlePrefix . '] ' . $prompt : $prompt,
            'prompt' => $prompt,
            'conversation_context_json' => $context,
            'expected_intent' => $this->nullableString($expected['expected_intent'] ?? ($case['category'] ?? null)),
            'expected_keywords_json' => $this->stringList($expected['must_contain_any'] ?? []),
            'expected_product_slugs_json' => $expectedProductSlugs,
            'expected_price_behavior' => $this->mapPriceBehavior($expected),
            'expected_stock_behavior' => $this->nullableString($expected['stock_claim'] ?? null),
            'reviewer_notes' => $reviewerNotes,
            'tags_json' => $this->stringList($case['tags'] ?? []),
            'is_active' => true,
            'source' => 'legacy_json',
            'source_reference' => $this->nullableString($case['id'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $case
     * @param array<string, mixed> $expected
     */
    private function buildReviewerNotes(array $case, array $expected): string
    {
        $lines = [
            'Imported from legacy chatbot_golden_dataset.json',
            'Legacy category: ' . ((string) ($case['category'] ?? 'unknown')),
        ];

        $standalone = $this->nullableString($expected['expected_standalone_query'] ?? null);
        if ($standalone !== null) {
            $lines[] = 'Expected standalone query: ' . $standalone;
        }

        $mustNotContain = $this->stringList($expected['must_not_contain'] ?? []);
        if ($mustNotContain !== []) {
            $lines[] = 'Must not contain: ' . implode(', ', $mustNotContain);
        }

        if (is_array($expected['expected_entities'] ?? null) && $expected['expected_entities'] !== []) {
            $lines[] = 'Expected entities: ' . json_encode($expected['expected_entities'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $criteria = $this->nullableString($expected['llm_judge_criteria'] ?? null);
        if ($criteria !== null) {
            $lines[] = 'Legacy judge criteria: ' . $criteria;
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $expected
     */
    private function mapPriceBehavior(array $expected): ?string
    {
        if (is_numeric($expected['expected_price'] ?? null)) {
            return 'exact_price:' . (string) $expected['expected_price'];
        }

        $mustContain = $this->stringList($expected['must_contain_any'] ?? []);
        foreach ($mustContain as $token) {
            if (str_contains($token, '₾') || mb_stripos($token, 'ლარი') !== false) {
                return 'mention_price_in_lari';
            }
        }

        return null;
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        return collect(is_array($value) ? $value : [$value])
            ->filter(fn ($item): bool => is_scalar($item))
            ->map(fn ($item): string => trim((string) $item))
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
