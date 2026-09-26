<?php

namespace App\Console\Commands;

use App\Services\Chatbot\ConversationAudit\ConversationSignalClassifier;
use App\Services\Chatbot\ModelCompletionService;
use App\Services\Chatbot\PairedModelBenchmarkService;
use Illuminate\Console\Command;

/** Model-only exploratory comparison. It cannot authorize the website rollout. */
class BenchmarkWidgetModels extends Command
{
    protected $signature = 'chatbot:benchmark-widget-models
        {--execute : Required to make paid OpenAI requests}
        {--dataset= : Controlled synthetic scenario JSON path}
        {--output= : Aggregate-only result JSON path}
        {--max-cases=120 : Maximum paired cases}
        {--budget-usd=2.99 : Remaining evaluation budget, capped at $3}';

    protected $description = 'Measure model-only latency and token cost on synthetic questions; write no responses or quality grades.';

    public function handle(
        PairedModelBenchmarkService $benchmark,
        ModelCompletionService $completion,
        ConversationSignalClassifier $classifier
    ): int {
        if (!$this->option('execute')) {
            $this->error('Use --execute only after confirming the paid evaluation budget.');
            return self::FAILURE;
        }

        $datasetPath = trim((string) ($this->option('dataset') ?: database_path('data/chatbot_conversation_scenarios.json')));
        $outputPath = trim((string) ($this->option('output') ?: database_path('data/chatbot_model_only_benchmark.json')));
        $budgetUsd = (float) $this->option('budget-usd');
        $maxCases = max(1, min(1000, (int) $this->option('max-cases')));
        if ($budgetUsd <= 0 || $budgetUsd > PairedModelBenchmarkService::HARD_BUDGET_USD
            || !is_file($datasetPath)) {
            $this->error('Invalid budget or missing dataset.');
            return self::FAILURE;
        }

        $cases = json_decode((string) file_get_contents($datasetPath), true);
        if (!is_array($cases) || !array_is_list($cases)) {
            $this->error('Dataset must be a JSON list of controlled synthetic scenarios.');
            return self::FAILURE;
        }
        $cases = array_slice($cases, 0, $maxCases);
        foreach ($cases as $case) {
            $question = is_array($case) ? trim((string) ($case['question'] ?? '')) : '';
            if (($case['review_status'] ?? null) !== 'synthetic_question_from_observed_intent'
                || $question === '' || mb_strlen($question) > 250
                || $classifier->redact($question) !== $question
                || isset($case['source_hash']) || isset($case['raw_message'])) {
                $this->error('Dataset is not a controlled, de-identified scenario set.');
                return self::FAILURE;
            }
        }

        $baseline = (string) config('chatbot.supervisor.model', 'gpt-4.1-mini');
        $candidate = (string) config('chatbot.widget_v2.model', 'gpt-6-luna');
        $prices = (array) config('chatbot.model_pricing_usd_per_million', []);
        config()->set('services.langfuse.enabled', false);

        $system = 'უპასუხე ბუნებრივ ქართულად. ეს მხოლოდ მოდელის გამოცდაა და ცოცხალი კატალოგი არ გაქვს. '
            . 'ფასი, მარაგი და კონკრეტული მოდელის ფუნქცია არ გამოიგონო; თუ ვერ ამოწმებ, ეს პირდაპირ თქვი. '
            . 'საიტზე საჯაროდ დადასტურებულია: მიწოდება უფასოა საქართველოს მასშტაბით; '
            . 'საქართველოს ბანკის ონლაინ გადახდა ხელმისაწვდომია; კურიერთან ნაღდი გადახდა მხოლოდ თბილისშია; '
            . '2G გარანტია 1 თვეა, 4G გარანტია 3 თვეა; მიღებიდან 14 დღეში შესაძლებელია მხოლოდ მოდელის გაცვლა '
            . 'გამოუყენებელ ნივთზე, ორიგინალი შეფუთვითა და შეძენის დასტურით. იგივე დღეს მიტანა საჭიროებს წინასწარ დადასტურებას.';
        $screen = [];
        try {
            $result = $benchmark->run(
                $cases,
                $baseline,
                $candidate,
                $prices,
                function (array $case, string $model, array $limits) use ($completion, $system, &$screen): array {
                    $started = hrtime(true);
                    $response = $completion->complete($model, [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => (string) $case['question']],
                    ], [
                        'max_tokens' => $limits['max_completion_tokens'],
                        'reasoning_effort' => 'none',
                        'temperature' => 0.2,
                        'timeout' => 25,
                    ]);
                    $reply = (string) ($response['reply'] ?? '');
                    $screen[$model]['responses'] = ($screen[$model]['responses'] ?? 0) + 1;
                    $screen[$model]['georgian_like'] = ($screen[$model]['georgian_like'] ?? 0)
                        + (int) (preg_match('/[ა-ჰ]/u', $reply) === 1);
                    $screen[$model]['potential_unverified_price_or_stock'] =
                        ($screen[$model]['potential_unverified_price_or_stock'] ?? 0)
                        + (int) (preg_match('/\b\d+[\s,.]*(?:₾|ლარ)|(?:მარაგშია|ხელმისაწვდომია)\b/iu', $reply) === 1);
                    $screen[$model]['potential_policy_conflict'] =
                        ($screen[$model]['potential_policy_conflict'] ?? 0)
                        + (int) (preg_match('/7\s*კალენდარული\s*დღ|12\s*თვ|უპირობო\s*დაბრუნ|ნებისმიერი\s*ნივთის\s*დაბრუნ/iu', $reply) === 1);

                    return [
                        'response' => $reply,
                        'usage' => $response['usage'] ?? [],
                        'latency_ms' => max(1, (int) round((hrtime(true) - $started) / 1_000_000)),
                        'fallback_reason' => $response['reason'] ?? null,
                    ];
                },
                null,
                [
                    'budget_usd' => $budgetUsd,
                    'max_input_tokens' => 1000,
                    'max_completion_tokens' => 200,
                ]
            );
        } catch (\Throwable $exception) {
            $this->error('Benchmark stopped: ' . $exception::class);
            return self::FAILURE;
        }

        $result['scope'] = 'model_only_identical_public_context';
        $result['release_gate'] = 'never_sufficient_without_full_pipeline_and_independent_grounded_judgments';
        $result['screen'] = $screen;
        if (!is_dir(dirname($outputPath)) && !mkdir(dirname($outputPath), 0700, true)) {
            $this->error('Could not create output directory.');
            return self::FAILURE;
        }
        file_put_contents($outputPath, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL);
        $this->line('Completed paired model-only cases: ' . $result['completed_pairs']);
        $this->line('Budget verified: ' . ($result['budget_verified'] ? 'yes' : 'no'));
        $this->line('Estimated API cost USD: ' . ($result['total_cost_usd'] ?? 'unknown'));
        $this->line('Release decision: ' . $result['decision']);
        $this->line('De-identified metrics report: ' . $outputPath);

        return $result['budget_verified'] ? self::SUCCESS : self::FAILURE;
    }
}
