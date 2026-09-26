<?php

namespace App\Services\Chatbot;

use Illuminate\Contracts\Container\BindingResolutionException;
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
        $startedAt = microtime(true);
        $langfuseName = (string) ($options['langfuse_name'] ?? 'chatbot.model_completion');
        $langfuseMetadata = is_array($options['langfuse_metadata'] ?? null)
            ? $options['langfuse_metadata']
            : [];

        try {
            $payload = $this->completionPayload($model, $messages, $options);

            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->post($baseUrl . '/chat/completions', $payload);

            if (!$response->successful()) {
                Log::warning('Model completion request failed', [
                    'model' => $model,
                    'status' => $response->status(),
                    'error_code' => data_get($response->json(), 'error.code'),
                ]);

                $this->langfuse()->recordGeneration(
                    $langfuseName,
                    $model,
                    $messages,
                    '',
                    [],
                    array_merge($langfuseMetadata, [
                        'provider' => 'openai',
                        'provider_status' => $response->status(),
                        'provider_error_code' => data_get($response->json(), 'error.code'),
                        'reason' => ChatbotOutcomeReason::PROVIDER_UNAVAILABLE,
                    ]),
                    $this->modelParameters($payload),
                    $startedAt,
                    microtime(true)
                );

                return [
                    'reply' => '',
                    'reason' => ChatbotOutcomeReason::PROVIDER_UNAVAILABLE,
                    'usage' => [],
                ];
            }

            $message = data_get($response->json(), 'choices.0.message');
            $reply = trim((string) data_get($message, 'content', ''));
            $toolCalls = data_get($message, 'tool_calls', []);

            if ($reply === '' && empty($toolCalls)) {
                $this->langfuse()->recordGeneration(
                    $langfuseName,
                    $model,
                    $messages,
                    '',
                    data_get($response->json(), 'usage', []),
                    array_merge($langfuseMetadata, [
                        'provider' => 'openai',
                        'reason' => ChatbotOutcomeReason::EMPTY_MODEL_OUTPUT,
                    ]),
                    $this->modelParameters($payload),
                    $startedAt,
                    microtime(true)
                );

                return [
                    'reply' => '',
                    'reason' => ChatbotOutcomeReason::EMPTY_MODEL_OUTPUT,
                    'usage' => [],
                ];
            }

            $usage = data_get($response->json(), 'usage', []);
            $estimatedCost = $this->estimateCostUsd($model, is_array($usage) ? $usage : []);

            $this->langfuse()->recordGeneration(
                $langfuseName,
                $model,
                $messages,
                $reply,
                $usage,
                array_merge($langfuseMetadata, [
                    'provider' => 'openai',
                    'finish_reason' => data_get($response->json(), 'choices.0.finish_reason'),
                    'estimated_cost_usd' => $estimatedCost,
                ]),
                $this->modelParameters($payload),
                $startedAt,
                microtime(true)
            );

            return [
                'reply' => $reply,
                'tool_calls' => $toolCalls,
                'reason' => null,
                'usage' => $usage,
                'estimated_cost_usd' => $estimatedCost,
            ];
        } catch (\Throwable $exception) {
            Log::warning('Model completion exception', [
                'model' => $model,
                'exception_class' => $exception::class,
            ]);

            $this->langfuse()->recordGeneration(
                $langfuseName,
                $model,
                $messages,
                '',
                [],
                array_merge($langfuseMetadata, [
                    'provider' => 'openai',
                    'reason' => ChatbotOutcomeReason::PROVIDER_EXCEPTION,
                    'exception_class' => $exception::class,
                ]),
                $this->modelParameters($this->completionPayload($model, $messages, $options)),
                $startedAt,
                microtime(true)
            );

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
        try {
            $startTime = microtime(true);
            $firstTokenTime = null;
            $fullResponse = '';

            $payload = $this->completionPayload($model, $messages, $options);
            $payload['stream'] = true;

            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->post($baseUrl . '/chat/completions', $payload);

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
                'exception_class' => $exception::class,
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

    /** @return array<string, mixed> */
    private function completionPayload(string $model, array $messages, array $options): array
    {
        $payload = [
            'model' => $model,
            'messages' => $messages,
        ];
        $maxTokens = (int) ($options['max_completion_tokens'] ?? $options['max_tokens'] ?? 400);

        if (str_starts_with($model, 'gpt-6-')) {
            $effort = (string) ($options['reasoning_effort'] ?? 'none');
            $payload['reasoning_effort'] = $effort;
            $payload['max_completion_tokens'] = $maxTokens;
            if ($effort === 'none') {
                $payload['temperature'] = $options['temperature'] ?? 0.4;
            }
        } else {
            $payload['temperature'] = $options['temperature'] ?? 0.4;
            $payload['max_tokens'] = $maxTokens;
        }

        if (isset($options['tools'])) {
            $payload['tools'] = $options['tools'];
        }
        if (isset($options['response_format']) && is_array($options['response_format'])) {
            $payload['response_format'] = $options['response_format'];
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function modelParameters(array $payload): array
    {
        return array_intersect_key($payload, array_flip([
            'temperature', 'max_tokens', 'max_completion_tokens', 'reasoning_effort',
        ]));
    }

    public function estimateCostUsd(string $model, array $usage): ?float
    {
        $prices = config('chatbot.model_pricing_usd_per_million', []);
        $rates = is_array($prices) ? ($prices[$model] ?? null) : null;
        if (!is_array($rates) || !isset($rates['input'], $rates['output'])) {
            return null;
        }

        $input = $usage['prompt_tokens'] ?? $usage['input_tokens'] ?? null;
        $output = $usage['completion_tokens'] ?? $usage['output_tokens'] ?? null;
        if (!is_numeric($input) || !is_numeric($output)) {
            return null;
        }

        $cached = max(0, (int) ($usage['prompt_tokens_details']['cached_tokens']
            ?? $usage['input_tokens_details']['cached_tokens'] ?? 0));
        $written = max(0, (int) ($usage['prompt_tokens_details']['cache_write_tokens']
            ?? $usage['input_tokens_details']['cache_write_tokens'] ?? 0));
        $uncached = max(0, (int) $input - $cached - $written);

        return round((
            $uncached * (float) $rates['input']
            + $cached * (float) ($rates['cached_input'] ?? $rates['input'])
            + $written * (float) ($rates['cache_write'] ?? $rates['input'])
            + (int) $output * (float) $rates['output']
        ) / 1_000_000, 8);
    }

    private function langfuse(): LangfuseService
    {
        try {
            return app(LangfuseService::class);
        } catch (BindingResolutionException) {
            return new LangfuseService();
        }
    }
}
