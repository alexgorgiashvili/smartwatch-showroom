<?php

namespace App\Services\Chatbot\Agents;

use App\Services\Chatbot\CircuitBreakerService;
use App\Services\Chatbot\IntentResult;
use App\Services\Chatbot\MultiLayerCacheService;
use App\Services\Chatbot\ParallelExecutionService;
use App\Services\Chatbot\SmartSearchOrchestrator;
use App\Services\Chatbot\BifurcatedMemoryService;
use App\Services\Chatbot\WidgetTraceLogger;

class SupervisorAgent
{
    public function __construct(
        private CircuitBreakerService $circuitBreaker,
        private MultiLayerCacheService $cache,
        private ParallelExecutionService $parallelExecution,
        private SmartSearchOrchestrator $searchOrchestrator,
        private BifurcatedMemoryService $memory,
        private VectorSqlReconciliationAgent $reconciliation,
        private InventoryAgent $inventoryAgent,
        private ComparisonAgent $comparisonAgent,
        private GeneralAgent $generalAgent,
        private WidgetTraceLogger $widgetTrace
    ) {
    }

    /**
     * Main orchestration method
     */
    public function orchestrate(
        string $message,
        int $conversationId,
        int $customerId,
        IntentResult $intent,
        array $preferences,
        array $trace = []
    ): array {
        $this->traceWidget('supervisor.started', [
            'intent' => $intent->intent(),
            'confidence' => $intent->confidence(),
        ], $trace);

        if (!$this->circuitBreaker->shouldAttemptMultiAgent()) {
            $this->traceWidget('supervisor.circuit_open', [
                'state' => $this->circuitBreaker->getState(),
            ], $trace);

            throw new \RuntimeException('Circuit breaker is open');
        }

        $cachedResponse = $this->cache->getCachedResponse($message, $intent);
        if ($cachedResponse) {
            $this->traceWidget('supervisor.cache_hit', [
                'cache_layer' => $cachedResponse['cache_layer'],
            ], $trace);

            return [
                'response' => $cachedResponse['response'],
                'cached' => true,
                'cache_layer' => $cachedResponse['cache_layer'],
            ];
        }

        $this->traceWidget('supervisor.cache_miss', [], $trace);

        $this->traceWidget('supervisor.parallel_fanout_started', [], $trace);

        $parallelResult = $this->parallelExecution->execute([
            'search' => fn() => $intent->requiresSearch() 
                ? $this->searchOrchestrator->search($intent)
                : null,
            'session' => fn() => $this->memory->getSessionContext($conversationId),
            'profile' => fn() => $this->memory->getUserPreferences($customerId),
        ]);

        $this->traceWidget('supervisor.parallel_fanout_completed', [
            'duration_ms' => $parallelResult['total_duration_ms'],
            'stats' => $this->parallelExecution->getStats($parallelResult),
        ], $trace);

        $results = $this->parallelExecution->getSuccessfulResults($parallelResult);

        $searchContext = $results['search'] ?? null;
        $sessionContext = $results['session'] ?? ['recent' => [], 'summary' => null];
        $userPreferences = array_merge($preferences, $results['profile'] ?? []);

        if ($searchContext && $searchContext->products()->isNotEmpty()) {
            $this->traceWidget('supervisor.reconciliation_started', [
                'product_count' => $searchContext->products()->count(),
            ], $trace);

            $reconciled = $this->reconciliation->reconcile(
                $searchContext->products(),
                $intent
            );

            $this->traceWidget('supervisor.reconciliation_completed', [
                'reconciled_count' => $reconciled['products']->count(),
                'out_of_stock_filtered' => $reconciled['out_of_stock_count'],
            ], $trace);
        } else {
            $reconciled = [
                'products' => collect(),
                'out_of_stock_count' => 0,
            ];
        }

        $agent = $this->routeToAgent($intent);

        $this->traceWidget('supervisor.routing', [
            'agent' => get_class($agent),
            'intent' => $intent->intent(),
        ], $trace);

        $agentResult = $agent->handle(
            $message,
            $conversationId,
            $intent,
            $searchContext,
            $reconciled['products'],
            $sessionContext,
            $userPreferences,
            $trace
        );

        if ($agentResult['success']) {
            $this->cache->cacheResponse($message, $intent, $agentResult['response'], [
                'agent' => get_class($agent),
                'validation_passed' => $agentResult['validation_passed'] ?? false,
            ]);
        }

        $this->traceWidget('supervisor.completed', [
            'agent' => get_class($agent),
            'success' => $agentResult['success'],
        ], $trace);

        return $agentResult;
    }

    /**
     * Route to appropriate specialized agent
     */
    private function routeToAgent(IntentResult $intent): InventoryAgent|ComparisonAgent|GeneralAgent
    {
        return match ($intent->intent()) {
            'price_query', 'stock_query' => $this->inventoryAgent,
            'comparison' => $this->comparisonAgent,
            default => $this->generalAgent,
        };
    }

    private function traceWidget(string $step, array $context, array $trace): void
    {
        if (!$this->widgetTrace->enabled()) {
            return;
        }

        $this->widgetTrace->logStep($step, array_merge($trace, $context));
    }
}
