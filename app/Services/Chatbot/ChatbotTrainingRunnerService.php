<?php

namespace App\Services\Chatbot;

use App\Services\Chatbot\Agents\SupervisorAgent;
use Illuminate\Support\Str;

class ChatbotTrainingRunnerService
{
    public function __construct(
        private readonly InputGuardService $inputGuard,
        private readonly IntentAnalyzerService $intentAnalyzer,
        private readonly SupervisorAgent $supervisor,
        private readonly WidgetTraceLogger $widgetTrace
    ) {}

    public function runSingleQuestion(string $question, string $category = 'manual'): array
    {
        return $this->runQuestion([
            'id' => 'manual_' . now()->format('Ymd_His'),
            'name' => 'Manual Flow Inspection',
        ], [
            'id' => 'manual_' . Str::lower(Str::random(6)),
            'question' => $question,
            'category' => $category,
            'difficulty' => 'manual',
        ]);
    }

    public function runBatch(array $batch): array
    {
        $results = [];
        $startedAt = microtime(true);

        foreach (($batch['questions'] ?? []) as $question) {
            if (!is_array($question)) {
                continue;
            }

            $results[] = $this->runQuestion($batch, $question);
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $successful = array_values(array_filter($results, static fn (array $result): bool => ($result['status'] ?? '') === 'passed'));
        $needsReview = array_values(array_filter($results, static fn (array $result): bool => (bool) ($result['needs_review'] ?? false)));
        $latencies = array_values(array_filter(array_map(static fn (array $result): ?int => isset($result['duration_ms']) ? (int) $result['duration_ms'] : null, $results)));

        return [
            'id' => 'run_' . now()->format('Ymd_His') . '_' . Str::lower(Str::random(6)),
            'batch_id' => (string) ($batch['id'] ?? ''),
            'batch_name' => (string) ($batch['name'] ?? ''),
            'created_at' => now()->toIso8601String(),
            'status' => 'completed',
            'summary' => [
                'total_questions' => count($results),
                'passed_count' => count($successful),
                'needs_review_count' => count($needsReview),
                'avg_response_time_ms' => $latencies !== [] ? (int) round(array_sum($latencies) / count($latencies)) : 0,
                'total_duration_ms' => $durationMs,
            ],
            'results' => $results,
        ];
    }

    private function runQuestion(array $batch, array $question): array
    {
        $traceId = $this->widgetTrace->enabled() ? $this->widgetTrace->newTraceId() : 'training_' . Str::lower(Str::random(12));
        $conversationId = abs((int) sprintf('%u', crc32((string) ($batch['id'] ?? 'batch') . ':' . ($question['id'] ?? 'question'))));
        $customerId = 999001;
        $rawQuestion = trim((string) ($question['question'] ?? ''));
        $startedAt = microtime(true);

        $traceContext = [
            'trace_id' => $traceId,
            'conversation_id' => $conversationId,
            'customer_id' => $customerId,
            'training_batch_id' => (string) ($batch['id'] ?? ''),
            'training_question_id' => (string) ($question['id'] ?? ''),
        ];

        $this->widgetTrace->logStep('training.question.started', array_merge($traceContext, [
            'question' => $rawQuestion,
            'category' => (string) ($question['category'] ?? ''),
            'difficulty' => (string) ($question['difficulty'] ?? ''),
            'next_step' => 'guard_and_intent_analysis',
        ]));

        try {
            $guardResult = $this->inputGuard->inspect($rawQuestion);
            $sanitizedQuestion = $guardResult->sanitizedInput();
            if ($sanitizedQuestion === '') {
                $sanitizedQuestion = $rawQuestion;
            }

            if (!$guardResult->allowed()) {
                $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
                $response = $guardResult->safeReply() ?? 'ბოდიში, ამ შინაარსზე ვერ გავაგრძელებ.';

                $this->widgetTrace->logStep('training.question.blocked', array_merge($traceContext, [
                    'question' => $rawQuestion,
                    'guard_reason' => $guardResult->reason(),
                    'response' => $response,
                ]));

                return [
                    'question_id' => (string) ($question['id'] ?? ''),
                    'question' => $rawQuestion,
                    'question_fingerprint' => (string) ($question['fingerprint'] ?? ''),
                    'category' => (string) ($question['category'] ?? ''),
                    'difficulty' => (string) ($question['difficulty'] ?? ''),
                    'response' => $response,
                    'trace_id' => $traceId,
                    'intent' => 'guard_blocked',
                    'intent_confidence' => 1.0,
                    'agent_used' => 'InputGuard',
                    'validation_passed' => false,
                    'reflection_attempts' => 0,
                    'fallback_reason' => $guardResult->reason(),
                    'duration_ms' => $durationMs,
                    'needs_review' => true,
                    'review_reasons' => ['Blocked by input guard'],
                    'status' => 'needs_review',
                ];
            }

            $intentResult = $this->intentAnalyzer->analyze($sanitizedQuestion, [], [], $traceContext);
            $supervisorResult = $this->supervisor->orchestrate(
                $sanitizedQuestion,
                $conversationId,
                $customerId,
                $intentResult,
                [],
                $traceContext
            );

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $response = (string) ($supervisorResult['response'] ?? '');
            $reviewReasons = $this->reviewReasons($response, $supervisorResult);
            $needsReview = $reviewReasons !== [];

            $this->widgetTrace->logStep('training.question.completed', array_merge($traceContext, [
                'question' => $rawQuestion,
                'intent' => $intentResult->intent(),
                'intent_confidence' => $intentResult->confidence(),
                'agent_used' => $supervisorResult['agent_used'] ?? null,
                'validation_passed' => $supervisorResult['validation_passed'] ?? false,
                'fallback_reason' => $supervisorResult['reason'] ?? null,
                'response_time_ms' => $durationMs,
                'response' => $response,
            ]));

            return [
                'question_id' => (string) ($question['id'] ?? ''),
                'question' => $rawQuestion,
                'question_fingerprint' => (string) ($question['fingerprint'] ?? ''),
                'category' => (string) ($question['category'] ?? ''),
                'difficulty' => (string) ($question['difficulty'] ?? ''),
                'response' => $response,
                'trace_id' => $traceId,
                'intent' => $intentResult->intent(),
                'intent_confidence' => $intentResult->confidence(),
                'agent_used' => $supervisorResult['agent_used'] ?? 'unknown',
                'validation_passed' => (bool) ($supervisorResult['validation_passed'] ?? false),
                'reflection_attempts' => (int) ($supervisorResult['reflection_attempts'] ?? 0),
                'fallback_reason' => $supervisorResult['reason'] ?? null,
                'duration_ms' => $durationMs,
                'needs_review' => $needsReview,
                'review_reasons' => $reviewReasons,
                'status' => $needsReview ? 'needs_review' : 'passed',
            ];
        } catch (\Throwable $exception) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            $this->widgetTrace->logStep('training.question.failed', array_merge($traceContext, [
                'question' => $rawQuestion,
                'error' => $exception->getMessage(),
                'exception_class' => $exception::class,
                'response_time_ms' => $durationMs,
            ]));

            return [
                'question_id' => (string) ($question['id'] ?? ''),
                'question' => $rawQuestion,
                'question_fingerprint' => (string) ($question['fingerprint'] ?? ''),
                'category' => (string) ($question['category'] ?? ''),
                'difficulty' => (string) ($question['difficulty'] ?? ''),
                'response' => '',
                'trace_id' => $traceId,
                'intent' => 'runtime_error',
                'intent_confidence' => 0.0,
                'agent_used' => 'runtime_error',
                'validation_passed' => false,
                'reflection_attempts' => 0,
                'fallback_reason' => $exception->getMessage(),
                'duration_ms' => $durationMs,
                'needs_review' => true,
                'review_reasons' => ['Runtime exception during batch execution'],
                'status' => 'failed',
            ];
        }
    }

    private function reviewReasons(string $response, array $supervisorResult): array
    {
        $reasons = [];

        if (trim($response) === '') {
            $reasons[] = 'Empty response';
        }

        if (($supervisorResult['reason'] ?? null) !== null) {
            $reasons[] = 'Fallback used: ' . $supervisorResult['reason'];
        }

        if (!(bool) ($supervisorResult['validation_passed'] ?? false)) {
            $reasons[] = 'Validation did not pass';
        }

        return $reasons;
    }
}
