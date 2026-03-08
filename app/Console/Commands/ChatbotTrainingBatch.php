<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Chatbot\TestRunnerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ChatbotTrainingBatch extends Command
{
    protected $signature = 'chatbot:training-batch
        {--size=5 : Number of cases to run in this batch}
        {--offset=0 : Dataset offset to start from}
        {--with-judge=0 : Run LLM judge scoring for each case (costs extra tokens)}';

    protected $description = 'Run chatbot training batch from golden dataset and log question/response/suggestions for human approval.';

    public function handle(TestRunnerService $runner): int
    {
        $size = max(1, (int) $this->option('size'));
        $offset = max(0, (int) $this->option('offset'));
        $withJudge = (string) $this->option('with-judge') === '1';

        $dataset = $runner->loadDataset();

        if ($dataset->isEmpty()) {
            $this->warn('Dataset is empty.');

            return Command::SUCCESS;
        }

        $batch = $dataset->slice($offset, $size)->values();

        if ($batch->isEmpty()) {
            $this->warn('No cases found for the requested offset/size.');

            return Command::SUCCESS;
        }

        $results = [];

        $this->info('Running training batch: offset=' . $offset . ', size=' . $batch->count());
        $bar = $this->output->createProgressBar($batch->count());
        $bar->start();

        foreach ($batch as $case) {
            $caseResult = $this->runCase($runner, $case, $withJudge);
            $results[] = $caseResult;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $payload = [
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'offset' => $offset,
                'size' => $batch->count(),
                'with_judge' => $withJudge,
            ],
            'results' => $results,
        ];

        $relativePath = 'chatbot-training/batch_' . now()->format('Ymd_His') . '_o' . $offset . '_s' . $batch->count() . '.json';
        Storage::disk('local')->put($relativePath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->line('Saved batch log: storage/app/' . $relativePath);

        $this->table(
            ['Case', 'Category', 'Status', 'ms', 'Question'],
            collect($results)->map(function (array $row): array {
                return [
                    $row['case_id'],
                    $row['category'],
                    $row['matcher_status'],
                    (string) ($row['response_time_ms'] ?? 0),
                    mb_strimwidth((string) ($row['question'] ?? ''), 0, 70, '...'),
                ];
            })->all()
        );

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $case
     * @return array<string, mixed>
     */
    private function runCase(TestRunnerService $runner, array $case, bool $withJudge): array
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
                $finalPipeline = $runner->callPipeline($content, $conversationId);
                $conversationId = (int) ($finalPipeline['conversation_id'] ?? $conversationId);
            }
        } else {
            $finalPipeline = $runner->callPipeline($question, null);
        }

        $response = (string) ($finalPipeline['response'] ?? '');
        $matchers = $runner->gradeWithMatchers($case, $response, $finalPipeline ?? []);

        $judge = null;
        if ($withJudge) {
            $judge = $runner->gradeWithLlmJudge($case, $response, (string) ($finalPipeline['rag_context_text'] ?? ''));
        }

        return [
            'case_id' => (string) ($case['id'] ?? ''),
            'category' => (string) ($case['category'] ?? ''),
            'question' => $question,
            'expected' => $case['expected'] ?? [],
            'bot_response' => $response,
            'rag_context' => (string) ($finalPipeline['rag_context_text'] ?? ''),
            'response_time_ms' => (int) ($finalPipeline['response_time_ms'] ?? 0),
            'matcher' => [
                'keyword_match' => $matchers['keyword_match'] ?? null,
                'price_match' => $matchers['price_match'] ?? null,
                'stock_match' => $matchers['stock_match'] ?? null,
                'guardrail_passed' => $matchers['guardrail_passed'] ?? null,
                'georgian_qa_passed' => $matchers['georgian_qa_passed'] ?? null,
                'matcher_pass' => $matchers['matcher_pass'] ?? false,
            ],
            'matcher_status' => ($matchers['matcher_pass'] ?? false) ? 'pass' : 'fail',
            'judge' => $judge,
            'assistant_suggested_answer' => $this->buildSuggestedAnswer($case),
            'assistant_training_note' => $this->buildTrainingNote($case, $matchers, $response),
        ];
    }

    /**
     * @param array<string, mixed> $case
     */
    private function buildSuggestedAnswer(array $case): string
    {
        $slug = trim((string) data_get($case, 'expected.product_slug', ''));
        $question = trim((string) ($case['question'] ?? ''));

        if ($slug !== '') {
            $product = Product::query()
                ->withSum('variants as total_stock', 'quantity')
                ->where('slug', $slug)
                ->first();

            if ($product) {
                $price = $product->sale_price ?: $product->price;
                $stock = max(0, (int) ($product->total_stock ?? 0));
                $stockText = $stock > 0 ? 'მარაგშია' : 'ამოწურულია';

                return 'დიახ, ' . $product->name . ' ღირს ' . $price . ' ₾. სტატუსი: ' . $stockText . ' (' . $stock . ' ცალი).';
            }

            return 'ამ მოდელის (' . $slug . ') ზუსტი ინფორმაცია ამჟამად თქვენს კატალოგში ვერ მოიძებნა. გთხოვთ დაამატოთ პროდუქტი ან განაახლოთ dataset case, რომ ფასი ზუსტად დაგისახელოთ.';
        }

        if ($question !== '') {
            return 'კითხვაზე უნდა გაიცეს კონკრეტული პასუხი, რომელიც მოიცავს expected.must_contain_any ელემენტებს და არ შეიცავს forbidden ფრაზებს.';
        }

        return 'პასუხი უნდა დააკმაყოფილოს expected წესები (must_contain_any / must_not_contain / guardrail / grammar).';
    }

    /**
     * @param array<string, mixed> $case
     * @param array<string, mixed> $matchers
     */
    private function buildTrainingNote(array $case, array $matchers, string $actualResponse): string
    {
        $notes = [];
        $mustContainAny = collect(data_get($case, 'expected.must_contain_any', []))
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->values()
            ->all();

        if (($matchers['keyword_match'] ?? true) !== true && $mustContainAny !== []) {
            $notes[] = 'პასუხში დაამატე მინიმუმ ერთი expected token: ' . implode(', ', $mustContainAny);
        }

        if (($matchers['price_match'] ?? null) === false) {
            $notes[] = 'ფასი არ ემთხვევა მოლოდინს — გამოიყენე კატალოგის ზუსტი ფასი ლარებში.';
        }

        if (($matchers['stock_match'] ?? null) === false) {
            $notes[] = 'მარაგის claim არასწორია — გამოიყენე ლაივ stock სტატუსი.';
        }

        if (($matchers['guardrail_passed'] ?? null) === false) {
            $notes[] = 'guardrail დარღვეულია — დაიცავი უსაფრთხოების პოლიტიკა.';
        }

        if (($matchers['georgian_qa_passed'] ?? null) === false) {
            $notes[] = 'პასუხი უნდა იყოს ბუნებრივი ქართული, თქვენობითი ფორმით.';
        }

        if ($notes === []) {
            $notes[] = 'ქეისი მისაღებია matcher წესებით. შეგიძლია დაადასტურო როგორც სწორი.';
        }

        if (mb_strlen(trim($actualResponse)) < 10) {
            $notes[] = 'პასუხი ზედმეტად მოკლეა — დაამატე კონკრეტული ფაქტი (ფასი/მარაგი/მოდელი).';
        }

        return implode(' ', $notes);
    }
}
