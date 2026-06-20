<?php

namespace App\Services\Chatbot;

use App\Models\Product;
use Illuminate\Contracts\Container\BindingResolutionException;
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
        private WidgetTraceLogger $widgetTrace,
        private ModelCompletionService $modelCompletion
    ) {
    }

    public function analyze(string $message, array $history = [], array $preferences = [], array $trace = []): IntentResult
    {
        $normalizedMessage = $this->policy->normalizeIncomingMessage($message);
        $traceContext = $this->withTraceContext($trace);
        $spanId = $this->langfuse()->startSpan('intent.analyze', [
            'message' => $normalizedMessage !== '' ? $normalizedMessage : $message,
        ], [
            'history_count' => count($history),
            'has_preferences' => $preferences !== [],
        ]);

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

            $this->langfuse()->endSpan($spanId, [
                'intent' => $heuristicIntent->intent(),
                'confidence' => $heuristicIntent->confidence(),
                'fallback' => $heuristicIntent->isFallback(),
                'resolution' => 'heuristic',
            ], $heuristicIntent->standaloneQuery());

            return $heuristicIntent;
        }

        if (!(bool) config('services.openai.intent_enabled', true)) {
            $this->traceWidget('intent.analysis_skipped', [
                'reason' => 'intent_model_disabled',
            ], $traceContext);

            $fallbackIntent = IntentResult::fallback($normalizedMessage !== '' ? $normalizedMessage : $message);
            $this->langfuse()->endSpan($spanId, [
                'intent' => $fallbackIntent->intent(),
                'confidence' => $fallbackIntent->confidence(),
                'fallback' => true,
                'reason' => 'intent_model_disabled',
            ], $fallbackIntent->standaloneQuery());

            return $fallbackIntent;
        }

        $model = (string) config('services.openai.intent_model', 'gpt-4.1-nano');

        $start = microtime(true);

        $prompt = $this->buildUserPrompt($normalizedMessage !== '' ? $normalizedMessage : $message, $history, $preferences);
        $systemPrompt = (string) config('chatbot-prompt.intent_analyzer', 'You are an intent analyzer. Return JSON only.');

        $requestContext = [
            'model' => $model,
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
            $completion = $this->modelCompletion->complete(
                $model,
                [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $prompt],
                ],
                [
                    'temperature' => 0.0,
                    'max_tokens' => 250,
                    'timeout' => 10,
                    'response_format' => ['type' => 'json_object'],
                    'langfuse_name' => 'chatbot.intent_analyzer',
                    'langfuse_metadata' => [
                        'component' => 'intent_analyzer',
                    ],
                ]
            );

            if (($completion['reason'] ?? null) !== null) {
                $this->traceWidget('intent.request_failed', [
                    'reason' => $completion['reason'],
                ], $traceContext);

                Log::warning('Intent analyzer request failed', [
                    'reason' => $completion['reason'],
                    'model' => $model,
                ]);

                $fallbackIntent = IntentResult::fallback($normalizedMessage !== '' ? $normalizedMessage : $message);
                $this->langfuse()->endSpan($spanId, [
                    'intent' => $fallbackIntent->intent(),
                    'confidence' => $fallbackIntent->confidence(),
                    'fallback' => true,
                    'reason' => $completion['reason'],
                ], $fallbackIntent->standaloneQuery());

                return $fallbackIntent;
            }

            $content = trim((string) ($completion['reply'] ?? ''));
            if ($content === '') {
                $this->traceWidget('intent.request_failed', [
                    'reason' => 'empty_intent_response',
                ], $traceContext);

                $fallbackIntent = IntentResult::fallback($normalizedMessage !== '' ? $normalizedMessage : $message);
                $this->langfuse()->endSpan($spanId, [
                    'intent' => $fallbackIntent->intent(),
                    'confidence' => $fallbackIntent->confidence(),
                    'fallback' => true,
                    'reason' => 'empty_intent_response',
                ], $fallbackIntent->standaloneQuery());

                return $fallbackIntent;
            }

            $parsed = $this->decodeIntentJson($content);
            if (!is_array($parsed)) {
                $this->traceWidget('intent.request_failed', [
                    'reason' => 'invalid_intent_json',
                    'raw_content' => $content,
                ], $traceContext);

                $fallbackIntent = IntentResult::fallback($normalizedMessage !== '' ? $normalizedMessage : $message);
                $this->langfuse()->endSpan($spanId, [
                    'intent' => $fallbackIntent->intent(),
                    'confidence' => $fallbackIntent->confidence(),
                    'fallback' => true,
                    'reason' => 'invalid_intent_json',
                ], $fallbackIntent->standaloneQuery());

                return $fallbackIntent;
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

            $intentResult = IntentResult::fromArray($sanitized, $latencyMs);
            $this->langfuse()->endSpan($spanId, [
                'intent' => $intentResult->intent(),
                'confidence' => $intentResult->confidence(),
                'fallback' => $intentResult->isFallback(),
                'latency_ms' => $latencyMs,
            ], $intentResult->standaloneQuery());

            return $intentResult;
        } catch (\Throwable $exception) {
            $this->traceWidget('intent.request_failed', [
                'reason' => 'intent_exception',
                'error' => $exception->getMessage(),
            ], $traceContext);

            Log::warning('Intent analyzer exception', [
                'error' => $exception->getMessage(),
            ]);

            $fallbackIntent = IntentResult::fallback($normalizedMessage !== '' ? $normalizedMessage : $message);
            $this->langfuse()->endSpan($spanId, [
                'intent' => $fallbackIntent->intent(),
                'confidence' => $fallbackIntent->confidence(),
                'fallback' => true,
                'reason' => 'intent_exception',
                'error' => $exception->getMessage(),
            ], $fallbackIntent->standaloneQuery());

            return $fallbackIntent;
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
        $normalizedMessage = trim($message);

        if ($this->looksLikeAdultCatalogRequest($normalizedMessage)) {
            return IntentResult::fromArray([
                'standalone_query' => $normalizedMessage,
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

        if ($this->looksLikeTrackingRecommendation($normalizedMessage)) {
            return IntentResult::fromArray([
                'standalone_query' => $normalizedMessage,
                'intent' => 'recommendation',
                'entities' => [
                    'brand' => null,
                    'model' => null,
                    'product_slug_hint' => null,
                    'color' => null,
                    'category' => 'kids_smart_watch',
                ],
                'needs_product_data' => true,
                'search_keywords' => [
                    'GPS',
                    'ლოკაცია',
                    'ადგილმდებარეობა',
                    'გადაადგილების ისტორია',
                ],
                'is_out_of_domain' => false,
                'confidence' => 0.90,
            ], 0);
        }

        $specificProductIntent = $this->applySpecificProductHeuristics($normalizedMessage);
        if ($specificProductIntent instanceof IntentResult) {
            return $specificProductIntent;
        }

        $catalogFacetIntent = $this->applyCatalogFacetHeuristics($normalizedMessage);
        if ($catalogFacetIntent instanceof IntentResult) {
            return $catalogFacetIntent;
        }

        if (!$this->looksLikeBudgetRecommendation($normalizedMessage, [], [], $preferences)) {
            return null;
        }

        $budget = $this->extractBudgetAmount($normalizedMessage);
        if ($budget === null && isset($preferences['budget_max_gel']) && is_numeric($preferences['budget_max_gel'])) {
            $budget = (float) $preferences['budget_max_gel'];
        }

        $searchKeywords = [];
        if ($budget !== null) {
            $budgetLabel = rtrim(rtrim(number_format($budget, 2, '.', ''), '0'), '.');
            $searchKeywords[] = $budgetLabel . ' ლარის ფარგლებში';
            $searchKeywords[] = $budgetLabel . ' ლარამდე';
            $searchKeywords[] = $budgetLabel . ' ლარი';
        }

        return IntentResult::fromArray([
            'standalone_query' => $normalizedMessage,
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
        ], 0);
    }

    private function applyCatalogFacetHeuristics(string $message): ?IntentResult
    {
        $normalized = trim($message);
        $searchable = mb_strtolower($normalized);

        if ($normalized === '') {
            return null;
        }

        $mentionsTwoG = $this->mentionsGenerationFacet($searchable, '2g');
        $mentionsFourG = $this->mentionsGenerationFacet($searchable, '4g');
        $mentionsDiscount = $this->mentionsDiscountFacet($searchable);

        if (!$mentionsTwoG && !$mentionsFourG && !$mentionsDiscount) {
            return null;
        }

        $searchKeywords = [];

        if ($mentionsTwoG) {
            $searchKeywords = array_merge($searchKeywords, ['2G', '2 გ', '2გ']);
        }

        if ($mentionsFourG) {
            $searchKeywords = array_merge($searchKeywords, ['4G', '4 გ', '4გ']);
        }

        if ($mentionsDiscount) {
            $searchKeywords = array_merge($searchKeywords, ['ფასდაკლება', 'discount', 'sale']);
        }

        $intent = $this->inferCatalogFacetIntent($searchable);

        return IntentResult::fromArray([
            'standalone_query' => $normalized,
            'intent' => $intent,
            'entities' => [
                'brand' => null,
                'model' => null,
                'product_slug_hint' => null,
                'color' => null,
                'category' => $mentionsDiscount
                    ? 'discounted_catalog'
                    : ($mentionsFourG
                        ? '4g_catalog'
                        : '2g_catalog'),
            ],
            'needs_product_data' => true,
            'search_keywords' => array_values(array_unique(array_filter($searchKeywords))),
            'is_out_of_domain' => false,
            'confidence' => 0.96,
        ], 0);
    }

    private function inferCatalogFacetIntent(string $message): string
    {
        if ($this->containsAnyNeedle($message, [
            'ფასი',
            'price',
            'cost',
            'how much',
            'ღირს',
            'ღირდა',
            'ზუსტი',
            'discount',
            'sale',
            'ფასდაკლ',
        ])) {
            return 'price_query';
        }

        if ($this->containsAnyNeedle($message, [
            'მარაგ',
            'stock',
            'available',
            'availability',
            'მარაგში',
        ])) {
            return 'stock_query';
        }

        return 'recommendation';
    }

    private function mentionsGenerationFacet(string $message, string $generation): bool
    {
        $patterns = $generation === '2g'
            ? [
                '/(?:^|\s)2\s*g(?:\s|$)/u',
                '/(?:^|\s)2\s*გ(?:\s|$)/u',
                '/(?:^|\s)2გ(?:\s|$)/u',
            ]
            : [
                '/(?:^|\s)4\s*g(?:\s|$)/u',
                '/(?:^|\s)4\s*გ(?:\s|$)/u',
                '/(?:^|\s)4გ(?:\s|$)/u',
            ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message) === 1) {
                return true;
            }
        }

        return false;
    }

    private function mentionsDiscountFacet(string $message): bool
    {
        return $this->containsAnyNeedle($message, [
            'ფასდაკლ',
            'discount',
            'sale',
            'offer',
            'reduc',
        ]);
    }

    private function applySpecificProductHeuristics(string $message): ?IntentResult
    {
        $normalized = trim($message);
        $searchable = mb_strtolower($normalized);

        if ($normalized === '' || !$this->looksLikeSpecificProductRequest($normalized)) {
            return null;
        }

        $product = $this->findBestMatchingProduct($searchable);
        if (!$product instanceof Product) {
            return null;
        }

        [$brand, $model] = $this->deriveProductIdentity($product);
        $color = $this->extractColorMention($searchable);
        $slugHint = null;
        $normalizedSlug = mb_strtolower(trim((string) $product->slug));
        if ($normalizedSlug !== '' && str_contains($searchable, $normalizedSlug)) {
            $slugHint = $this->nullableString($product->slug) ?? $normalizedSlug;
        }
        $intent = $this->inferSpecificProductIntent($searchable);

        $searchKeywords = collect([
            $product->name,
            $product->name_en,
            $product->name_ka,
            $product->slug,
            $brand,
            $model,
            $color,
        ])
            ->filter(fn ($keyword) => is_string($keyword) && trim($keyword) !== '')
            ->map(fn (string $keyword): string => trim($keyword))
            ->unique()
            ->values()
            ->all();

        return IntentResult::fromArray([
            'standalone_query' => trim($message),
            'intent' => $intent,
            'entities' => [
                'brand' => $brand,
                'model' => $model,
                'product_slug_hint' => $slugHint,
                'color' => $color,
                'category' => null,
            ],
            'needs_product_data' => true,
            'search_keywords' => $searchKeywords,
            'is_out_of_domain' => false,
            'confidence' => 0.98,
        ], 0);
    }

    private function findBestMatchingProduct(string $message): ?Product
    {
        $products = Product::query()
            ->active()
            ->select(['id', 'name_en', 'name_ka', 'slug', 'brand', 'model'])
            ->orderByDesc('featured')
            ->orderBy('id')
            ->limit(200)
            ->get();

        $bestProduct = null;
        $bestScore = 0;

        foreach ($products as $product) {
            $score = $this->scoreProductMention($message, $product);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestProduct = $product;
            }
        }

        return $bestScore > 0 ? $bestProduct : null;
    }

    private function scoreProductMention(string $message, Product $product): int
    {
        $normalizedMessage = $this->normalizeProductText($message);
        $score = 0;

        foreach ($this->productSearchCandidates($product) as $candidate) {
            if ($candidate === '' || !str_contains($normalizedMessage, $candidate)) {
                continue;
            }

            $score += max(12, mb_strlen($candidate));
        }

        return $score;
    }

    /**
     * @return array<int, string>
     */
    private function productSearchCandidates(Product $product): array
    {
        return collect([
            $this->normalizeProductText((string) $product->name),
            $this->normalizeProductText((string) $product->name_en),
            $this->normalizeProductText((string) $product->name_ka),
            $this->normalizeProductText((string) $product->slug),
            $this->normalizeProductText((string) trim((string) $product->brand . ' ' . (string) $product->model)),
        ])
            ->filter(fn (string $candidate): bool => $candidate !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function deriveProductIdentity(Product $product): array
    {
        $brand = $this->nullableString($product->brand);
        $model = $this->nullableString($product->model);

        if ($brand !== null && $model !== null) {
            return [$brand, $model];
        }

        $name = $this->normalizeProductText((string) $product->name);
        $tokens = collect(preg_split('/\s+/u', $name) ?: [])
            ->filter(fn ($token): bool => is_string($token) && trim($token) !== '')
            ->values()
            ->all();

        if ($tokens === []) {
            return [$brand, $model];
        }

        if ($brand === null && count($tokens) === 1) {
            $brand = $this->nullableString($tokens[0]);
        }

        if ($brand === null && count($tokens) >= 2) {
            $brand = $this->nullableString($tokens[0]);
        }

        if ($model === null) {
            $remaining = count($tokens) >= 2 ? array_slice($tokens, 1) : $tokens;
            $model = $this->nullableString(implode(' ', $remaining)) ?? ($this->nullableString($tokens[0]) ?? null);
        }

        return [$brand, $model];
    }

    private function inferSpecificProductIntent(string $message): string
    {
        $priceSignals = [
            'რა ღირს',
            'ფასი',
            'price',
            'cost',
            'how much',
            'გირდ',
            'ღირდა',
            'ღირ',
        ];

        if ($this->containsAnyNeedle($message, $priceSignals)) {
            return 'price_query';
        }

        $comparisonSignals = [
            'vs',
            'compare',
            'comparison',
            'შედარ',
            'რომელია უკეთესი',
            'რომელი ჯობია',
        ];

        if ($this->containsAnyNeedle($message, $comparisonSignals)) {
            return 'comparison';
        }

        $featureSignals = [
            'აქვს',
            'support',
            'features',
            'feature',
            'მხარს უჭერს',
            'ფუნქცია',
            'კამერა',
        ];

        if ($this->containsAnyNeedle($message, $featureSignals)) {
            return 'features';
        }

        return 'stock_query';
    }

    private function extractColorMention(string $message): ?string
    {
        $normalized = mb_strtolower(trim($message));

        if ($normalized === '') {
            return null;
        }

        $colorAliases = [
            'blue' => ['blue', 'ლურჯი', 'ლურჯ', 'ცისფერი'],
            'black' => ['black', 'შავი'],
            'white' => ['white', 'თეთრი'],
            'red' => ['red', 'წითელი'],
            'green' => ['green', 'მწვანე'],
            'yellow' => ['yellow', 'ყვითელი'],
            'pink' => ['pink', 'ვარდისფერი'],
            'gray' => ['gray', 'grey', 'ნაცრისფერი'],
        ];

        foreach ($colorAliases as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                if ($alias !== '' && str_contains($normalized, mb_strtolower($alias))) {
                    return $canonical;
                }
            }
        }

        return null;
    }

    private function normalizeProductText(string $text): string
    {
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower(trim($text)));

        return trim((string) $normalized);
    }

    private function looksLikeBudgetRecommendation(string $message, array $searchKeywords = [], array $entities = [], array $preferences = []): bool
    {
        $normalized = mb_strtolower(trim($message));

        if ($normalized === '') {
            return false;
        }

        if ($this->looksLikeSpecificProductRequest($normalized)) {
            return false;
        }

        foreach ([
            $entities['brand'] ?? null,
            $entities['model'] ?? null,
            $entities['product_slug_hint'] ?? null,
        ] as $value) {
            if (is_string($value) && trim($value) !== '') {
                return false;
            }
        }

        $haystack = $normalized;
        foreach ($searchKeywords as $keyword) {
            if (is_string($keyword) && trim($keyword) !== '') {
                $haystack .= ' ' . mb_strtolower(trim($keyword));
            }
        }

        if ($this->containsAnyNeedle($haystack, ['მირჩ', 'გირჩ', 'recommend', 'suggest', 'best'])
            && $this->containsAnyNeedle($haystack, ['საათ', 'watch', 'smartwatch', 'smart watch', 'kids', 'child', 'ბავშვ'])) {
            return true;
        }

        $budgetSignals = [
            'ფარგლებში',
            'ფარგლ',
            'ლარამდე',
            'ლარიან',
            'არაუმეტეს',
            'ბიუჯეტ',
            'budget',
            'within',
            'under',
            'up to',
        ];

        if (!$this->containsAnyNeedle($haystack, $budgetSignals)) {
            return false;
        }

        if (preg_match('/\d+(?:[.,]\d+)?/u', $haystack) === 1) {
            return true;
        }

        if (isset($preferences['budget_max_gel']) && is_numeric($preferences['budget_max_gel'])) {
            return true;
        }

        $recommendationSignals = [
            'მირჩევ',
            'გირჩევ',
            'recommend',
            'suggest',
            'გაქვთ',
            'რას',
            'what',
        ];

        if ($this->containsAnyNeedle($haystack, ['მირჩ', 'გირჩ', 'recommend', 'suggest', 'best'])
            && $this->containsAnyNeedle($haystack, ['საათ', 'watch', 'smartwatch', 'smart watch', 'kids', 'child', 'ბავშვ'])) {
            return true;
        }

        return $this->containsAnyNeedle($haystack, $recommendationSignals);
    }

    private function looksLikeSpecificProductRequest(string $message): bool
    {
        $normalized = trim($message);

        if ($normalized === '') {
            return false;
        }

        if (preg_match('/\b[\p{Lu}][\p{L}\p{N}]{2,}\s+[\p{Lu}][\p{L}\p{N}]{1,}(?:\s+[\p{L}\p{N}-]{2,})*/u', $normalized) === 1) {
            return true;
        }

        return preg_match('/\b[A-Z][A-Za-z0-9]+(?:-[A-Za-z0-9]+)*\s+[A-Z][A-Za-z0-9]+(?:-[A-Za-z0-9]+)*/', $normalized) === 1;
    }

    private function looksLikeAdultCatalogRequest(string $message): bool
    {
        $normalized = mb_strtolower(trim($message));

        if ($normalized === '') {
            return false;
        }

        if ($this->containsAnyNeedle($normalized, [
            'adult',
            'grown',
            'grown up',
            'men',
            'women',
            'male',
            'female',
            'ზრდასრულ',
            'მოზრდილ',
            'დიდებისთვის',
            'ქალებისთვის',
            'კაცებისთვის',
            'ქალის',
            'კაცის',
        ])) {
            return true;
        }

        return preg_match('/\b(1[89]|[2-9][0-9])\s*(?:\+|წლ|წლის|years?|yrs?)\b/u', $normalized) === 1;
    }

    private function looksLikeTrackingRecommendation(string $message): bool
    {
        $normalized = mb_strtolower(trim($message));

        if ($normalized === '') {
            return false;
        }

        return $this->containsAnyNeedle($normalized, [
            'გადაადგილების ისტორია',
            'ადგილმდებარეობა',
            'ადგილმდებარეობ',
            'ლოკაცია',
            'gps',
            'location',
            'tracker',
            'tracking',
            'ტრეკერი',
            'ტრეკერ',
            'anti-lost',
            'დაკარგვის',
        ]);
    }

    /**
     * @param array<int, string> $searchKeywords
     * @return array<int, string>
     */
    private function sanitizeSearchKeywords(array $searchKeywords, string $message): array
    {
        $negated = $this->negatedFeaturesInMessage($message);

        if ($negated === []) {
            return array_values(array_filter($searchKeywords, fn ($keyword) => is_string($keyword) && trim($keyword) !== ''));
        }

        $featureNeedles = [
            'camera' => ['camera', 'კამერა'],
            'calls' => ['call', 'calls', 'ზარი', 'ზარები', 'ზარის', 'ზარით', 'ვიდეო ზარი', 'ვიდეოზარი'],
        ];

        $filtered = [];

        foreach ($searchKeywords as $keyword) {
            if (!is_string($keyword)) {
                continue;
            }

            $normalizedKeyword = mb_strtolower(trim($keyword));
            if ($normalizedKeyword === '') {
                continue;
            }

            $skip = false;
            foreach ($negated as $feature) {
                foreach ($featureNeedles[$feature] ?? [] as $needle) {
                    if (str_contains($normalizedKeyword, mb_strtolower($needle))) {
                        $skip = true;
                        break 2;
                    }
                }
            }

            if (!$skip) {
                $filtered[] = trim($keyword);
            }
        }

        return array_values(array_unique($filtered));
    }

    private function stripNegatedFeaturePhrases(string $message): string
    {
        $normalized = trim($message);

        if ($normalized === '') {
            return '';
        }

        $negationPattern = '(?:არ მინდა|არ მჭირდება|არ მაინტერესებს|არ არის საჭირო|არ არის მნიშვნელოვანი|არ დამჭირდება|without|no)';

        foreach ([
            ['camera', 'კამერა'],
            ['call', 'calls', 'ზარი', 'ზარები', 'ზარის', 'ზარით', 'ვიდეო ზარი', 'ვიდეოზარი'],
        ] as $needles) {
            foreach ($needles as $needle) {
                $quoted = preg_quote($needle, '/');
                $normalized = preg_replace([
                    '/(?:' . $negationPattern . ')[^.!?\n]{0,24}' . $quoted . '/iu',
                    '/' . $quoted . '[^.!?\n]{0,24}(?:' . $negationPattern . ')/iu',
                ], ' ', $normalized) ?? $normalized;
            }
        }

        return trim(preg_replace('/\s{2,}/u', ' ', $normalized) ?? $normalized);
    }

    private function negatedFeaturesInMessage(string $message): array
    {
        $normalized = mb_strtolower($message);
        $negated = [];

        $featureNeedles = [
            'camera' => ['camera', 'კამერა'],
            'calls' => ['call', 'calls', 'ზარი', 'ზარები', 'ზარის', 'ზარით', 'ვიდეო ზარი', 'ვიდეოზარი'],
        ];

        foreach ($featureNeedles as $feature => $needles) {
            foreach ($needles as $needle) {
                if ($this->containsNegatedNeedle($normalized, $needle)) {
                    $negated[] = $feature;
                    break;
                }
            }
        }

        return array_values(array_unique($negated));
    }

    private function extractBudgetAmount(string $message): ?float
    {
        if (preg_match('/(\d+(?:[.,]\d+)?)/u', $message, $matches) !== 1) {
            return null;
        }

        return (float) str_replace(',', '.', (string) ($matches[1] ?? ''));
    }

    /**
     * @param array<int, string> $needles
     */
    private function containsAnyNeedle(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (!is_string($needle) || trim($needle) === '') {
                continue;
            }

            if (mb_stripos($haystack, mb_strtolower(trim($needle))) !== false) {
                return true;
            }
        }

        return false;
    }

    private function containsNegatedNeedle(string $normalized, string $needle): bool
    {
        $quoted = preg_quote(mb_strtolower($needle), '/');
        $negationPattern = '(?:არ მინდა|არ მჭირდება|არ მაინტერესებს|არ არის საჭირო|არ არის მნიშვნელოვანი|არ დამჭირდება|without|no)';

        return preg_match('/(?:' . $negationPattern . ')[^.!?\n]{0,24}' . $quoted . '/u', $normalized) === 1
            || preg_match('/' . $quoted . '[^.!?\n]{0,24}(?:' . $negationPattern . ')/u', $normalized) === 1;
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

    private function langfuse(): LangfuseService
    {
        try {
            return app(LangfuseService::class);
        } catch (BindingResolutionException) {
            return new LangfuseService();
        }
    }
}
