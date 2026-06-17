<?php

namespace App\Services\Chatbot;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LangfuseService
{
    public function enabled(): bool
    {
        if (app()->environment('testing')) {
            return false;
        }

        return (bool) config('services.langfuse.enabled', false)
            && $this->publicKey() !== ''
            && $this->secretKey() !== ''
            && $this->baseUrl() !== '';
    }

    public function baseUrl(): string
    {
        return rtrim((string) config('services.langfuse.base_url', 'https://cloud.langfuse.com'), '/');
    }

    public function currentTraceId(): ?string
    {
        return $this->context()->traceId();
    }

    public function startTrace(
        string $name,
        mixed $input = null,
        array $metadata = [],
        ?string $userId = null,
        ?string $sessionId = null,
        ?string $traceId = null,
        array $tags = []
    ): ?string {
        if (!$this->enabled()) {
            return null;
        }

        $traceId ??= (string) Str::uuid();

        $body = array_filter([
            'id' => $traceId,
            'name' => $name,
            'input' => $this->normalizeValue($input),
            'userId' => $userId,
            'sessionId' => $sessionId,
            'metadata' => $metadata !== [] ? $metadata : null,
            'tags' => $tags !== [] ? array_values($tags) : null,
        ], fn ($value) => $value !== null);

        $this->sendBatch([
            $this->event('trace-create', $body),
        ]);

        $this->context()->setTrace($traceId, $metadata);

        return $traceId;
    }

    public function updateTrace(array $metadata = [], mixed $output = null, ?string $name = null, array $tags = []): void
    {
        if (!$this->enabled() || !$this->context()->traceId()) {
            return;
        }

        $mergedMetadata = array_merge($this->context()->metadata(), $metadata);
        $body = array_filter([
            'id' => $this->context()->traceId(),
            'name' => $name,
            'output' => $this->normalizeValue($output),
            'metadata' => $mergedMetadata !== [] ? $mergedMetadata : null,
            'tags' => $tags !== [] ? array_values($tags) : null,
        ], fn ($value) => $value !== null);

        $this->sendBatch([
            $this->event('trace-update', $body),
        ]);

        $this->context()->setTrace($this->context()->traceId(), $mergedMetadata);
    }

    public function startSpan(string $name, mixed $input = null, array $metadata = []): ?string
    {
        if (!$this->enabled() || !$this->context()->traceId()) {
            return null;
        }

        $observationId = (string) Str::uuid();
        $body = array_filter([
            'id' => $observationId,
            'traceId' => $this->context()->traceId(),
            'parentObservationId' => $this->context()->currentObservationId(),
            'name' => $name,
            'startTime' => $this->timestamp(),
            'input' => $this->normalizeValue($input),
            'metadata' => $metadata !== [] ? $metadata : null,
        ], fn ($value) => $value !== null);

        $this->sendBatch([
            $this->event('span-create', $body),
        ]);

        $this->context()->pushObservation($observationId);

        return $observationId;
    }

    public function endSpan(?string $observationId, array $metadata = [], mixed $output = null, ?string $statusMessage = null): void
    {
        if (!$this->enabled() || !$this->context()->traceId() || !$observationId) {
            return;
        }

        $body = array_filter([
            'id' => $observationId,
            'traceId' => $this->context()->traceId(),
            'endTime' => $this->timestamp(),
            'output' => $this->normalizeValue($output),
            'metadata' => $metadata !== [] ? $metadata : null,
            'statusMessage' => $statusMessage,
        ], fn ($value) => $value !== null);

        $this->sendBatch([
            $this->event('span-update', $body),
        ]);

        if ($this->context()->currentObservationId() === $observationId) {
            $this->context()->popObservation();
        }
    }

    public function recordGeneration(
        string $name,
        string $model,
        array $messages,
        ?string $output,
        array $usage = [],
        array $metadata = [],
        array $modelParameters = [],
        ?float $startedAt = null,
        ?float $endedAt = null
    ): void {
        if (!$this->enabled() || !$this->context()->traceId()) {
            return;
        }

        $body = array_filter([
            'id' => (string) Str::uuid(),
            'traceId' => $this->context()->traceId(),
            'parentObservationId' => $this->context()->currentObservationId(),
            'name' => $name,
            'startTime' => $this->timestamp($startedAt),
            'endTime' => $this->timestamp($endedAt),
            'model' => $model,
            'modelParameters' => $modelParameters !== [] ? $modelParameters : null,
            'input' => $this->normalizeValue($messages),
            'output' => $this->normalizeValue($output),
            'usage' => $this->normalizeUsage($usage),
            'metadata' => $metadata !== [] ? $metadata : null,
        ], fn ($value) => $value !== null);

        $this->sendBatch([
            $this->event('generation-create', $body),
        ]);
    }

    public function clearContext(): void
    {
        $this->context()->clear();
    }

    private function context(): LangfuseTraceContext
    {
        try {
            return app(LangfuseTraceContext::class);
        } catch (BindingResolutionException) {
            return new LangfuseTraceContext();
        }
    }

    private array $pendingEvents = [];

    private function sendBatch(array $events): void
    {
        if ($events === []) {
            return;
        }

        $this->pendingEvents = array_merge($this->pendingEvents, $events);

        if (!app()->bound('langfuse.terminating')) {
            app()->instance('langfuse.terminating', true);
            app()->terminating(function () {
                $this->flush();
            });
        }
    }

    public function flush(): void
    {
        if (empty($this->pendingEvents)) {
            return;
        }

        $events = $this->pendingEvents;
        $this->pendingEvents = [];

        try {
            $response = Http::withBasicAuth($this->publicKey(), $this->secretKey())
                ->timeout((int) config('services.langfuse.timeout', 5))
                ->post($this->baseUrl() . '/api/public/ingestion', [
                    'batch' => $events,
                ]);

            if (!$response->successful() && $response->status() !== 207) {
                Log::warning('Langfuse ingestion failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $exception) {
            Log::warning('Langfuse ingestion exception', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function event(string $type, array $body): array
    {
        return [
            'id' => (string) Str::uuid(),
            'type' => $type,
            'timestamp' => $this->timestamp(),
            'body' => $body,
        ];
    }

    private function timestamp(?float $microtime = null): string
    {
        $time = $microtime ?? microtime(true);
        $seconds = (int) floor($time);
        $milliseconds = (int) round(($time - $seconds) * 1000);

        return gmdate('Y-m-d\TH:i:s', $seconds) . sprintf('.%03dZ', $milliseconds);
    }

    private function publicKey(): string
    {
        return trim((string) config('services.langfuse.public_key', ''));
    }

    private function secretKey(): string
    {
        return trim((string) config('services.langfuse.secret_key', ''));
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function normalizeUsage(array $usage): ?array
    {
        $input = $usage['prompt_tokens'] ?? $usage['input'] ?? null;
        $output = $usage['completion_tokens'] ?? $usage['output'] ?? null;

        if ($input === null && $output === null) {
            return null;
        }

        return array_filter([
            'input' => $input !== null ? (int) $input : null,
            'output' => $output !== null ? (int) $output : null,
            'unit' => 'TOKENS',
        ], fn ($value) => $value !== null);
    }
}
