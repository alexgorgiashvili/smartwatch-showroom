<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Chatbot\WidgetTraceReadService;
use Mockery;
use Tests\TestCase;

class ChatbotTracesPjaxTest extends TestCase
{
    public function testAdminCanOpenChatbotTracesViaPjax(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $mock = Mockery::mock(WidgetTraceReadService::class);
        $mock->shouldReceive('pipelineSnapshot')
            ->once()
            ->with(24, '', false, false, 300)
            ->andReturn([
                'entries' => [],
                'summary' => [
                    'total_steps' => 0,
                    'unique_traces' => 0,
                    'avg_response_time_ms' => 0,
                    'fallback_rate' => 0,
                    'multi_agent_rate' => 0,
                    'validation_pass_rate' => 0,
                ],
                'meta' => [
                    'window_start' => now()->subDay()->toIso8601String(),
                    'window_end' => now()->toIso8601String(),
                ],
            ]);

        $this->app->instance(WidgetTraceReadService::class, $mock);

        $response = $this->actingAs($admin)->get(route('admin.chatbot-traces'), [
            'X-PJAX' => 'true',
        ]);

        $response->assertOk();
        $response->assertSee('ჩატბოტის ტრეისები');
    }
}
