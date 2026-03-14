<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CircuitBreakerService
{
    private const FAILURE_THRESHOLD = 5;
    private const RESET_TIMEOUT = 300; // 5 minutes
    private const HALF_OPEN_MAX_ATTEMPTS = 3;

    /**
     * Check if multi-agent pipeline should be attempted
     */
    public function shouldAttemptMultiAgent(): bool
    {
        if (!config('chatbot.circuit_breaker.enabled', true)) {
            return true;
        }

        $state = $this->getState();

        if ($state['state'] === 'open') {
            if (time() - $state['last_failure'] > $this->getResetTimeout()) {
                $this->transitionToHalfOpen();
                return true;
            }
            return false;
        }

        if ($state['state'] === 'half_open') {
            return $state['half_open_attempts'] < self::HALF_OPEN_MAX_ATTEMPTS;
        }

        return true;
    }

    /**
     * Record a successful execution
     */
    public function recordSuccess(): void
    {
        $state = $this->getState();

        if ($state['state'] === 'half_open') {
            $this->reset();
            Log::info('Circuit breaker closed after successful half-open attempt');
        } elseif ($state['state'] === 'closed' && $state['failures'] > 0) {
            $this->decrementFailures();
        }
    }

    /**
     * Record a failed execution
     */
    public function recordFailure(string $reason = ''): void
    {
        $state = $this->getState();
        $state['failures']++;
        $state['last_failure'] = time();
        $state['last_failure_reason'] = $reason;

        if ($state['state'] === 'half_open') {
            $state['half_open_attempts']++;
            
            if ($state['half_open_attempts'] >= self::HALF_OPEN_MAX_ATTEMPTS) {
                $state['state'] = 'open';
                Log::warning('Circuit breaker reopened after failed half-open attempts', [
                    'attempts' => $state['half_open_attempts'],
                    'reason' => $reason,
                ]);
            }
        } elseif ($state['failures'] >= $this->getFailureThreshold()) {
            $state['state'] = 'open';
            Log::warning('Circuit breaker opened for multi-agent pipeline', [
                'failures' => $state['failures'],
                'threshold' => $this->getFailureThreshold(),
                'reason' => $reason,
            ]);
        }

        $this->setState($state);
    }

    /**
     * Manually reset the circuit breaker
     */
    public function reset(): void
    {
        $this->setState([
            'state' => 'closed',
            'failures' => 0,
            'last_failure' => null,
            'last_failure_reason' => null,
            'half_open_attempts' => 0,
        ]);

        Log::info('Circuit breaker manually reset');
    }

    /**
     * Get current circuit breaker state
     */
    public function getState(): array
    {
        return Cache::get('chatbot:circuit_breaker:multi_agent', [
            'state' => 'closed', // closed, open, half_open
            'failures' => 0,
            'last_failure' => null,
            'last_failure_reason' => null,
            'half_open_attempts' => 0,
        ]);
    }

    /**
     * Get circuit breaker statistics
     */
    public function getStats(): array
    {
        $state = $this->getState();

        return [
            'enabled' => config('chatbot.circuit_breaker.enabled', true),
            'state' => $state['state'],
            'failures' => $state['failures'],
            'threshold' => $this->getFailureThreshold(),
            'reset_timeout' => $this->getResetTimeout(),
            'last_failure' => $state['last_failure'] ? date('Y-m-d H:i:s', $state['last_failure']) : null,
            'last_failure_reason' => $state['last_failure_reason'],
            'time_until_reset' => $state['state'] === 'open' && $state['last_failure']
                ? max(0, $this->getResetTimeout() - (time() - $state['last_failure']))
                : null,
        ];
    }

    private function transitionToHalfOpen(): void
    {
        $state = $this->getState();
        $state['state'] = 'half_open';
        $state['half_open_attempts'] = 0;
        $this->setState($state);

        Log::info('Circuit breaker transitioned to half-open state');
    }

    private function decrementFailures(): void
    {
        $state = $this->getState();
        $state['failures'] = max(0, $state['failures'] - 1);
        $this->setState($state);
    }

    private function setState(array $state): void
    {
        Cache::put('chatbot:circuit_breaker:multi_agent', $state, 3600);
    }

    private function getFailureThreshold(): int
    {
        return config('chatbot.circuit_breaker.threshold', self::FAILURE_THRESHOLD);
    }

    private function getResetTimeout(): int
    {
        return config('chatbot.circuit_breaker.reset_timeout', self::RESET_TIMEOUT);
    }
}
