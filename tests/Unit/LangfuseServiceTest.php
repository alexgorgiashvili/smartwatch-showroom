<?php

namespace Tests\Unit;

use App\Services\Chatbot\LangfuseService;
use App\Services\Chatbot\LangfuseTraceContext;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LangfuseServiceTest extends TestCase
{
    public function testItSendsTraceSpanGenerationAndTraceUpdateToIngestionEndpoint(): void
    {
        $baseUrl = 'https://cloud.langfuse.com';

        config()->set('services.langfuse.enabled', true);
        config()->set('services.langfuse.base_url', $baseUrl);
        config()->set('services.langfuse.public_key', 'pk-test');
        config()->set('services.langfuse.secret_key', 'sk-test');

        Http::fake();

        $this->app->scoped(LangfuseTraceContext::class);

        $service = app(LangfuseService::class);

        $traceId = $service->startTrace(
            'chatbot.widget.response',
            ['message' => 'გამარჯობა'],
            ['conversation_id' => 123, 'customer_id' => 456],
            '456',
            '123',
            'trace-test-123',
            ['chatbot', 'widget']
        );

        $spanId = $service->startSpan('intent.analyze', ['message' => 'გამარჯობა'], ['history_count' => 0]);

        $service->recordGeneration(
            'chatbot.intent_analyzer',
            'gpt-4.1-nano',
            [
                ['role' => 'system', 'content' => 'Return JSON only.'],
                ['role' => 'user', 'content' => 'გამარჯობა'],
            ],
            '{"intent":"general"}',
            ['prompt_tokens' => 10, 'completion_tokens' => 4],
            ['component' => 'intent_analyzer'],
            ['temperature' => 0.0, 'max_tokens' => 250],
            microtime(true) - 0.2,
            microtime(true)
        );

        $service->endSpan($spanId, ['intent' => 'general', 'confidence' => 0.7], 'general');
        $service->updateTrace(['intent' => 'general', 'intent_confidence' => 0.7], 'სალამი');

        Http::assertSentCount(5);

        Http::assertSent(function ($request) use ($traceId, $baseUrl) {
            $batch = $request->data()['batch'] ?? [];
            $event = $batch[0] ?? [];
            $body = $event['body'] ?? [];

            return $request->url() === $baseUrl . '/api/public/ingestion'
                && $event['type'] === 'trace-create'
                && $body['id'] === $traceId
                && $body['name'] === 'chatbot.widget.response';
        });

        Http::assertSent(function ($request) use ($traceId, $spanId) {
            $batch = $request->data()['batch'] ?? [];
            $event = $batch[0] ?? [];
            $body = $event['body'] ?? [];

            return $event['type'] === 'span-create'
                && $body['traceId'] === $traceId
                && $body['id'] === $spanId
                && $body['name'] === 'intent.analyze';
        });

        Http::assertSent(function ($request) use ($traceId, $spanId) {
            $batch = $request->data()['batch'] ?? [];
            $event = $batch[0] ?? [];
            $body = $event['body'] ?? [];
            $usage = $body['usage'] ?? [];

            return $event['type'] === 'generation-create'
                && $body['traceId'] === $traceId
                && $body['parentObservationId'] === $spanId
                && $body['model'] === 'gpt-4.1-nano'
                && ($usage['input'] ?? null) === 10
                && ($usage['output'] ?? null) === 4;
        });

        Http::assertSent(function ($request) use ($traceId, $spanId) {
            $batch = $request->data()['batch'] ?? [];
            $event = $batch[0] ?? [];
            $body = $event['body'] ?? [];

            return $event['type'] === 'span-update'
                && $body['traceId'] === $traceId
                && $body['id'] === $spanId;
        });

        Http::assertSent(function ($request) use ($traceId) {
            $batch = $request->data()['batch'] ?? [];
            $event = $batch[0] ?? [];
            $body = $event['body'] ?? [];
            $metadata = $body['metadata'] ?? [];

            return $event['type'] === 'trace-update'
                && $body['id'] === $traceId
                && ($metadata['intent'] ?? null) === 'general';
        });
    }
}
