<?php

namespace App\Services\Chatbot;

use App\Services\Chatbot\WidgetTraceLogger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IntentAnalyzerService
{
    private const ALLOWED_INTENTS = [
        'price_query',
        'stock_query',
        'comparison',
        'recommendation',
        'features',
        'general',
        'out_of_domain',
        'clarification_needed',
    ];

    public function __construct(
        private UnifiedAiPolicyService $policy,
        private WidgetTraceLogger $widgetTrace
    ) {
    }

    public function analyze(string $message, array $history = [], array $preferences = [], array $trace = []): IntentResult
    {
        $normalizedMessage = $this->policy->normalizeIncomingMessage($message);
        $traceContext = $this->withTraceContext($trace);

        $this->traceWidget('intent.analysis_started', [
            'message' => $normalizedMessage !== '' ? $normalizedMessage : $message,
            'history_count' => count($history),
            'preferences' => $preferences,
            'next_step' => 'prepare_intent_request',
        ], $traceContext);

        $heuristicIntent = $this->applyLocalIntentHeuristics($normalizedMessage !== '' ? $normalizedMessage : $message, $preferences);
        if ($heuristicIntent instanceof IntentResult) {
            $this->traceWidget('intent.heuristic_resolved', [
                'intent' => $heuristicIntent->intent(),
                'confidence' => $heuristicIntent->confidence(),
                'standalone_query' => $heuristicIntent->standaloneQuery(),
                'search_keywords' => $heuristicIntent->searchKeywords(),
                'next_step' => 'return_intent_result_to_pipeline',
            ], $traceContext);

            return $heuristicIntent;
        }

        if (!(bool) config('services.openai.intent_enabled', true)) {
            $this->traceWidget('intent.analysis_skipped', [
                'reason' => 'intent_model_disabled',
            ], $traceContext);

            return IntentResult::fallback($normalizedMessage !== '' ? $normalizedMessage : $message);
        }

        $apiKey = (string) config('services.openai.key');
        if ($apiKey === '') {
            $this->traceWidget('intent.analysis_skipped', [
                'reason' => 'missing_openai_key',
            ], $traceContext);

            return IntentResult::fallback($normalizedMessage !== '' ? $normalizedMessage : $message);
        }

        $model = (string) config('services.openai.intent_model', 'gpt-4.1-nano');
        $baseUrl = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');

        $start = microtime(true);

        $prompt = $this->buildUserPrompt($normalizedMessage !== '' ? $normalizedMessage : $message, $history, $preferences);
        $systemPrompt = (string) config('chatbot-prompt.intent_analyzer', 'You are an intent analyzer. Return JSON only.');

        $requestContext = [
            'model' => $model,
            'base_url' => $baseUrl,
            'history_count' => count($history),
            'next_step' => 'call_openai_intent_model',
        ];

        if ($this->widgetTrace->payloadsEnabled()) {
            $requestContext['request_payload'] = [
                'system_prompt' => $systemPrompt,
                'user_prompt' => $prompt,
            ];
        }

        $this->traceWidget('intent.request_sent', $requestContext, $traceContext);

        try {
            $response = Http::withToken($apiKey)
                ->timeout(10)
                ->post($baseUrl . '/chat/completions', [
                    'model' => $model,
                    'temperature' => 0,
                    'max_tokens' => 250,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

            if (!$response->successful()) {
                $this->traceWidget('intent.request_failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ], $traceContext);

                Log::warning('Intent analyzer request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return IntentResult::fallback($normalizedMessage !== '' ? $normalizedMessage : $message);
            }

            $content = trim((string) data_get($response->json(), 'choices.0.message.content', ''));
            if ($content === '') {
                $this->traceWidget('intent.request_failed', [
                    'reason' => 'empty_intent_response',
                    'status' => $response->status(),
                ], $traceContext);

                return IntentResult::fallback($normalizedMessage !== '' ? $normalizedMessage : $message);
            }

            $parsed = $this->decodeIntentJson($content);
            if (!is_array($parsed)) {
                $this->traceWidget('intent.request_failed', [
                    'reason' => 'invalid_intent_json',
                    'raw_content' => $content,
                ], $traceContext);

                return IntentResult::fallback($normalizedMessage !== '' ? $normalizedMessage : $message);
            }

            $latencyMs = (int) round((microtime(true) - $start) * 1000);
            $sanitized = $this->sanitizePayload($parsed);

            $responseContext = [
                'latency_ms' => $latencyMs,
                'parsed_intent' => $sanitized,
                'next_step' => 'return_intent_result_to_pipeline',
            ];

            if ($this->widgetTrace->payloadsEnabled()) {
                $responseContext['raw_response'] = $content;
            }

            $this->traceWidget('intent.response_received', $responseContext, $traceContext);

            return IntentResult::fromArray($sanitized, $latencyMs);
        } catch (\Throwable $exception) {
            $this->traceWidget('intent.request_failed', [
                'reason' => 'intent_exception',
                'error' => $exception->getMessage(),
            ], $traceContext);

            Log::warning('Intent analyzer exception', [
                'error' => $exception->getMessage(),
            ]);

            return IntentResult::fallback($normalizedMessage !== '' ? $normalizedMessage : $message);
        }
    }

    private function buildUserPrompt(string $message, array $history, array $preferences): string
    {
        $historyLines = $this->condenseHistory($history);

        $parts = [];

        $parts[] = 'Current user message: ' . $message;

        if ($historyLines !== '') {
            $parts[] = "Recent chat history:\n" . $historyLines;
        }

        if ($preferences !== []) {
            $parts[] = 'User preferences JSON: ' . json_encode($preferences, JSON_UNESCAPED_UNICODE);
        }

        $parts[] = 'Return JSON only.';

        return implode("\n\n", $parts);
    }

    private function condenseHistory(array $history): string
    {
        $recent = collect($history)
            ->filter(fn ($item) => is_array($item))
            ->take(-6)
            ->values();

        if ($recent->isEmpty()) {
            return '';
        }

        return $recent
            ->map(function (array $entry): string {
                $role = (string) ($entry['role'] ?? 'user');
                $content = trim((string) ($entry['content'] ?? ''));

                if ($content === '') {
                    return '';
                }

                $prefix = $role === 'assistant' ? 'A' : 'U';
                $trimmed = mb_substr($content, 0, 150);

                return $prefix . ': ' . $trimmed;
            })
            ->filter(fn (string $line): bool => $line !== '')
            ->implode("\n");
    }

    private function decodeIntentJson(string $content): ?array
    {
        $decoded = json_decode($content, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        $start = mb_strpos($content, '{');
        $end = mb_strrpos($content, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $candidate = mb_substr($content, $start, $end - $start + 1);
        $decoded = json_decode($candidate, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function sanitizePayload(array $payload): array
    {
        $intent = mb_strtolower(trim((string) ($payload['intent'] ?? 'general')));

        if (!in_array($intent, self::ALLOWED_INTENTS, true)) {
            $intent = 'general';
        }

        $standaloneQuery = trim((string) ($payload['standalone_query'] ?? ''));
        if ($standaloneQuery === '') {
            $standaloneQuery = trim((string) ($payload['refined_query'] ?? ''));
        }

        $entities = is_array($payload['entities'] ?? null) ? $payload['entities'] : [];

        $searchKeywords = collect($payload['search_keywords'] ?? [])
            ->filter(fn ($keyword) => is_string($keyword) && trim($keyword) !== '')
            ->map(fn (string $keyword) => trim($keyword))
            ->values()
            ->all();

        $standaloneQuery = $this->stripNegatedFeaturePhrases($standaloneQuery);
        $searchKeywords = $this->sanitizeSearchKeywords($searchKeywords, $standaloneQuery);

        if ($this->looksLikeBudgetRecommendation($standaloneQuery, $searchKeywords, $entities)) {
            $intent = 'recommendation';
        }

        return [
            'standalone_query' => $standaloneQuery,
            'intent' => $intent,
            'entities' => [
                'brand' => $this->nullableString($entities['brand'] ?? null),
                'model' => $this->nullableString($entities['model'] ?? null),
                'product_slug_hint' => $this->nullableString($entities['product_slug_hint'] ?? null),
                'color' => $this->nullableString($entities['color'] ?? null),
                'category' => $this->nullableString($entities['category'] ?? null),
            ],
            'needs_product_data' => (bool) ($payload['needs_product_data'] ?? true),
            'search_keywords' => $searchKeywords,
            'is_out_of_domain' => (bool) ($payload['is_out_of_domain'] ?? false),
            'confidence' => max(0.0, min(1.0, (float) ($payload['confidence'] ?? 0.0))),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    private function applyLocalIntentHeuristics(string $message, array $preferences): ?IntentResult
    {
        if ($this->looksLikeAdultCatalogRequest($message)) {
            return IntentResult::fromArray([
                'standalone_query' => trim($message),
                'intent' => 'out_of_domain',
                'entities' => [
                    'brand' => null,
                    'model' => null,
                    'product_slug_hint' => null,
                    'color' => null,
                    'category' => 'adult_smartwatch',
                ],
                'needs_product_data' => false,
                'search_keywords' => [],
                'is_out_of_domain' => true,
                'confidence' => 0.94,
            ], 0);
        }

        if ($this->looksLikeTrackingRecommendation($message)) {
            return IntentResult::fromArray([
                'standalone_query' => trim($message),
                'intent' => 'recommendation',
                'entities' => [
                    'brand' => null,
                    'model' => null,
                    'product_slug_hint' => null,
                    'color' => null,
                    'category' => 'kids_smart_watch',
                ],
                'needs_product_data' => true,
                'search_keywords' => ['GPS', 'ლოკაცია', 'გადაადგილების ისტორია', 'ტრეკინგი'],
                'is_out_of_domain' => false,
                'confidence' => 0.9,
            ], 0);
        }

        if (!$this->looksLikeBudgetRecommendation($message, [], [])) {
            return null;
        }

        $searchKeywords = [];
        $budget = $this->extractBudgetAmount($message);

        if ($budget === null && isset($preferences['budget_max_gel']) && is_numeric($preferences['budget_max_gel'])) {
            $budget = (float) $preferences['budget_max_gel'];
        }

        if ($budget !== null) {
            $searchKeywords[] = rtrim(rtrim(number_format($budget, 2, '.', ''), '0'), '.') . ' ლარის ფარგლებში';
        }

        foreach (['იაფი', 'ბიუჯეტი', 'დაბალ ფასიანი', 'GPS', 'SOS'] as $keyword) {
            if (mb_stripos($message, $keyword) !== false) {
                $searchKeywords[] = $keyword;
            }
        }

        $payload = [
            'standalone_query' => trim($message),
            'intent' => 'recommendation',
            'entities' => [
                'brand' => null,
                'model' => null,
                'product_slug_hint' => null,
                'color' => null,
                'category' => null,
            ],
            'needs_product_data' => true,
            'search_keywords' => array_values(array_unique(array_filter($searchKeywords))),
            'is_out_of_domain' => false,
            'confidence' => 0.92,
        ];

        return IntentResult::fromArray($payload, 0);
    }

    private function looksLikeBudgetRecommendation(string $message, array $searchKeywords = [], array $entities = []): bool
    {
        $normalized = mb_strtolower(trim($message));

        if ($normalized === '') {
            return false;
        }

        $hasSpecificEntity = collect([
            $entities['brand'] ?? null,
            $entities['model'] ?? null,
            $entities['product_slug_hint'] ?? null,
        ])->contains(fn ($value): bool => is_string($value) && trim($value) !== '');

        if ($hasSpecificEntity) {
            return false;
        }

        $hasBudgetSignal = preg_match('/\d+(?:[\.,]\d+)?\s*(?:₾|ლარ(?:ი|ამდე)?|gel|lari)/iu', $normalized) === 1
            || preg_match('/\b(?:ბიუჯეტ|ფარგლებ|მდე|იაფ|დაბალ ფას)\b/iu', $normalized) === 1;

        if (!$hasBudgetSignal) {
            return false;
        }

        $genericRecommendationSignals = [
            'რამე',
            'რაიმე',
            'ვარიანტი',
            'მირჩი',
            'შემომთავაზ',
            'გაქვთ',
            'მინდა',
        ];

        if (collect($searchKeywords)->contains(fn ($keyword): bool => is_string($keyword) && mb_stripos($keyword, 'ფარგლებში') !== false)) {
            return true;
        }

        return collect($genericRecommendationSignals)
            ->contains(fn (string $signal): bool => mb_stripos($normalized, $signal) !== false);
    }

    private function looksLikeAdultCatalogRequest(string $message): bool
    {
        $normalized = mb_strtolower(trim($message));

        if ($normalized === '') {
            return false;
        }

        if (preg_match('/\b(adult|grown|men|women)\b/u', $normalized) === 1 || str_contains($normalized, 'ზრდასრულ')) {
            return true;
        }

        if (preg_match('/\b(1[89]|[2-9][0-9])\s*წლის\b/u', $normalized) === 1 && !str_contains($normalized, 'ბავშვ')) {
            return true;
        }

        return false;
    }

    private function looksLikeTrackingRecommendation(string $message): bool
    {
        $normalized = mb_strtolower(trim($message));

        if ($normalized === '') {
            return false;
        }

        $hasTrackingSignal = str_contains($normalized, 'ლოკაცია')
            || str_contains($normalized, 'გადაადგილების ისტორია')
            || str_contains($normalized, 'ტრეკინგ')
            || str_contains($normalized, 'ადგილმდებარეობ');

        if (!$hasTrackingSignal) {
            return false;
        }

        return !str_contains($normalized, 'ზრდასრულ');
    }

    /**
     * @param array<int, string> $searchKeywords
     * @return array<int, string>
     */
    private function sanitizeSearchKeywords(array $searchKeywords, string $message): array
    {
        $negated = $this->negatedFeaturesInMessage($message);

        if ($negated === []) {
            return $searchKeywords;
        }

        return array_values(array_filter($searchKeywords, function (string $keyword) use ($negated): bool {
            $normalizedKeyword = mb_strtolower(trim($keyword));

            foreach ($negated as $feature) {
                if (str_contains($normalizedKeyword, $feature)) {
                    return false;
                }
            }

            return true;
        }));
    }

    private function stripNegatedFeaturePhrases(string $message): string
    {
        $normalized = trim($message);

        foreach (['camera', 'კამერა', 'call', 'calls', 'ზარი', 'ზარები'] as $feature) {
            $normalized = preg_replace('/(?:მაგრამ\s+)?(?:' . preg_quote($feature, '/') . '|(?:არ\s+(?:მინდა|მაინტერესებს|მჭირდება)[^.!?\n]{0,24}' . preg_quote($feature, '/') . '))+/iu', ' ', $normalized) ?? $normalized;
        }

        return trim(preg_replace('/\s{2,}/u', ' ', $normalized) ?? $normalized);
    }

    /**
     * @return array<int, string>
     */
    private function negatedFeaturesInMessage(string $message): array
    {
        $normalized = mb_strtolower($message);
        $negated = [];

        foreach ([
            'camera' => ['camera', 'კამერა'],
            'calls' => ['call', 'calls', 'ზარი', 'ზარები'],
        ] as $feature => $needles) {
            foreach ($needles as $needle) {
                $quoted = preg_quote(mb_strtolower($needle), '/');
                if (preg_match('/(?:არ\s+(?:მინდა|მაინტერესებს|მჭირდება)|გარეშე)[^.!?\n]{0,24}' . $quoted . '/u', $normalized) === 1 || preg_match('/' . $quoted . '[^.!?\n]{0,24}(?:არ\s+(?:მინდა|მაინტერესებს|მჭირდება)|გარეშე)/u', $normalized) === 1) {
                    $negated[] = $feature;
                    break;
                }
            }
        }

        return array_values(array_unique($negated));
    }

    private function extractBudgetAmount(string $message): ?float
    {
        if (preg_match('/(\d+(?:[\.,]\d+)?)\s*(?:₾|ლარ(?:ი|ამდე)?|gel|lari)/iu', $message, $matches) !== 1) {
            return null;
        }

        return (float) str_replace(',', '.', (string) ($matches[1] ?? ''));
    }

    private function withTraceContext(array $trace): array
    {
        return array_filter([
            'trace_id' => ($trace['trace_id'] ?? null) ?: null,
            'conversation_id' => $trace['conversation_id'] ?? null,
            'customer_id' => $trace['customer_id'] ?? null,
        ], fn ($value) => $value !== null);
    }

    private function traceWidget(string $step, array $context, array $trace): void
    {
        if (!$this->widgetTrace->enabled()) {
            return;
        }

        $this->widgetTrace->logStep($step, array_merge($trace, $context));
    }
}
