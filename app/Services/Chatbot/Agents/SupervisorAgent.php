<?php

namespace App\Services\Chatbot\Agents;

use App\Services\Chatbot\LangfuseService;
use App\Services\Chatbot\ChatbotOutcomeReason;
use App\Services\Chatbot\CircuitBreakerService;
use App\Services\Chatbot\ChatbotFallbackStrategyService;
use App\Services\Chatbot\IntentResult;
use App\Services\Chatbot\MultiLayerCacheService;
use App\Services\Chatbot\ParallelExecutionService;
use App\Services\Chatbot\SmartSearchOrchestrator;
use App\Services\Chatbot\BifurcatedMemoryService;
use App\Services\Chatbot\WidgetTraceLogger;
use Illuminate\Support\Collection;

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
        private ChatbotFallbackStrategyService $fallbackStrategy,
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
        array $trace = [],
        string $executionMode = 'single_agent'
    ): array {
        $this->langfuse()->updateTrace([
            'conversation_id' => $conversationId,
            'customer_id' => $customerId,
            'intent' => $intent->intent(),
            'intent_confidence' => $intent->confidence(),
            'execution_mode' => $executionMode,
        ], null, 'chatbot.widget.response');

        $spanId = $this->langfuse()->startSpan('supervisor.orchestrate', [
            'message' => $message,
        ], [
            'conversation_id' => $conversationId,
            'customer_id' => $customerId,
            'intent' => $intent->intent(),
        ]);

        $this->traceWidget('supervisor.started', [
            'intent' => $intent->intent(),
            'confidence' => $intent->confidence(),
        ], $trace);

        $extractedPreferences = $this->memory->scopePreferencesForMessage($preferences, $message);

        if (!$this->circuitBreaker->shouldAttemptMultiAgent()) {
            $sessionContext = $this->memory->getSessionContext($conversationId);
            $fallbackResolution = $this->fallbackStrategy->resolveProviderFailureOutcome(
                $intent,
                ['products' => []],
                $sessionContext['recent'] ?? [],
                $preferences
            );

            $this->traceWidget('supervisor.circuit_open', [
                'state' => $this->circuitBreaker->getState(),
            ], $trace);

            $this->traceWidget('supervisor.circuit_open_fallback', [
                'intent' => $intent->intent(),
                'fallback_reply' => $this->widgetTrace->payloadsEnabled() ? $fallbackResolution->reply() : null,
            ], $trace);

            $this->langfuse()->endSpan($spanId, [
                'state' => $this->circuitBreaker->getState(),
                'reason' => 'circuit_open',
            ], $fallbackResolution->reply());

            return [
                'success' => true,
                'response' => $fallbackResolution->reply(),
                'cached' => false,
                'cache_layer' => 'circuit_breaker_fallback',
                'agent_used' => 'circuit_breaker',
                'execution_mode' => 'fallback',
                'validation_passed' => $fallbackResolution->validationPassed(),
                'validation_context' => ['products' => []],
                'reflection_attempts' => 0,
                'violations' => $fallbackResolution->validationViolations(),
                'reason' => ChatbotOutcomeReason::PROVIDER_UNAVAILABLE,
                'extracted_preferences' => $extractedPreferences,
            ];
        }

        $cachedResponse = $this->cache->getCachedResponse($message, $intent);
        if ($cachedResponse && !$this->shouldBypassCache($message, $intent, $cachedResponse)) {
            $this->traceWidget('supervisor.cache_hit', [
                'cache_layer' => $cachedResponse['cache_layer'],
            ], $trace);

            $this->langfuse()->endSpan($spanId, [
                'cached' => true,
                'cache_layer' => $cachedResponse['cache_layer'],
            ], $cachedResponse['response']);

            return [
                'success' => true,
                'response' => $cachedResponse['response'],
                'cached' => true,
                'cache_layer' => $cachedResponse['cache_layer'],
                'agent_used' => 'cache',
                'execution_mode' => 'cache',
                'validation_passed' => (bool) data_get($cachedResponse, 'metadata.validation_passed', true),
                'validation_context' => data_get($cachedResponse, 'metadata.validation_context', ['products' => []]),
                'reflection_attempts' => 0,
                'violations' => [],
                'reason' => null,
                'extracted_preferences' => $extractedPreferences,
            ];
        }

        if ($cachedResponse) {
            $this->traceWidget('supervisor.cache_bypassed', [
                'cache_layer' => $cachedResponse['cache_layer'] ?? null,
                'reason' => 'stale_or_overly_generic_catalog_denial',
            ], $trace);
        }

        $this->traceWidget('supervisor.cache_miss', [], $trace);
        $searchContext = null;

        $this->traceWidget('supervisor.parallel_fanout_started', [], $trace);

        $parallelResult = $this->parallelExecution->execute([
            'search' => fn() => $intent->requiresSearch()
                ? $this->searchOrchestrator->search($intent)
                : null,
            'session' => fn() => $this->memory->getSessionContext($conversationId),
            'profile' => fn() => $this->memory->getUserPreferences($customerId),
        ]);

        $results = $this->parallelExecution->getSuccessfulResults($parallelResult);

        $searchContext = $results['search'] ?? null;
        $sessionContext = $results['session'] ?? ['recent' => [], 'summary' => null];
        $userPreferences = array_merge($preferences, $results['profile'] ?? []);
        $extractedPreferences = $this->memory->scopePreferencesForMessage($userPreferences, $message);

        $this->traceWidget('supervisor.parallel_fanout_completed', [
            'duration_ms' => $parallelResult['total_duration_ms'],
            'stats' => $this->parallelExecution->getStats($parallelResult),
            'search_product_count' => $searchContext?->products()->count() ?? 0,
            'has_rag_context' => trim((string) ($searchContext?->ragContext() ?? '')) !== '',
            'search_not_found_message' => $searchContext?->productNotFoundMessage(),
        ], $trace);

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
            'agent_basename' => class_basename($agent),
            'intent' => $intent->intent(),
            'routing_reason' => $this->routingReason($intent),
            'routing_rules' => [
                'price_query|stock_query -> InventoryAgent',
                'comparison -> ComparisonAgent',
                'default -> GeneralAgent',
            ],
            'selected_products' => $this->productSnapshot($reconciled['products']),
        ], $trace);

        try {
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

            // Mocked generation of alternative suggestions when in suggestion_generation mode
            if ($executionMode === 'suggestion_generation' && $agentResult['success']) {
                $agentResult['response'] = [$agentResult['response'], "Alternative response 1", "Alternative response 2"];
            }

            $providerFailure = in_array(
                $agentResult['reason'] ?? null,
                [ChatbotOutcomeReason::PROVIDER_UNAVAILABLE, ChatbotOutcomeReason::PROVIDER_EXCEPTION],
                true
            );

            if ($providerFailure) {
                $this->circuitBreaker->recordFailure((string) ($agentResult['reason'] ?? ''));
            } else {
                $this->circuitBreaker->recordSuccess();
            }

            // Only cache fully validated model outputs. Fallback replies are
            // cheap to regenerate and should not poison exact-match cache.
            if (
                $agentResult['success']
                && is_string($agentResult['response'] ?? null)
                && ($agentResult['validation_passed'] ?? false)
                && ($agentResult['reason'] ?? null) === null
            ) {
                $this->cache->cacheResponse($message, $intent, $agentResult['response'], [
                    'agent' => get_class($agent),
                    'validation_passed' => $agentResult['validation_passed'] ?? false,
                    'validation_context' => $agentResult['validation_context'] ?? ['products' => []],
                ]);
            }

            $this->traceWidget('supervisor.completed', [
                'agent' => get_class($agent),
                'success' => $agentResult['success'],
            ], $trace);

            $this->langfuse()->endSpan($spanId, [
                'agent' => class_basename($agent),
                'success' => $agentResult['success'],
                'validation_passed' => $agentResult['validation_passed'] ?? false,
                'reflection_attempts' => $agentResult['reflection_attempts'] ?? 0,
                'reason' => $agentResult['reason'] ?? null,
            ], $agentResult['response'] ?? null);

            return array_merge($agentResult, [
                'agent_used' => class_basename($agent),
                'execution_mode' => 'single_agent',
                'extracted_preferences' => $extractedPreferences,
            ]);
        } catch (\Throwable $e) {
            $this->circuitBreaker->recordFailure($e->getMessage());
            $this->langfuse()->endSpan($spanId, [
                'success' => false,
                'error' => $e->getMessage(),
            ], null, $e->getMessage());
            throw $e;
        }
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

    private function routingReason(IntentResult $intent): string
    {
        return match ($intent->intent()) {
            'price_query', 'stock_query' => 'Inventory კითხვები გადადის InventoryAgent-ზე',
            'comparison' => 'შედარების კითხვები გადადის ComparisonAgent-ზე',
            default => 'დანარჩენი მოთხოვნები გადადის GeneralAgent-ზე',
        };
    }

    private function productSnapshot(Collection $products): array
    {
        return $products
            ->take(5)
            ->map(static function ($product): array {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'price' => $product->sale_price ?: $product->price,
                    'stock' => (int) ($product->total_stock ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    private function traceWidget(string $step, array $context, array $trace): void
    {
        if (!$this->widgetTrace->enabled()) {
            return;
        }

        $this->widgetTrace->logStep($step, array_merge($trace, $context));
    }

    private function langfuse(): LangfuseService
    {
        return app(LangfuseService::class);
    }

    /**
     * Product-seeking questions are sensitive to catalog freshness, so we
     * avoid replaying stale cached denials and force a fresh search.
     *
     * @param array<string, mixed> $cachedResponse
     */
    private function shouldBypassCache(string $message, IntentResult $intent, array $cachedResponse): bool
    {
        $response = mb_strtolower((string) ($cachedResponse['response'] ?? ''));
        $normalizedMessage = mb_strtolower($message);

        if (
            in_array($intent->intent(), ['stock_query', 'general'], true)
            && collect(['ახალი მოდელ', 'new model', 'current model', 'ახლა რა გაქვთ'])
                ->contains(fn (string $signal): bool => str_contains($normalizedMessage, $signal))
        ) {
            return true;
        }

        $denialPhrases = [
            'არ გვაქვს',
            'არ შეიცავს',
            'კატალოგი არ შეიცავს',
            'კონკრეტული მოდელები არ არის',
            'ახალი მოდელები ჩვენს კატალოგში არ არის',
            'ვერ გირჩევთ',
            'ვერ გითხრით',
        ];

        $productSeekingSignals = [
            'მირჩიე',
            'რა გაქვთ',
            'რომელი',
            'მოდელი',
            'მოდელები',
            'gps',
            '4g',
            '2g',
            'ბიუჯეტ',
            'ფასი',
            'პატარა მაჯ',
            'ახალი',
        ];

        $isProductSeekingIntent = in_array($intent->intent(), [
            'recommendation',
            'stock_query',
            'price_query',
            'comparison',
        ], true);

        if (!$isProductSeekingIntent) {
            return false;
        }

        $hasDenial = collect($denialPhrases)->contains(
            fn (string $phrase): bool => str_contains($response, $phrase)
        );

        if (!$hasDenial) {
            return false;
        }

        return collect($productSeekingSignals)->contains(
            fn (string $signal): bool => str_contains($normalizedMessage, $signal)
        );
    }
}
