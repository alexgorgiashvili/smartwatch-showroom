<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\File;

class RuntimeAuditService
{
    public function __construct(
        private readonly ChatbotTrainingRunnerService $trainingRunner,
        private readonly ChatbotLabService $labService
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function loadDataset(?string $dataset = null): array
    {
        $path = $this->resolveDatasetPath($dataset);

        if (!File::exists($path)) {
            throw new \RuntimeException('Runtime audit dataset not found: ' . $path);
        }

        $decoded = json_decode((string) File::get($path), true);

        if (!is_array($decoded)) {
            throw new \RuntimeException('Runtime audit dataset is not valid JSON.');
        }

        $decoded['_resolved_path'] = $path;

        return $decoded;
    }

    /**
     * @param array<string, mixed> $dataset
     * @return array<string, mixed>
     */
    public function run(array $dataset): array
    {
        $startedAt = microtime(true);
        $singleTurn = $this->runSingleTurnCases($dataset['single_turn'] ?? []);
        $sessions = $this->runSessionCases($dataset['sessions'] ?? []);
        $allResults = array_merge($singleTurn, $sessions);

        $passedCount = count(array_filter($allResults, fn (array $result): bool => ($result['status'] ?? 'failed') === 'passed'));
        $failedCount = count($allResults) - $passedCount;

        return [
            'meta' => [
                'name' => (string) ($dataset['name'] ?? 'Runtime audit'),
                'generated_at' => now()->toIso8601String(),
                'dataset_path' => (string) ($dataset['_resolved_path'] ?? ''),
                'single_turn_count' => count($singleTurn),
                'session_count' => count($sessions),
                'total_cases' => count($allResults),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ],
            'summary' => [
                'passed_count' => $passedCount,
                'failed_count' => $failedCount,
                'pass_rate' => count($allResults) > 0
                    ? round(($passedCount / count($allResults)) * 100, 2)
                    : 0.0,
            ],
            'results' => $allResults,
        ];
    }

    /**
     * @param mixed $cases
     * @return array<int, array<string, mixed>>
     */
    private function runSingleTurnCases(mixed $cases): array
    {
        if (!is_array($cases)) {
            return [];
        }

        $results = [];

        foreach ($cases as $case) {
            if (!is_array($case)) {
                continue;
            }

            $question = trim((string) ($case['question'] ?? ''));

            if ($question === '') {
                continue;
            }

            $runtime = $this->trainingRunner->runSingleQuestion(
                $question,
                (string) ($case['category'] ?? 'single_turn')
            );

            $evaluation = $this->evaluateResponse(
                (string) ($runtime['response'] ?? ''),
                is_array($case['expected'] ?? null) ? $case['expected'] : [],
                $runtime
            );

            $results[] = [
                'mode' => 'single_turn',
                'id' => (string) ($case['id'] ?? ''),
                'category' => (string) ($case['category'] ?? 'single_turn'),
                'question' => $question,
                'turns' => [
                    [
                        'role' => 'user',
                        'content' => $question,
                    ],
                ],
                'response' => (string) ($runtime['response'] ?? ''),
                'runtime' => $runtime,
                'evaluation' => $evaluation,
                'status' => $evaluation['passed'] ? 'passed' : 'failed',
            ];
        }

        return $results;
    }

    /**
     * @param mixed $cases
     * @return array<int, array<string, mixed>>
     */
    private function runSessionCases(mixed $cases): array
    {
        if (!is_array($cases)) {
            return [];
        }

        $results = [];

        foreach ($cases as $case) {
            if (!is_array($case)) {
                continue;
            }

            $turns = is_array($case['turns'] ?? null) ? $case['turns'] : [];
            if ($turns === []) {
                continue;
            }

            $conversationId = null;
            $turnLogs = [];
            $lastResponse = '';
            $exceptionMessage = null;

            try {
                foreach ($turns as $turn) {
                    $question = trim((string) ($turn['question'] ?? ''));
                    if ($question === '') {
                        continue;
                    }

                    $result = $this->labService->runManualTest(
                        $question,
                        '',
                        $conversationId,
                        true
                    );

                    if (!($result['success'] ?? false)) {
                        $exceptionMessage = (string) ($result['error'] ?? 'Unknown runtime audit session error');
                        break;
                    }

                    $conversationId = (int) data_get($result, 'session.conversation_id', $conversationId ?? 0);
                    $lastResponse = (string) ($result['response'] ?? '');

                    $turnLogs[] = [
                        'role' => 'user',
                        'content' => $question,
                    ];
                    $turnLogs[] = [
                        'role' => 'assistant',
                        'content' => $lastResponse,
                    ];
                }
            } finally {
                if ($conversationId !== null) {
                    $this->labService->resetSession($conversationId);
                }
            }

            $runtime = [
                'conversation_id' => $conversationId,
                'error' => $exceptionMessage,
                'fallback_reason' => null,
                'validation_passed' => $exceptionMessage === null,
            ];

            $evaluation = $this->evaluateResponse(
                $lastResponse,
                is_array($case['expected'] ?? null) ? $case['expected'] : [],
                $runtime
            );

            if ($exceptionMessage !== null) {
                $evaluation['passed'] = false;
                $evaluation['reasons'][] = 'Session runtime error: ' . $exceptionMessage;
            }

            $results[] = [
                'mode' => 'session',
                'id' => (string) ($case['id'] ?? ''),
                'category' => (string) ($case['category'] ?? 'session'),
                'question' => trim((string) data_get($turns, '0.question', '')),
                'turns' => $turnLogs,
                'response' => $lastResponse,
                'runtime' => $runtime,
                'evaluation' => $evaluation,
                'status' => $evaluation['passed'] ? 'passed' : 'failed',
            ];
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $expected
     * @param array<string, mixed> $runtime
     * @return array{passed:bool,reasons:array<int,string>,checks:array<string,mixed>}
     */
    private function evaluateResponse(string $response, array $expected, array $runtime): array
    {
        $normalizedResponse = $this->normalize($response);
        $mustContainAny = $this->stringList($expected['must_contain_any'] ?? []);
        $mustContainAll = $this->stringList($expected['must_contain_all'] ?? []);
        $mustNotContain = $this->stringList($expected['must_not_contain'] ?? []);
        $allowFallback = (bool) ($expected['allow_fallback'] ?? false);
        $validationMustPass = !array_key_exists('validation_must_pass', $expected)
            || (bool) $expected['validation_must_pass'];

        $matchedAny = [];
        foreach ($mustContainAny as $token) {
            if (str_contains($normalizedResponse, $this->normalize($token))) {
                $matchedAny[] = $token;
            }
        }

        $missingAll = [];
        foreach ($mustContainAll as $token) {
            if (!str_contains($normalizedResponse, $this->normalize($token))) {
                $missingAll[] = $token;
            }
        }

        $forbiddenHits = [];
        foreach ($mustNotContain as $token) {
            if (str_contains($normalizedResponse, $this->normalize($token))) {
                $forbiddenHits[] = $token;
            }
        }

        $reasons = [];

        if (trim($response) === '') {
            $reasons[] = 'Empty response';
        }

        if ($mustContainAny !== [] && $matchedAny === []) {
            $reasons[] = 'Missing any of expected tokens: ' . implode(', ', $mustContainAny);
        }

        if ($missingAll !== []) {
            $reasons[] = 'Missing required tokens: ' . implode(', ', $missingAll);
        }

        if ($forbiddenHits !== []) {
            $reasons[] = 'Contains forbidden tokens: ' . implode(', ', $forbiddenHits);
        }

        if (!$allowFallback && trim((string) ($runtime['fallback_reason'] ?? '')) !== '') {
            $reasons[] = 'Unexpected fallback: ' . $runtime['fallback_reason'];
        }

        if ($validationMustPass && array_key_exists('validation_passed', $runtime) && !$runtime['validation_passed']) {
            $reasons[] = 'Validation did not pass';
        }

        return [
            'passed' => $reasons === [],
            'reasons' => $reasons,
            'checks' => [
                'matched_any' => $matchedAny,
                'missing_all' => $missingAll,
                'forbidden_hits' => $forbiddenHits,
                'fallback_reason' => $runtime['fallback_reason'] ?? null,
                'validation_passed' => $runtime['validation_passed'] ?? null,
            ],
        ];
    }

    /**
     * @param mixed $values
     * @return array<int, string>
     */
    private function stringList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($value): string {
            return trim((string) $value);
        }, $values), fn (string $value): bool => $value !== ''));
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private function resolveDatasetPath(?string $dataset): string
    {
        $defaultPath = database_path('data/chatbot_runtime_audit_dataset.json');
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
}
