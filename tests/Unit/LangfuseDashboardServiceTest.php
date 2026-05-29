<?php

namespace Tests\Unit;

use App\Services\Chatbot\LangfuseDashboardService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LangfuseDashboardServiceTest extends TestCase
{
    public function testItBuildsDashboardSnapshotFromObservationsApi(): void
    {
        config()->set('services.langfuse.enabled', true);
        config()->set('services.langfuse.base_url', 'https://cloud.langfuse.com');
        config()->set('services.langfuse.public_key', 'pk-test');
        config()->set('services.langfuse.secret_key', 'sk-test');
        config()->set('services.langfuse.timeout', 5);

        Http::fake([
            'https://cloud.langfuse.com/api/public/v2/observations*' => Http::response([
                'data' => [
                    [
                        'id' => 'obs-1',
                        'traceId' => 'trace-1',
                        'projectId' => 'project-1',
                        'type' => 'GENERATION',
                        'name' => 'chatbot.intent_analyzer',
                        'model' => 'gpt-4.1-nano',
                        'startTime' => '2026-03-22T18:00:00Z',
                        'userId' => '55',
                        'sessionId' => '101',
                        'latency' => 0.42,
                        'totalUsage' => 14,
                        'totalCost' => 0.0012,
                        'statusMessage' => '',
                    ],
                    [
                        'id' => 'obs-2',
                        'traceId' => 'trace-1',
                        'projectId' => 'project-1',
                        'type' => 'SPAN',
                        'name' => 'supervisor.orchestrate',
                        'startTime' => '2026-03-22T18:00:01Z',
                        'latency' => 0.30,
                        'statusMessage' => '',
                    ],
                    [
                        'id' => 'obs-3',
                        'traceId' => 'trace-2',
                        'projectId' => 'project-1',
                        'type' => 'GENERATION',
                        'name' => 'chatbot.model_completion',
                        'model' => 'gpt-4.1-mini',
                        'startTime' => '2026-03-22T17:30:00Z',
                        'userId' => '77',
                        'sessionId' => '202',
                        'latency' => 0.85,
                        'totalUsage' => 22,
                        'totalCost' => 0.0023,
                        'statusMessage' => 'provider timeout',
                    ],
                ],
                'meta' => [
                    'cursor' => null,
                ],
            ], 200),
        ]);

        $snapshot = app(LangfuseDashboardService::class)->snapshot(24, 50);

        $this->assertTrue($snapshot['enabled']);
        $this->assertTrue($snapshot['connected']);
        $this->assertSame(3, $snapshot['summary']['total_observations']);
        $this->assertSame(2, $snapshot['summary']['unique_traces']);
        $this->assertSame(2, $snapshot['summary']['generation_count']);
        $this->assertSame(1, $snapshot['summary']['error_count']);
        $this->assertSame(2, $snapshot['summary']['success_count']);
        $this->assertSame(33.3, $snapshot['summary']['error_rate']);
        $this->assertSame(66.7, $snapshot['summary']['success_rate']);
        $this->assertSame(523, $snapshot['summary']['avg_latency_ms']);
        $this->assertSame(850, $snapshot['summary']['p95_latency_ms']);
        $this->assertSame(36, $snapshot['summary']['total_tokens']);
        $this->assertSame(0.0035, $snapshot['summary']['total_cost']);
        $this->assertSame('საჭიროა სწრაფი რეაგირება', $snapshot['health']['label']);
        $this->assertSame('chatbot.model_completion', $snapshot['error_breakdown'][0]['name']);
        $this->assertSame('provider timeout', $snapshot['top_error_messages'][0]['message']);
        $this->assertSame('chatbot.model_completion', $snapshot['slow_observations'][0]['name']);
        $this->assertSame('gpt-4.1-mini', $snapshot['model_breakdown'][0]['model']);
        $this->assertSame('https://cloud.langfuse.com/project/project-1/traces/trace-1', $snapshot['recent_traces'][0]['trace_url']);
        $this->assertSame('trace-1', $snapshot['recent_traces'][0]['trace_id']);
        $this->assertSame('chatbot.intent_analyzer', $snapshot['top_observations'][0]['name']);
        $this->assertSame('gpt-4.1-nano', $snapshot['top_models'][0]['model']);
    }
}
