<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Redis;

class ConversationMemoryService
{
    private const HISTORY_LIMIT = 10;
    private const SESSION_TTL_SECONDS = 86400;

    public function getContext(int $conversationId): array
    {
        $key = $this->sessionKey($conversationId);
        $raw = Redis::hgetall($key);

        if (!is_array($raw) || $raw === []) {
            return [
                'history' => [],
                'preferences' => [],
                'intent_state' => null,
                'last_active' => null,
            ];
        }

        return [
            'history' => $this->decodeArray($raw['history'] ?? '[]'),
            'preferences' => $this->decodeArray($raw['preferences'] ?? '[]'),
            'intent_state' => $raw['intent_state'] ?? null,
            'last_active' => $raw['last_active'] ?? null,
        ];
    }

    public function appendMessage(int $conversationId, string $role, string $content): void
    {
        $context = $this->getContext($conversationId);
        $history = is_array($context['history']) ? $context['history'] : [];
        $preferences = is_array($context['preferences']) ? $context['preferences'] : [];

        $history[] = [
            'role' => $role,
            'content' => $content,
            'timestamp' => now()->toIso8601String(),
        ];

        if (count($history) > self::HISTORY_LIMIT) {
            $history = array_slice($history, -self::HISTORY_LIMIT);
        }

        if ($role === 'user') {
            $preferences = $this->mergePreferences($preferences, $this->extractPreferencesFromMessage($content));
        }

        $key = $this->sessionKey($conversationId);

        Redis::hmset($key, [
            'history' => json_encode($history, JSON_UNESCAPED_UNICODE),
            'preferences' => json_encode($preferences, JSON_UNESCAPED_UNICODE),
            'intent_state' => $context['intent_state'] ?? '',
            'last_active' => now()->toIso8601String(),
        ]);

        Redis::expire($key, self::SESSION_TTL_SECONDS);
    }

    public function updatePreferences(int $conversationId, array $preferences): void
    {
        $context = $this->getContext($conversationId);

        $existing = is_array($context['preferences']) ? $context['preferences'] : [];
        $merged = $this->mergePreferences($existing, $preferences);

        $key = $this->sessionKey($conversationId);

        Redis::hmset($key, [
            'history' => json_encode($context['history'] ?? [], JSON_UNESCAPED_UNICODE),
            'preferences' => json_encode($merged, JSON_UNESCAPED_UNICODE),
            'intent_state' => $context['intent_state'] ?? '',
            'last_active' => now()->toIso8601String(),
        ]);

        Redis::expire($key, self::SESSION_TTL_SECONDS);
    }

    public function shouldUseConversationContext(string $message): bool
    {
        return $this->messageLooksContextualFollowUp($message);
    }

    public function scopePreferencesForMessage(array $storedPreferences, string $message): array
    {
        $storedPreferences = is_array($storedPreferences) ? $storedPreferences : [];
        $currentPreferences = $this->extractPreferencesFromMessage($message);

        if (!$this->shouldUseConversationContext($message)) {
            return $currentPreferences;
        }

        if ($storedPreferences === []) {
            return $currentPreferences;
        }

        if ($currentPreferences === []) {
            return $storedPreferences;
        }

        return $this->mergePreferences($storedPreferences, $currentPreferences);
    }

    public function clearContext(int $conversationId): void
    {
        Redis::del($this->sessionKey($conversationId));
    }

    private function sessionKey(int $conversationId): string
    {
        return 'chat_session:' . $conversationId;
    }

    private function decodeArray(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function extractPreferencesFromMessage(string $content): array
    {
        $preferences = [];
        $normalized = mb_strtolower($content);

        if (preg_match('/(\d+)\s*(lari|ლარ|gel|₾)/iu', $content, $matches) === 1) {
            $preferences['budget_max_gel'] = (int) $matches[1];
        }

        $colorMap = [
            'black' => 'black',
            'white' => 'white',
            'blue' => 'blue',
            'red' => 'red',
            'pink' => 'pink',
            'green' => 'green',
            'ყვითელი' => 'yellow',
            'შავი' => 'black',
            'შავში' => 'black',
            'თეთრი' => 'white',
            'ლურჯი' => 'blue',
            'წითელი' => 'red',
            'ვარდისფერი' => 'pink',
            'მწვანე' => 'green',
        ];

        foreach ($colorMap as $needle => $value) {
            if (str_contains($normalized, $needle)) {
                $preferences['color'] = $value;
                break;
            }
        }

        if (preg_match('/\b(size|small|medium|large|xl|xxl)\b/i', $content, $matches) === 1) {
            $preferences['size'] = mb_strtolower($matches[1]);
        } elseif (preg_match('/(ზომა|პატარა|საშუალო|დიდი)/u', $content, $matches) === 1) {
            $preferences['size'] = $matches[1];
        }

        $features = [];
        $excludedFeatures = [];

        foreach ([
            'gps' => ['gps'],
            'sos' => ['sos'],
            'camera' => ['camera', 'კამერა'],
            'calls' => ['call', 'calls', 'ზარი', 'ზარები'],
        ] as $feature => $needles) {
            if (!$this->messageReferencesAnyNeedle($normalized, $content, $needles)) {
                continue;
            }

            if ($this->messageNegatesFeature($normalized, $needles)) {
                $excludedFeatures[] = $feature;
                continue;
            }

            $features[] = $feature;
        }

        if ($features !== []) {
            $preferences['features'] = array_values(array_unique($features));
        }

        if ($excludedFeatures !== []) {
            $preferences['excluded_features'] = array_values(array_unique($excludedFeatures));
        }

        return $preferences;
    }

    private function mergePreferences(array $existing, array $incoming): array
    {
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

    /**
     * @param array<int, string> $needles
     */
    private function messageReferencesAnyNeedle(string $normalized, string $rawContent, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (preg_match('/\b' . preg_quote($needle, '/') . '\b/iu', $rawContent) === 1 || str_contains($normalized, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $needles
     */
    private function messageNegatesFeature(string $normalized, array $needles): bool
    {
        foreach ($needles as $needle) {
            $quoted = preg_quote(mb_strtolower($needle), '/');

            if (preg_match('/(?:არ\s+(?:მინდა|მაინტერესებს|მჭირდება|მქონდეს)|გარეშე|უშუალოდ\s+არ\s+მინდა)[^.!?\n]{0,24}' . $quoted . '/u', $normalized) === 1) {
                return true;
            }

            if (preg_match('/' . $quoted . '[^.!?\n]{0,24}(?:არ\s+(?:მინდა|მაინტერესებს|მჭირდება)|გარეშე)/u', $normalized) === 1) {
                return true;
            }

            if (preg_match('/' . $quoted . '[^.!?\n]{0,24}(?:არ\s+არის\s+(?:მნიშვნელოვანი|პრიორიტეტული)|საერთოდ\s+არ\s+არის\s+(?:მნიშვნელოვანი|პრიორიტეტული))/u', $normalized) === 1) {
                return true;
            }

            if (preg_match('/(?:არ\s+არის\s+(?:მნიშვნელოვანი|პრიორიტეტული)|საერთოდ\s+არ\s+არის\s+(?:მნიშვნელოვანი|პრიორიტეტული))[^.!?\n]{0,24}' . $quoted . '/u', $normalized) === 1) {
                return true;
            }
        }

        return false;
    }

    private function messageLooksContextualFollowUp(string $message): bool
    {
        $normalized = mb_strtolower(trim($message));

        if ($normalized === '') {
            return false;
        }

        foreach ([
            '/\b(იგივე|იმავე|იმავეს|ამავე)\b/u',
            '/\b(შემდეგი|ალტერნატივ)\b/u',
            '/თუ\s+არ\s+(?:აქვს|არის|გაქვთ)/u',
            '/იგივე\s+კლასის/u',
            '/\b(ამისი|მისი|ამის|იმისი)\b/u',
        ] as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return true;
            }
        }

        return false;
    }
}
