<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Cache;

class BifurcatedMemoryService
{
    private const SESSION_WINDOW = 4;
    private const SUMMARY_THRESHOLD = 10;

    public function __construct(
        private ConversationMemoryService $conversationMemory
    ) {
    }

    /**
     * Get session context (short-term memory)
     */
    public function getSessionContext(int $conversationId): array
    {
        $history = $this->conversationMemory->getContext($conversationId)['history'] ?? [];

        $sessionWindow = config('chatbot.memory.session_window', self::SESSION_WINDOW);

        if (count($history) <= $sessionWindow) {
            return [
                'recent' => $history,
                'summary' => null,
            ];
        }

        if (config('chatbot.memory.summarization_enabled', true) && count($history) > self::SUMMARY_THRESHOLD) {
            $olderMessages = array_slice($history, 0, -$sessionWindow);
            $recentMessages = array_slice($history, -$sessionWindow);

            $summary = $this->summarizeContext($olderMessages, $conversationId);

            return [
                'recent' => $recentMessages,
                'summary' => $summary,
            ];
        }

        return [
            'recent' => array_slice($history, -$sessionWindow),
            'summary' => null,
        ];
    }

    /**
     * Get user preferences (long-term memory)
     */
    public function getUserPreferences(int $customerId): array
    {
        if ($customerId <= 0) {
            return [];
        }

        return Cache::remember(
            "chatbot:user_prefs:{$customerId}",
            3600,
            fn() => $this->loadUserPreferences($customerId)
        );
    }

    /**
     * Update user preferences
     */
    public function updateUserPreferences(int $customerId, array $preferences): void
    {
        if ($customerId <= 0) {
            return;
        }

        Cache::put("chatbot:user_prefs:{$customerId}", $preferences, 86400);

        $this->persistUserPreferences($customerId, $preferences);
    }

    /**
     * Should use conversation context for this message
     */
    public function shouldUseConversationContext(string $message): bool
    {
        return $this->conversationMemory->shouldUseConversationContext($message);
    }

    /**
     * Append message to conversation
     */
    public function appendMessage(int $conversationId, string $role, string $content): void
    {
        $this->conversationMemory->appendMessage($conversationId, $role, $content);
    }

    /**
     * Get scoped preferences for current message
     */
    public function scopePreferencesForMessage(array $storedPreferences, string $message): array
    {
        return $this->conversationMemory->scopePreferencesForMessage($storedPreferences, $message);
    }

    /**
     * Clear session context
     */
    public function clearSessionContext(int $conversationId): void
    {
        Cache::forget("chatbot:session_summary:{$conversationId}");
    }

    /**
     * Clear user preferences cache
     */
    public function clearUserPreferences(int $customerId): void
    {
        Cache::forget("chatbot:user_prefs:{$customerId}");
    }

    /**
     * Get memory statistics
     */
    public function getStats(int $conversationId, int $customerId): array
    {
        $sessionContext = $this->getSessionContext($conversationId);
        $userPreferences = $this->getUserPreferences($customerId);

        return [
            'session' => [
                'recent_messages' => count($sessionContext['recent']),
                'has_summary' => $sessionContext['summary'] !== null,
                'window_size' => config('chatbot.memory.session_window', self::SESSION_WINDOW),
            ],
            'user' => [
                'has_preferences' => !empty($userPreferences),
                'preference_count' => count($userPreferences),
            ],
            'config' => [
                'session_window' => config('chatbot.memory.session_window', self::SESSION_WINDOW),
                'summarization_enabled' => config('chatbot.memory.summarization_enabled', true),
                'summary_threshold' => self::SUMMARY_THRESHOLD,
            ],
        ];
    }

    private function summarizeContext(array $messages, int $conversationId): ?string
    {
        $cacheKey = "chatbot:session_summary:{$conversationId}";
        
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        if (count($messages) < 2) {
            return null;
        }

        $summary = $this->generateSummary($messages);

        Cache::put($cacheKey, $summary, 1800);

        return $summary;
    }

    private function generateSummary(array $messages): string
    {
        $userMessages = array_filter($messages, fn($msg) => ($msg['role'] ?? '') === 'user');
        $assistantMessages = array_filter($messages, fn($msg) => ($msg['role'] ?? '') === 'assistant');

        $topics = [];
        foreach ($userMessages as $msg) {
            $content = $msg['content'] ?? '';
            if (mb_strlen($content) > 20) {
                $topics[] = mb_substr($content, 0, 50);
            }
        }

        if (empty($topics)) {
            return 'მომხმარებელმა დაუსვა რამდენიმე კითხვა.';
        }

        return 'წინა საუბარში განხილული თემები: ' . implode('; ', array_slice($topics, 0, 3)) . '.';
    }

    private function loadUserPreferences(int $customerId): array
    {
        return $this->conversationMemory->getContext($customerId)['preferences'] ?? [];
    }

    private function persistUserPreferences(int $customerId, array $preferences): void
    {
        // This would typically save to database
        // For now, we rely on cache
    }
}
