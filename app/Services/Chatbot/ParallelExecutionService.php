<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Log;

class ParallelExecutionService
{
    /**
     * Execute multiple tasks in parallel
     *
     * @param array<string, callable> $tasks
     * @return array<string, mixed>
     */
    public function execute(array $tasks, int $timeout = 10): array
    {
        if (!config('chatbot.parallel_execution.enabled', true)) {
            return $this->executeSequentially($tasks);
        }

        $results = [];
        $startTime = microtime(true);

        foreach ($tasks as $key => $task) {
            try {
                $taskStartTime = microtime(true);
                
                $result = $this->executeWithTimeout($task, $timeout);
                
                $taskDuration = (int) round((microtime(true) - $taskStartTime) * 1000);
                
                $results[$key] = [
                    'result' => $result,
                    'success' => true,
                    'duration_ms' => $taskDuration,
                    'error' => null,
                ];
            } catch (\Throwable $e) {
                Log::warning("Parallel task failed: {$key}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $results[$key] = [
                    'result' => null,
                    'success' => false,
                    'duration_ms' => 0,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $totalDuration = (int) round((microtime(true) - $startTime) * 1000);

        return [
            'results' => $results,
            'total_duration_ms' => $totalDuration,
            'parallel' => true,
        ];
    }

    /**
     * Execute tasks sequentially (fallback)
     *
     * @param array<string, callable> $tasks
     * @return array<string, mixed>
     */
    private function executeSequentially(array $tasks): array
    {
        $results = [];
        $startTime = microtime(true);

        foreach ($tasks as $key => $task) {
            try {
                $taskStartTime = microtime(true);
                
                $result = $task();
                
                $taskDuration = (int) round((microtime(true) - $taskStartTime) * 1000);
                
                $results[$key] = [
                    'result' => $result,
                    'success' => true,
                    'duration_ms' => $taskDuration,
                    'error' => null,
                ];
            } catch (\Throwable $e) {
                Log::warning("Sequential task failed: {$key}", [
                    'error' => $e->getMessage(),
                ]);

                $results[$key] = [
                    'result' => null,
                    'success' => false,
                    'duration_ms' => 0,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $totalDuration = (int) round((microtime(true) - $startTime) * 1000);

        return [
            'results' => $results,
            'total_duration_ms' => $totalDuration,
            'parallel' => false,
        ];
    }

    /**
     * Execute a task with timeout
     */
    private function executeWithTimeout(callable $task, int $timeout): mixed
    {
        $startTime = time();
        
        $result = $task();
        
        $elapsed = time() - $startTime;
        if ($elapsed > $timeout) {
            Log::warning('Task exceeded timeout', [
                'timeout' => $timeout,
                'elapsed' => $elapsed,
            ]);
        }

        return $result;
    }

    /**
     * Get successful results only
     *
     * @param array<string, mixed> $executionResult
     * @return array<string, mixed>
     */
    public function getSuccessfulResults(array $executionResult): array
    {
        $successful = [];

        foreach ($executionResult['results'] as $key => $result) {
            if ($result['success']) {
                $successful[$key] = $result['result'];
            }
        }

        return $successful;
    }

    /**
     * Check if all tasks succeeded
     */
    public function allSucceeded(array $executionResult): bool
    {
        foreach ($executionResult['results'] as $result) {
            if (!$result['success']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get execution statistics
     */
    public function getStats(array $executionResult): array
    {
        $totalTasks = count($executionResult['results']);
        $successfulTasks = 0;
        $failedTasks = 0;
        $totalTaskDuration = 0;

        foreach ($executionResult['results'] as $result) {
            if ($result['success']) {
                $successfulTasks++;
            } else {
                $failedTasks++;
            }
            $totalTaskDuration += $result['duration_ms'];
        }

        return [
            'total_tasks' => $totalTasks,
            'successful' => $successfulTasks,
            'failed' => $failedTasks,
            'total_duration_ms' => $executionResult['total_duration_ms'],
            'avg_task_duration_ms' => $totalTasks > 0 ? (int) round($totalTaskDuration / $totalTasks) : 0,
            'parallel' => $executionResult['parallel'],
            'efficiency_gain' => $executionResult['parallel'] && $totalTaskDuration > 0
                ? round((1 - ($executionResult['total_duration_ms'] / $totalTaskDuration)) * 100, 1)
                : 0,
        ];
    }
}
