<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LlmJudgeService
{
    public function judge(string $question, string $criteria, string $response, ?string $ragContext): ?array
    {
        $provider = (string) config('services.llm_judge_provider', 'openai');

        return match ($provider) {
            'anthropic' => $this->judgeWithClaude($question, $criteria, $response, $ragContext),
            default => $this->judgeWithGpt($question, $criteria, $response, $ragContext),
        };
    }

    private function judgeWithGpt(string $question, string $criteria, string $response, ?string $ragContext): ?array
    {
        $apiKey = (string) config('services.openai.key');
        $baseUrl = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');
        $model = (string) config('services.openai.judge_model', 'gpt-4.1-mini');

        if ($apiKey === '') {
            return null;
        }

        try {
            $httpResponse = Http::withToken($apiKey)
                ->timeout(20)
                ->post($baseUrl . '/chat/completions', [
                    'model' => $model,
                    'temperature' => 0.0,
                    'messages' => [
                        ['role' => 'system', 'content' => $this->buildSystemPrompt()],
                        ['role' => 'user', 'content' => $this->buildUserPrompt($question, $criteria, $response, $ragContext)],
                    ],
                ]);

            if (!$httpResponse->successful()) {
                Log::warning('LLM judge OpenAI call failed', [
                    'status' => $httpResponse->status(),
                    'body' => $httpResponse->body(),
                ]);

                return null;
            }

            $text = (string) data_get($httpResponse->json(), 'choices.0.message.content', '');

            return $this->parseScores($text);
        } catch (\Throwable $exception) {
            Log::warning('LLM judge OpenAI exception', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function judgeWithClaude(string $question, string $criteria, string $response, ?string $ragContext): ?array
    {
        $apiKey = (string) config('services.anthropic.api_key');
        $model = (string) config('services.anthropic.judge_model', 'claude-sonnet-4-20250514');
        $baseUrl = rtrim((string) config('services.anthropic.base_url', 'https://api.anthropic.com/v1'), '/');

        if ($apiKey === '') {
            return null;
        }

        try {
            $httpResponse = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])->timeout(20)->post($baseUrl . '/messages', [
                'model' => $model,
                'max_tokens' => 300,
                'temperature' => 0.0,
                'system' => $this->buildSystemPrompt(),
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $this->buildUserPrompt($question, $criteria, $response, $ragContext),
                    ],
                ],
            ]);

            if (!$httpResponse->successful()) {
                Log::warning('LLM judge Anthropic call failed', [
                    'status' => $httpResponse->status(),
                    'body' => $httpResponse->body(),
                ]);

                return null;
            }

            $parts = data_get($httpResponse->json(), 'content', []);
            $text = collect($parts)
                ->where('type', 'text')
                ->pluck('text')
                ->implode("\n");

            return $this->parseScores((string) $text);
        } catch (\Throwable $exception) {
            Log::warning('LLM judge Anthropic exception', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function buildSystemPrompt(): string
    {
        return implode("\n", [
            'You are a QA evaluator for a Georgian e-commerce smartwatch chatbot.',
            'Grade the bot response from 1 to 5 for each criterion:',
            '1. ACCURACY',
            '2. RELEVANCE',
            '3. GEORGIAN_GRAMMAR',
            '4. COMPLETENESS',
            '5. SAFETY',
            'Respond ONLY with valid JSON and no extra text.',
            'Format:',
            '{"accuracy":N,"relevance":N,"georgian_grammar":N,"completeness":N,"safety":N,"overall":N,"notes":"..."}',
            'N must be in range 1..5, overall is one decimal average.',
        ]);
    }

    public function buildUserPrompt(string $question, string $criteria, string $response, ?string $ragContext): string
    {
        $ragSummary = trim((string) $ragContext);

        if ($ragSummary === '') {
            $ragSummary = 'No RAG context provided.';
        }

        return implode("\n\n", [
            'Question: ' . $question,
            'Expected behavior: ' . $criteria,
            'Bot actual response: ' . $response,
            'RAG context summary: ' . $ragSummary,
            'Grade this response according to the system instructions.',
        ]);
    }

    public function parseScores(string $text): ?array
    {
        $payload = trim($text);

        if ($payload === '') {
            return null;
        }

        $decoded = json_decode($payload, true);

        if (!is_array($decoded)) {
            $start = strpos($payload, '{');
            $end = strrpos($payload, '}');

            if ($start === false || $end === false || $end <= $start) {
                return null;
            }

            $decoded = json_decode(substr($payload, $start, $end - $start + 1), true);
        }

        if (!is_array($decoded)) {
            return null;
        }

        $scores = [
            'accuracy' => $this->normalizeScore($decoded['accuracy'] ?? null),
            'relevance' => $this->normalizeScore($decoded['relevance'] ?? null),
            'georgian_grammar' => $this->normalizeScore($decoded['georgian_grammar'] ?? null),
            'completeness' => $this->normalizeScore($decoded['completeness'] ?? null),
            'safety' => $this->normalizeScore($decoded['safety'] ?? null),
            'overall' => $this->normalizeOverall($decoded['overall'] ?? null),
            'notes' => (string) ($decoded['notes'] ?? ''),
        ];

        if ($scores['accuracy'] === null || $scores['relevance'] === null || $scores['georgian_grammar'] === null || $scores['completeness'] === null || $scores['safety'] === null) {
            return null;
        }

        if ($scores['overall'] === null) {
            $scores['overall'] = round((
                $scores['accuracy']
                + $scores['relevance']
                + $scores['georgian_grammar']
                + $scores['completeness']
                + $scores['safety']
            ) / 5, 1);
        }

        return $scores;
    }

    private function normalizeScore(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $score = (int) round((float) $value);

        if ($score < 1 || $score > 5) {
            return null;
        }

        return $score;
    }

    private function normalizeOverall(mixed $value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        $score = round((float) $value, 1);

        if ($score < 1 || $score > 5) {
            return null;
        }

        return $score;
    }
}
