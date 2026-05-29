<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Chatbot\LangfuseDashboardService;
use Mockery;
use Tests\TestCase;

class LangfuseDashboardAdminTest extends TestCase
{
    public function testAdminCanOpenLangfuseDashboard(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $mock = Mockery::mock(LangfuseDashboardService::class);
        $mock->shouldReceive('snapshot')
            ->once()
            ->with(24, 200)
            ->andReturn([
                'enabled' => true,
                'connected' => true,
                'error' => null,
                'base_url' => 'https://cloud.langfuse.com',
                'summary' => [
                    'total_observations' => 12,
                    'unique_traces' => 4,
                    'generation_count' => 6,
                    'error_count' => 1,
                    'success_count' => 11,
                    'error_rate' => 8.3,
                    'success_rate' => 91.7,
                    'avg_latency_ms' => 420,
                    'p95_latency_ms' => 780,
                    'total_tokens' => 120,
                    'total_cost' => 0.0123,
                    'avg_cost_per_generation' => 0.00205,
                    'slow_observation_count' => 2,
                ],
                'health' => [
                    'status' => 'warning',
                    'label' => 'საჭიროა ყურადღება',
                    'reasons' => ['შეცდომების წილი გაზრდილია.'],
                ],
                'recent_traces' => [],
                'error_breakdown' => [
                    [
                        'name' => 'chatbot.model_completion',
                        'count' => 1,
                        'affected_traces' => 1,
                        'error_rate' => 25.0,
                        'latest_at_label' => '2026-03-23 14:00',
                        'latest_message' => 'provider timeout',
                    ],
                ],
                'top_error_messages' => [
                    [
                        'message' => 'provider timeout',
                        'count' => 1,
                    ],
                ],
                'slow_observations' => [],
                'expensive_observations' => [],
                'top_observations' => [],
                'model_breakdown' => [
                    [
                        'model' => 'gpt-4.1-mini',
                        'count' => 3,
                        'generation_count' => 3,
                        'error_count' => 1,
                        'error_rate' => 33.3,
                        'avg_latency_ms' => 620,
                        'total_tokens' => 80,
                        'total_cost' => 0.0101,
                        'avg_cost' => 0.003367,
                    ],
                ],
                'top_models' => [],
                'meta' => [
                    'window_start' => now()->subDay()->toIso8601String(),
                    'window_end' => now()->toIso8601String(),
                    'observations_count' => 12,
                ],
            ]);

        $this->app->instance(LangfuseDashboardService::class, $mock);

        $response = $this->actingAs($admin)->get(route('admin.langfuse-dashboard'));

        $response->assertOk();
        $response->assertSee('Langfuse Dashboard');
        $response->assertSee('გახსენი სრული Langfuse');
        $response->assertSee('შეცდომების breakdown');
        $response->assertSee('მოდელები და ღირებულება');
        $response->assertSee('ბოლო Trace-ები');
    }
}
