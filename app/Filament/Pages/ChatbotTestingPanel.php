<?php

namespace App\Filament\Pages;

use App\Services\Chatbot\Agents\SupervisorAgent;
use App\Services\Chatbot\BifurcatedMemoryService;
use App\Services\Chatbot\CircuitBreakerService;
use App\Services\Chatbot\InputGuardService;
use App\Services\Chatbot\IntentAnalyzerService;
use App\Services\Chatbot\MultiLayerCacheService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;

class ChatbotTestingPanel extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static string $view = 'filament.pages.chatbot-testing-panel';

    protected static ?string $navigationGroup = 'AI Lab';

    protected static ?string $navigationLabel = 'ტესტირების პანელი';

    protected static ?string $title = 'ჩატბოტის ტესტირების პანელი';

    protected static ?int $navigationSort = 1;

    public string $message = '';

    public array $conversation = [];

    public ?array $lastMetrics = null;

    public ?array $lastExecutionPath = null;

    public ?array $lastDebugInfo = null;

    public bool $cacheBypass = false;

    public bool $streamingEnabled = false;

    protected function getViewData(): array
    {
        return [
            'conversation' => $this->conversation,
            'lastMetrics' => $this->lastMetrics,
            'lastExecutionPath' => $this->lastExecutionPath,
            'lastDebugInfo' => $this->lastDebugInfo,
            'circuitBreakerStats' => app(CircuitBreakerService::class)->getStats(),
            'cacheStats' => app(MultiLayerCacheService::class)->getStats(),
        ];
    }

    public function sendMessage(): void
    {
        if (trim($this->message) === '') {
            return;
        }

        $startTime = microtime(true);

        try {
            $inputGuard = app(InputGuardService::class);
            $intentAnalyzer = app(IntentAnalyzerService::class);
            $supervisor = app(SupervisorAgent::class);
            $memory = app(BifurcatedMemoryService::class);
            $cache = app(MultiLayerCacheService::class);

            $sanitizedMessage = $inputGuard->sanitize($this->message);

            $this->conversation[] = [
                'role' => 'user',
                'content' => $sanitizedMessage,
                'timestamp' => now()->toIso8601String(),
            ];

            $conversationId = 1;
            $customerId = 1;

            $intentStartTime = microtime(true);
            $intentResult = $intentAnalyzer->analyze($sanitizedMessage, [], [], [
                'trace_id' => 'testing_panel_session',
            ]);
            $intentDuration = (int) round((microtime(true) - $intentStartTime) * 1000);

            if ($this->cacheBypass) {
                $cachedResponse = null;
            } else {
                $cacheCheckStart = microtime(true);
                $cachedResponse = $cache->getCachedResponse($sanitizedMessage, $intentResult);
                $cacheDuration = (int) round((microtime(true) - $cacheCheckStart) * 1000);
            }

            if ($cachedResponse) {
                $response = $cachedResponse['response'];
                $totalDuration = (int) round((microtime(true) - $startTime) * 1000);

                $this->conversation[] = [
                    'role' => 'assistant',
                    'content' => $response,
                    'timestamp' => now()->toIso8601String(),
                    'cached' => true,
                ];

                $this->lastMetrics = [
                    'total_latency_ms' => $totalDuration,
                    'cache_hit' => true,
                    'cache_layer' => $cachedResponse['cache_layer'],
                    'cache_check_ms' => $cacheDuration ?? 0,
                ];

                $this->lastExecutionPath = [
                    ['step' => 'Input Guard', 'duration_ms' => 10, 'status' => 'success'],
                    ['step' => 'Intent Analysis', 'duration_ms' => $intentDuration, 'status' => 'success'],
                    ['step' => 'Cache Check', 'duration_ms' => $cacheDuration ?? 0, 'status' => 'hit'],
                ];

                $this->lastDebugInfo = [
                    'intent' => $intentResult->intent(),
                    'confidence' => $intentResult->confidence(),
                    'cached' => true,
                ];

                $this->message = '';
                return;
            }

            $memoryStart = microtime(true);
            $memory->appendMessage($conversationId, 'user', $sanitizedMessage);
            $preferences = $memory->getUserPreferences($customerId);
            $memoryDuration = (int) round((microtime(true) - $memoryStart) * 1000);

            $supervisorStartTime = microtime(true);
            $supervisorResult = $supervisor->orchestrate(
                $sanitizedMessage,
                $conversationId,
                $customerId,
                $intentResult,
                $preferences,
                [
                    'trace_id' => 'testing_panel_session',
                    'customer_id' => $customerId,
                ]
            );
            $supervisorDuration = (int) round((microtime(true) - $supervisorStartTime) * 1000);

            if ($supervisorResult['success'] ?? false) {
                $memory->appendMessage($conversationId, 'assistant', $supervisorResult['response']);
            }

            $totalDuration = (int) round((microtime(true) - $startTime) * 1000);

            $this->conversation[] = [
                'role' => 'assistant',
                'content' => $supervisorResult['response'] ?? 'Error occurred',
                'timestamp' => now()->toIso8601String(),
                'cached' => false,
            ];

            $this->lastMetrics = [
                'total_latency_ms' => $totalDuration,
                'intent_analysis_ms' => $intentDuration,
                'supervisor_ms' => $supervisorDuration,
                'cache_hit' => false,
                'ttft_ms' => null,
            ];

            $this->lastExecutionPath = [
                ['step' => 'Input Guard', 'duration_ms' => 10, 'status' => 'success'],
                ['step' => 'Intent Analysis', 'duration_ms' => $intentDuration, 'status' => 'success'],
                ['step' => 'Cache Check', 'duration_ms' => 20, 'status' => 'miss'],
                ['step' => 'Memory Operations', 'duration_ms' => $memoryDuration ?? 0, 'status' => 'success'],
                ['step' => 'Supervisor Orchestration', 'duration_ms' => $supervisorDuration, 'status' => 'success'],
            ];

            $this->lastDebugInfo = [
                'intent' => $intentResult->intent(),
                'confidence' => $intentResult->confidence(),
                'standalone_query' => $intentResult->standaloneQuery(),
                'validation_passed' => $supervisorResult['validation_passed'] ?? false,
                'reflection_attempts' => $supervisorResult['reflection_attempts'] ?? 0,
                'success' => $supervisorResult['success'] ?? false,
            ];

            $this->message = '';
        } catch (\Throwable $e) {
            Log::error('Testing panel error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->conversation[] = [
                'role' => 'assistant',
                'content' => 'Error: ' . $e->getMessage(),
                'timestamp' => now()->toIso8601String(),
                'error' => true,
            ];

            $totalDuration = (int) round((microtime(true) - $startTime) * 1000);

            $this->lastMetrics = [
                'total_latency_ms' => $totalDuration,
                'error' => true,
            ];

            $this->lastDebugInfo = [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ];

            $this->message = '';
        }
    }

    public function clearConversation(): void
    {
        $this->conversation = [];
        $this->lastMetrics = null;
        $this->lastExecutionPath = null;
        $this->lastDebugInfo = null;
        $this->message = '';
    }

    public function resetCircuitBreaker(): void
    {
        app(CircuitBreakerService::class)->reset();
        $this->dispatch('circuit-breaker-reset');
    }

    public function clearCache(): void
    {
        app(MultiLayerCacheService::class)->clearAll();
        $this->dispatch('cache-cleared');
    }
}
