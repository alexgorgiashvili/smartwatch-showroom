<?php

namespace App\Services\Chatbot;

use App\Models\Customer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class BifurcatedMemoryService
{
    private const SESSION_WINDOW = 4;
    private const SUMMARY_THRESHOLD = 10;
    private const METADATA_PREFERENCES_KEY = 'chatbot_preferences';
    private ?bool $hasPreferencesColumn = null;

    public function __construct(
        private ConversationMemoryService $conversationMemory,
        private ModelCompletionService $modelCompletion
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

        $mergedPreferences = $this->mergePreferences(
            $this->loadUserPreferences($customerId),
            $preferences
        );

        Cache::put("chatbot:user_prefs:{$customerId}", $mergedPreferences, 86400);

        $this->persistUserPreferences($customerId, $mergedPreferences);
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
        $lines = [];

        foreach ($messages as $message) {
            $role = ($message['role'] ?? '') === 'assistant' ? 'ასისტენტი' : 'მომხმარებელი';
            $content = trim((string) ($message['content'] ?? ''));

            if ($content === '') {
                continue;
            }

            $lines[] = $role . ': ' . $content;
        }

        if ($lines === []) {
            return 'მომხმარებელმა დაუსვა რამდენიმე კითხვა.';
        }

        $completion = $this->modelCompletion->complete(
            (string) config('chatbot.memory.summarization_model', 'gpt-4.1-nano'),
            [
                [
                    'role' => 'system',
                    'content' => 'შეაჯამე ეს საუბარი 2-3 წინადადებით ქართულად. გამოყავი მომხმარებლის ბიუჯეტი, სასურველი ფუნქციები, ფერი და სხვა მნიშვნელოვანი პრეფერენციები. ნუ დააბრუნებ სიას ან JSON-ს.',
                ],
                [
                    'role' => 'user',
                    'content' => implode("\n", $lines),
                ],
            ],
            [
                'temperature' => 0.2,
                'max_tokens' => 160,
                'timeout' => 15,
            ]
        );

        $reply = trim((string) ($completion['reply'] ?? ''));

        if ($reply !== '') {
            return $reply;
        }

        return $this->fallbackSummary($messages);
    }

    private function loadUserPreferences(int $customerId): array
    {
        $customer = Customer::query()->find($customerId);

        if (!$customer) {
            return [];
        }

        $preferences = $this->customerPreferencesColumnExists()
            ? $customer->preferences
            : data_get($customer->metadata, self::METADATA_PREFERENCES_KEY);

        return is_array($preferences) ? $preferences : [];
    }

    private function persistUserPreferences(int $customerId, array $preferences): void
    {
        $customer = Customer::query()->find($customerId);

        if (!$customer) {
            return;
        }

        if ($this->customerPreferencesColumnExists()) {
            $customer->update([
                'preferences' => $preferences,
            ]);

            return;
        }

        $metadata = is_array($customer->metadata) ? $customer->metadata : [];
        $metadata[self::METADATA_PREFERENCES_KEY] = $preferences;

        $customer->update([
            'metadata' => $metadata,
        ]);
    }

    private function customerPreferencesColumnExists(): bool
    {
        if ($this->hasPreferencesColumn === null) {
            $this->hasPreferencesColumn = Schema::hasColumn('customers', 'preferences');
        }

        return $this->hasPreferencesColumn;
    }

    private function mergePreferences(array $existing, array $incoming): array
    {
        if ($incoming === []) {
            return $existing;
        }

        $merged = array_merge($existing, $incoming);

        $existingFeatures = is_array($existing['features'] ?? null) ? $existing['features'] : [];
        $incomingFeatures = is_array($incoming['features'] ?? null) ? $incoming['features'] : [];
        $existingExcluded = is_array($existing['excluded_features'] ?? null) ? $existing['excluded_features'] : [];
        $incomingExcluded = is_array($incoming['excluded_features'] ?? null) ? $incoming['excluded_features'] : [];

        $features = array_values(array_unique(array_filter([...$existingFeatures, ...$incomingFeatures])));
        $excluded = array_values(array_unique(array_filter([...$existingExcluded, ...$incomingExcluded])));

        if ($excluded !== []) {
            $features = array_values(array_diff($features, $excluded));
            $merged['excluded_features'] = $excluded;
        }

        if ($features !== []) {
            $merged['features'] = $features;
        } else {
            unset($merged['features']);
        }

        if ($excluded === []) {
            unset($merged['excluded_features']);
        }

        return $merged;
    }

    private function fallbackSummary(array $messages): string
    {
        $topics = [];

        foreach ($messages as $message) {
            if (($message['role'] ?? '') !== 'user') {
                continue;
            }

            $content = trim((string) ($message['content'] ?? ''));
            if (mb_strlen($content) > 20) {
                $topics[] = mb_substr($content, 0, 50);
            }
        }

        if ($topics === []) {
            return 'მომხმარებელმა დაუსვა რამდენიმე კითხვა.';
        }

        return 'მომხმარებელთან წინა საუბარში განიხილებოდა: ' . implode('; ', array_slice($topics, 0, 3)) . '.';
    }
}
