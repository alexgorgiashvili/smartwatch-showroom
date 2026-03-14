<?php

namespace App\Console\Commands;

use App\Services\Chatbot\Agents\SupervisorAgent;
use App\Services\Chatbot\IntentAnalyzerService;
use App\Services\Chatbot\MultiLayerCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CollectChatbotResponses extends Command
{
    protected $signature = 'chatbot:collect-responses
                            {file? : Specific questions file to use (defaults to test-questions.json)}
                            {--no-cache : Bypass cache for fresh responses}';

    protected $description = 'Collect chatbot responses for grammar testing';

    public function handle(
        SupervisorAgent $supervisor,
        IntentAnalyzerService $intentAnalyzer,
        MultiLayerCacheService $cache
    ): int {
        $this->info('🤖 Starting chatbot response collection...');
        $this->newLine();

        $questionsFile = $this->argument('file') ?? 'test-questions.json';
        $questionsPath = storage_path('chatbot-grammar-tests/' . $questionsFile);

        if (!File::exists($questionsPath)) {
            $this->error('❌ Test questions file not found: ' . $questionsPath);
            return self::FAILURE;
        }

        $questionsData = json_decode(File::get($questionsPath), true);
        $questions = $questionsData['questions'] ?? [];

        if (empty($questions)) {
            $this->error('❌ No questions found in test file');
            return self::FAILURE;
        }

        $this->info('📋 Found ' . count($questions) . ' test questions');
        $this->newLine();

        $responses = [];
        $conversationId = 999999; // Test conversation ID
        $customerId = 1;
        $preferences = [];

        $bar = $this->output->createProgressBar(count($questions));
        $bar->start();

        foreach ($questions as $question) {
            $questionText = $question['question'];

            try {
                $startTime = microtime(true);

                // Analyze intent
                $intentResult = $intentAnalyzer->analyze($questionText, [], [], [
                    'trace_id' => 'grammar_test_' . $question['id'],
                ]);

                // Check cache (unless bypassed)
                $cachedResponse = null;
                if (!$this->option('no-cache')) {
                    $cachedResponse = $cache->getCachedResponse($questionText, $intentResult);
                }

                // Get response from supervisor
                if ($cachedResponse) {
                    $response = $cachedResponse['response'];
                    $cacheHit = true;
                } else {
                    $supervisorResult = $supervisor->orchestrate(
                        $questionText,
                        $conversationId,
                        $customerId,
                        $intentResult,
                        $preferences,
                        [
                            'trace_id' => 'grammar_test_' . $question['id'],
                            'customer_id' => $customerId,
                        ]
                    );

                    $response = $supervisorResult['response'] ?? '';
                    $cacheHit = false;
                }

                $duration = round((microtime(true) - $startTime) * 1000);

                $responses[] = [
                    'question_id' => $question['id'],
                    'question' => $questionText,
                    'category' => $question['category'],
                    'response' => $response,
                    'intent' => $intentResult->intent(),
                    'cache_hit' => $cacheHit,
                    'duration_ms' => $duration,
                    'timestamp' => now()->toIso8601String(),
                ];

            } catch (\Exception $e) {
                $responses[] = [
                    'question_id' => $question['id'],
                    'question' => $questionText,
                    'category' => $question['category'],
                    'response' => '',
                    'error' => $e->getMessage(),
                    'timestamp' => now()->toIso8601String(),
                ];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Save responses
        $timestamp = now()->format('Y-m-d_H-i-s');
        $outputPath = storage_path("chatbot-grammar-tests/responses/{$timestamp}.json");

        $output = [
            'collected_at' => now()->toIso8601String(),
            'total_questions' => count($questions),
            'successful_responses' => count(array_filter($responses, fn($r) => !isset($r['error']))),
            'cache_hits' => count(array_filter($responses, fn($r) => $r['cache_hit'] ?? false)),
            'responses' => $responses,
        ];

        File::put($outputPath, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info('✅ Responses collected successfully!');
        $this->info('📁 Saved to: ' . $outputPath);
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Questions', $output['total_questions']],
                ['Successful', $output['successful_responses']],
                ['Cache Hits', $output['cache_hits']],
                ['Output File', basename($outputPath)],
            ]
        );

        return self::SUCCESS;
    }
}
