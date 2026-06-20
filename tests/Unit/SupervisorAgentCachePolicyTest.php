<?php

namespace Tests\Unit;

use App\Services\Chatbot\Agents\ComparisonAgent;
use App\Services\Chatbot\Agents\GeneralAgent;
use App\Services\Chatbot\Agents\InventoryAgent;
use App\Services\Chatbot\Agents\SupervisorAgent;
use App\Services\Chatbot\Agents\VectorSqlReconciliationAgent;
use App\Services\Chatbot\BifurcatedMemoryService;
use App\Services\Chatbot\ChatbotFallbackStrategyService;
use App\Services\Chatbot\CircuitBreakerService;
use App\Services\Chatbot\IntentResult;
use App\Services\Chatbot\LangfuseService;
use App\Services\Chatbot\MultiLayerCacheService;
use App\Services\Chatbot\ParallelExecutionService;
use App\Services\Chatbot\SmartSearchOrchestrator;
use App\Services\Chatbot\WidgetTraceLogger;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class SupervisorAgentCachePolicyTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testSupervisorCachesValidatedResponses(): void
    {
        $intent = $this->priceIntent();
        [$supervisor, $cache, $langfuse, $circuitBreaker, $inventoryAgent, $parallelExecution, $memory] = $this->buildSupervisor();

        $cache->shouldReceive('getCachedResponse')
            ->once()
            ->with('Q19-ის ფასი?', Mockery::type(IntentResult::class))
            ->andReturnNull();
        $cache->shouldReceive('cacheResponse')
            ->once()
            ->with('Q19-ის ფასი?', Mockery::type(IntentResult::class), Mockery::type('string'), Mockery::on(function (array $metadata): bool {
                return ($metadata['validation_passed'] ?? null) === true;
            }));

        $executionResult = [
            'results' => [],
            'total_duration_ms' => 12,
            'parallel' => true,
        ];

        $parallelExecution->shouldReceive('execute')
            ->once()
            ->andReturn($executionResult);
        $parallelExecution->shouldReceive('getSuccessfulResults')
            ->once()
            ->with($executionResult)
            ->andReturn([
                'session' => ['recent' => [], 'summary' => null],
                'profile' => [],
            ]);
        $parallelExecution->shouldReceive('getStats')
            ->once()
            ->with($executionResult)
            ->andReturn([
                'total_tasks' => 3,
                'successful' => 3,
                'failed' => 0,
                'total_duration_ms' => 12,
                'avg_task_duration_ms' => 4,
                'parallel' => true,
                'efficiency_gain' => 0.0,
            ]);

        $memory->shouldReceive('scopePreferencesForMessage')
            ->twice()
            ->with([], 'Q19-ის ფასი?')
            ->andReturn([], []);

        $inventoryAgent->shouldReceive('handle')
            ->once()
            ->andReturn([
                'success' => true,
                'response' => 'Q19-ის ფასი 59 ₾ არის. [Q19](http://127.0.0.1:8000/products/q19-2g)',
                'validation_passed' => true,
                'reflection_attempts' => 0,
                'violations' => [],
                'validation_context' => ['products' => []],
                'reason' => null,
            ]);

        $circuitBreaker->shouldReceive('shouldAttemptMultiAgent')
            ->once()
            ->andReturn(true);
        $circuitBreaker->shouldReceive('recordSuccess')->once();
        $circuitBreaker->shouldNotReceive('recordFailure');

        $langfuse->shouldReceive('updateTrace')->once();
        $langfuse->shouldReceive('startSpan')->once()->andReturn('span-1');
        $langfuse->shouldReceive('endSpan')->once()->andReturnNull();

        $this->app->instance(LangfuseService::class, $langfuse);

        $result = $supervisor->orchestrate('Q19-ის ფასი?', 12, 34, $intent, [], []);

        $this->assertSame('Q19-ის ფასი 59 ₾ არის. [Q19](http://127.0.0.1:8000/products/q19-2g)', $result['response']);
        $this->assertTrue($result['validation_passed']);
    }

    public function testSupervisorSkipsCachingWhenValidationFails(): void
    {
        $intent = $this->priceIntent();
        [$supervisor, $cache, $langfuse, $circuitBreaker, $inventoryAgent, $parallelExecution, $memory] = $this->buildSupervisor();

        $cache->shouldReceive('getCachedResponse')
            ->once()
            ->with('Q19-ის ფასი?', Mockery::type(IntentResult::class))
            ->andReturnNull();
        $cache->shouldNotReceive('cacheResponse');

        $executionResult = [
            'results' => [],
            'total_duration_ms' => 12,
            'parallel' => true,
        ];

        $parallelExecution->shouldReceive('execute')
            ->once()
            ->andReturn($executionResult);
        $parallelExecution->shouldReceive('getSuccessfulResults')
            ->once()
            ->with($executionResult)
            ->andReturn([
                'session' => ['recent' => [], 'summary' => null],
                'profile' => [],
            ]);
        $parallelExecution->shouldReceive('getStats')
            ->once()
            ->with($executionResult)
            ->andReturn([
                'total_tasks' => 3,
                'successful' => 3,
                'failed' => 0,
                'total_duration_ms' => 12,
                'avg_task_duration_ms' => 4,
                'parallel' => true,
                'efficiency_gain' => 0.0,
            ]);

        $memory->shouldReceive('scopePreferencesForMessage')
            ->twice()
            ->with([], 'Q19-ის ფასი?')
            ->andReturn([], []);

        $inventoryAgent->shouldReceive('handle')
            ->once()
            ->andReturn([
                'success' => true,
                'response' => 'Q19-ის ფასი შესაძლოა 59 ₾ იყოს.',
                'validation_passed' => false,
                'reflection_attempts' => 0,
                'violations' => [],
                'validation_context' => ['products' => []],
                'reason' => null,
            ]);

        $circuitBreaker->shouldReceive('shouldAttemptMultiAgent')
            ->once()
            ->andReturn(true);
        $circuitBreaker->shouldReceive('recordSuccess')->once();
        $circuitBreaker->shouldNotReceive('recordFailure');

        $langfuse->shouldReceive('updateTrace')->once();
        $langfuse->shouldReceive('startSpan')->once()->andReturn('span-1');
        $langfuse->shouldReceive('endSpan')->once()->andReturnNull();

        $this->app->instance(LangfuseService::class, $langfuse);

        $result = $supervisor->orchestrate('Q19-ის ფასი?', 12, 34, $intent, [], []);

        $this->assertSame('Q19-ის ფასი შესაძლოა 59 ₾ იყოს.', $result['response']);
        $this->assertFalse($result['validation_passed']);
    }

    public function testSupervisorReturnsValidatedCacheHitsWithoutFallback(): void
    {
        $intent = $this->priceIntent();
        [$supervisor, $cache, $langfuse, $circuitBreaker, $inventoryAgent, $parallelExecution, $memory] = $this->buildSupervisor();

        $cache->shouldReceive('getCachedResponse')
            ->once()
            ->with('Q19-ის ფასი?', Mockery::type(IntentResult::class))
            ->andReturn([
                'response' => 'Q19-ის ფასი 59 ₾ არის.',
                'cache_layer' => 'exact',
                'metadata' => [
                    'validation_passed' => true,
                    'validation_context' => ['products' => [['name' => 'Q19']]],
                ],
            ]);
        $cache->shouldNotReceive('cacheResponse');

        $memory->shouldReceive('scopePreferencesForMessage')
            ->once()
            ->with([], 'Q19-ის ფასი?')
            ->andReturn([]);

        $circuitBreaker->shouldReceive('shouldAttemptMultiAgent')
            ->once()
            ->andReturn(true);
        $circuitBreaker->shouldNotReceive('recordSuccess');
        $circuitBreaker->shouldNotReceive('recordFailure');

        $parallelExecution->shouldNotReceive('execute');
        $parallelExecution->shouldNotReceive('getSuccessfulResults');
        $parallelExecution->shouldNotReceive('getStats');
        $inventoryAgent->shouldNotReceive('handle');

        $langfuse->shouldReceive('updateTrace')->once();
        $langfuse->shouldReceive('startSpan')->once()->andReturn('span-1');
        $langfuse->shouldReceive('endSpan')->once()->andReturnNull();

        $this->app->instance(LangfuseService::class, $langfuse);

        $result = $supervisor->orchestrate('Q19-ის ფასი?', 12, 34, $intent, [], []);

        $this->assertTrue($result['cached']);
        $this->assertSame('exact', $result['cache_layer']);
        $this->assertTrue($result['validation_passed']);
        $this->assertNull($result['reason']);
        $this->assertSame('Q19-ის ფასი 59 ₾ არის.', $result['response']);
    }

    /**
     * @return array{0: SupervisorAgent, 1: \Mockery\MockInterface, 2: \Mockery\MockInterface, 3: \Mockery\MockInterface, 4: \Mockery\MockInterface, 5: \Mockery\MockInterface, 6: \Mockery\MockInterface}
     */
    private function buildSupervisor(): array
    {
        $circuitBreaker = Mockery::mock(CircuitBreakerService::class);
        $cache = Mockery::mock(MultiLayerCacheService::class);
        $parallelExecution = Mockery::mock(ParallelExecutionService::class);
        $searchOrchestrator = Mockery::mock(SmartSearchOrchestrator::class);
        $memory = Mockery::mock(BifurcatedMemoryService::class);
        $reconciliation = Mockery::mock(VectorSqlReconciliationAgent::class);
        $inventoryAgent = Mockery::mock(InventoryAgent::class);
        $comparisonAgent = Mockery::mock(ComparisonAgent::class);
        $generalAgent = Mockery::mock(GeneralAgent::class);
        $fallbackStrategy = Mockery::mock(ChatbotFallbackStrategyService::class);
        $widgetTrace = Mockery::mock(WidgetTraceLogger::class);
        $langfuse = Mockery::mock(LangfuseService::class);

        $widgetTrace->shouldReceive('enabled')->andReturn(false);
        $fallbackStrategy->shouldReceive('resolveProviderFailureOutcome')->never();
        $comparisonAgent->shouldReceive('handle')->never();
        $generalAgent->shouldReceive('handle')->never();

        $supervisor = new SupervisorAgent(
            $circuitBreaker,
            $cache,
            $parallelExecution,
            $searchOrchestrator,
            $memory,
            $reconciliation,
            $inventoryAgent,
            $comparisonAgent,
            $generalAgent,
            $fallbackStrategy,
            $widgetTrace
        );

        return [$supervisor, $cache, $langfuse, $circuitBreaker, $inventoryAgent, $parallelExecution, $memory];
    }

    private function priceIntent(): IntentResult
    {
        return IntentResult::fromArray([
            'standalone_query' => 'Q19-ის ფასი?',
            'intent' => 'price_query',
            'entities' => [
                'brand' => null,
                'model' => 'Q19',
                'product_slug_hint' => 'q19-2g',
                'color' => null,
                'category' => null,
            ],
            'needs_product_data' => true,
            'search_keywords' => ['Q19', 'ფასი'],
            'is_out_of_domain' => false,
            'confidence' => 0.96,
        ], 0);
    }
}
