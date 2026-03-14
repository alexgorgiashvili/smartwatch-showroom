<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ModelCompletionService
{
    /**
     * Complete a chat request with OpenAI API
     *
     * @param array<int, array<string, string>> $messages
     * @return array{reply: string, reason: ?string, usage: array}
     */
    public function complete(
        string $model,
        array $messages,
        array $options = []
    ): array {
        $apiKey = (string) config('services.openai.key');
        $baseUrl = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');

        if ($apiKey === '') {
            return [
                'reply' => '',
                'reason' => ChatbotOutcomeReason::CHATBOT_DISABLED,
                'usage' => [],
            ];
        }

        $timeout = $options['timeout'] ?? 20;
        $temperature = $options['temperature'] ?? 0.4;
        $maxTokens = $options['max_tokens'] ?? 400;

        try {
            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->post($baseUrl . '/chat/completions', [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokens,
                ]);

            if (!$response->successful()) {
                Log::warning('Model completion request failed', [
                    'model' => $model,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'reply' => '',
                    'reason' => ChatbotOutcomeReason::PROVIDER_UNAVAILABLE,
                    'usage' => [],
                ];
            }

            $reply = trim((string) data_get($response->json(), 'choices.0.message.content', ''));

            if ($reply === '') {
                return [
                    'reply' => '',
                    'reason' => ChatbotOutcomeReason::EMPTY_MODEL_OUTPUT,
                    'usage' => [],
                ];
            }

            return [
                'reply' => $reply,
                'reason' => null,
                'usage' => data_get($response->json(), 'usage', []),
            ];
        } catch (\Throwable $exception) {
            Log::warning('Model completion exception', [
                'model' => $model,
                'error' => $exception->getMessage(),
            ]);

            return [
                'reply' => '',
                'reason' => ChatbotOutcomeReason::PROVIDER_EXCEPTION,
                'usage' => [],
            ];
        }
    }

    /**
     * Complete with retry logic
     *
     * @param array<int, array<string, string>> $messages
     * @return array{reply: string, reason: ?string, usage: array, attempts: int}
     */
    public function completeWithRetry(
        string $model,
        array $messages,
        int $maxRetries = 2,
        array $options = []
    ): array {
        $lastResult = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $result = $this->complete($model, $messages, $options);

            if ($result['reason'] === null && $result['reply'] !== '') {
                return array_merge($result, ['attempts' => $attempt]);
            }

            $lastResult = $result;

            if ($attempt < $maxRetries) {
                Log::info("Model completion attempt {$attempt} failed, retrying...", [
                    'model' => $model,
                    'reason' => $result['reason'],
                ]);

                usleep(500000);
            }
        }

        return array_merge($lastResult ?? [
            'reply' => '',
            'reason' => ChatbotOutcomeReason::PROVIDER_EXCEPTION,
            'usage' => [],
        ], ['attempts' => $maxRetries]);
    }

    /**
     * Stream completion tokens (for future streaming support)
     *
     * @param array<int, array<string, string>> $messages
     */
    public function streamCompletion(
        string $model,
        array $messages,
        callable $onToken,
        array $options = []
    ): array {
        $apiKey = (string) config('services.openai.key');
        $baseUrl = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');

        if ($apiKey === '') {
            return [
                'reply' => '',
                'reason' => ChatbotOutcomeReason::CHATBOT_DISABLED,
                'ttft' => null,
            ];
        }

        $timeout = $options['timeout'] ?? 30;
        $temperature = $options['temperature'] ?? 0.4;
        $maxTokens = $options['max_tokens'] ?? 400;

        try {
            $startTime = microtime(true);
            $firstTokenTime = null;
            $fullResponse = '';

            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->post($baseUrl . '/chat/completions', [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokens,
                    'stream' => true,
                ]);

            foreach ($response->stream() as $chunk) {
                if ($firstTokenTime === null) {
                    $firstTokenTime = microtime(true);
                }

                $token = $this->parseStreamChunk($chunk);
                if ($token) {
                    $fullResponse .= $token;
                    $onToken($token);
                }
            }

            $ttft = $firstTokenTime ? (int) round(($firstTokenTime - $startTime) * 1000) : null;

            return [
                'reply' => $fullResponse,
                'reason' => $fullResponse === '' ? ChatbotOutcomeReason::EMPTY_MODEL_OUTPUT : null,
                'ttft' => $ttft,
            ];
        } catch (\Throwable $exception) {
            Log::warning('Streaming completion exception', [
                'model' => $model,
                'error' => $exception->getMessage(),
            ]);

            return [
                'reply' => '',
                'reason' => ChatbotOutcomeReason::PROVIDER_EXCEPTION,
                'ttft' => null,
            ];
        }
    }

    private function parseStreamChunk(string $chunk): ?string
    {
        if (str_starts_with($chunk, 'data: ')) {
            $data = substr($chunk, 6);
            
            if ($data === '[DONE]') {
                return null;
            }

            $decoded = json_decode($data, true);
            return data_get($decoded, 'choices.0.delta.content');
        }

        return null;
    }
}
