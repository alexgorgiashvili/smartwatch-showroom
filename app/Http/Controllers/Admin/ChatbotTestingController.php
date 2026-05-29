<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Chatbot\Agents\SupervisorAgent;
use App\Services\Chatbot\BifurcatedMemoryService;
use App\Services\Chatbot\CircuitBreakerService;
use App\Services\Chatbot\InputGuardService;
use App\Services\Chatbot\IntentAnalyzerService;
use App\Services\Chatbot\MultiLayerCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ChatbotTestingController extends Controller
{
    public function __construct(
        private readonly InputGuardService $inputGuard,
        private readonly IntentAnalyzerService $intentAnalyzer,
        private readonly SupervisorAgent $supervisor,
        private readonly BifurcatedMemoryService $memory,
        private readonly MultiLayerCacheService $cache,
        private readonly CircuitBreakerService $circuitBreaker
    ) {}

    public function index(Request $request)
    {
        $view = view('admin.chatbot-testing.index', [
            'circuitBreakerStats' => $this->circuitBreaker->getStats(),
            'cacheStats' => $this->cache->getStats(),
        ]);

        return $this->renderPjaxView($request, $view);
    }

    public function sendMessage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => 'required|string|max:1000',
            'conversation_id' => 'nullable|integer',
            'cache_bypass' => 'boolean',
        ]);

        $startTime = microtime(true);

        try {
            $sanitizedMessage = $this->inputGuard->sanitize($data['message']);

            $conversationId = $data['conversation_id'] ?? session('chatbot_testing_conversation_id', 1);
            $customerId = 1;
            $cacheBypass = $data['cache_bypass'] ?? false;

            // Intent Analysis
            $intentStartTime = microtime(true);
            $intentResult = $this->intentAnalyzer->analyze($sanitizedMessage, [], [], [
                'trace_id' => 'testing_panel_session',
            ]);
            $intentDuration = (int) round((microtime(true) - $intentStartTime) * 1000);

            // Cache Check
            $cachedResponse = null;
            $cacheDuration = 0;
            if (!$cacheBypass) {
                $cacheCheckStart = microtime(true);
                $cachedResponse = $this->cache->getCachedResponse($sanitizedMessage, $intentResult);
                $cacheDuration = (int) round((microtime(true) - $cacheCheckStart) * 1000);
            }

            if ($cachedResponse) {
                $totalDuration = (int) round((microtime(true) - $startTime) * 1000);

                return response()->json([
                    'success' => true,
                    'response' => $cachedResponse['response'],
                    'cached' => true,
                    'metrics' => [
                        'total_latency_ms' => $totalDuration,
                        'cache_hit' => true,
                        'cache_layer' => $cachedResponse['cache_layer'],
                        'cache_check_ms' => $cacheDuration,
                        'intent_analysis_ms' => $intentDuration,
                    ],
                    'execution_path' => [
                        ['step' => 'Input Guard', 'duration_ms' => 10, 'status' => 'success'],
                        ['step' => 'Intent Analysis', 'duration_ms' => $intentDuration, 'status' => 'success'],
                        ['step' => 'Cache Check', 'duration_ms' => $cacheDuration, 'status' => 'hit'],
                    ],
                    'debug_info' => [
                        'intent' => $intentResult->intent(),
                        'confidence' => $intentResult->confidence(),
                        'cached' => true,
                    ],
                ]);
            }

            // Memory Management
            $memoryStart = microtime(true);
            $this->memory->appendMessage($conversationId, 'user', $sanitizedMessage);
            $preferences = $this->memory->getUserPreferences($customerId);
            $memoryDuration = (int) round((microtime(true) - $memoryStart) * 1000);

            // Supervisor Orchestration
            $supervisorStartTime = microtime(true);
            $supervisorResult = $this->supervisor->orchestrate(
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
                $this->memory->appendMessage($conversationId, 'assistant', $supervisorResult['response']);
            }

            $totalDuration = (int) round((microtime(true) - $startTime) * 1000);

            return response()->json([
                'success' => true,
                'response' => $supervisorResult['response'] ?? 'No response generated',
                'cached' => false,
                'metrics' => [
                    'total_latency_ms' => $totalDuration,
                    'cache_hit' => false,
                    'intent_analysis_ms' => $intentDuration,
                    'memory_ms' => $memoryDuration,
                    'supervisor_ms' => $supervisorDuration,
                ],
                'execution_path' => [
                    ['step' => 'Input Guard', 'duration_ms' => 10, 'status' => 'success'],
                    ['step' => 'Intent Analysis', 'duration_ms' => $intentDuration, 'status' => 'success'],
                    ['step' => 'Cache Check', 'duration_ms' => $cacheDuration, 'status' => 'miss'],
                    ['step' => 'Memory Load', 'duration_ms' => $memoryDuration, 'status' => 'success'],
                    ['step' => 'Supervisor', 'duration_ms' => $supervisorDuration, 'status' => 'success'],
                ],
                'debug_info' => [
                    'intent' => $intentResult->intent(),
                    'confidence' => $intentResult->confidence(),
                    'cached' => false,
                    'agent_used' => $supervisorResult['agent_used'] ?? 'unknown',
                    'execution_mode' => $supervisorResult['execution_mode'] ?? 'unknown',
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Chatbot testing panel error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function resetCircuitBreaker(): JsonResponse
    {
        $this->circuitBreaker->reset();

        return response()->json([
            'success' => true,
            'message' => 'Circuit breaker reset successfully',
            'stats' => $this->circuitBreaker->getStats(),
        ]);
    }

    public function flushCache(): JsonResponse
    {
        $this->cache->clearAll();

        return response()->json([
            'success' => true,
            'message' => 'Cache flushed successfully',
            'stats' => $this->cache->getStats(),
        ]);
    }

    public function getStats(): JsonResponse
    {
        return response()->json([
            'circuit_breaker' => $this->circuitBreaker->getStats(),
            'cache' => $this->cache->getStats(),
        ]);
    }
}
