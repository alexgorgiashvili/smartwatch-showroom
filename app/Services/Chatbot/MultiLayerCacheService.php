<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Cache;

class MultiLayerCacheService
{
    private const CACHE_KEY_VERSION = 'v6';
    private const EMBEDDING_CACHE_TTL = 3600;
    private const SEMANTIC_CACHE_TTL = 1800;
    private const RESPONSE_CACHE_TTL = 600;
    private const SEMANTIC_SIMILARITY_THRESHOLD = 0.95;

    public function __construct(
        private EmbeddingService $embeddingService
    ) {
    }

    /**
     * Get cached response (checks all layers)
     */
    public function getCachedResponse(string $query, IntentResult $intent, array $context = []): ?array
    {
        if (!config('chatbot.caching.enabled', true)) {
            return null;
        }

        // Try exact match first (fast)
        $exactMatch = $this->getExactMatch($query, $intent, $context);
        if ($exactMatch) {
            return array_merge($exactMatch, ['cache_layer' => 'exact']);
        }

        // Skip semantic matching if semantic cache is disabled or empty
        $intentKey = $intent->intent();
        $semanticIndex = $this->cache()->get($this->key($this->semanticIndexKey($intentKey, $context)), []);

        if (empty($semanticIndex)) {
            return null; // No semantic cache yet, skip expensive embedding
        }

        // Only do semantic matching if we have cached embeddings
        $semanticMatch = $this->getSemanticMatch($query, $intent, $context);
        if ($semanticMatch) {
            return array_merge($semanticMatch, ['cache_layer' => 'semantic']);
        }

        return null;
    }

    /**
     * Store response in cache
     */
    public function cacheResponse(string $query, IntentResult $intent, string $response, array $metadata = [], array $context = []): void
    {
        if (!config('chatbot.caching.enabled', true)) {
            return;
        }

        $queryHash = $this->hashQuery($query, $intent, $context);
        $intentKey = $intent->intent();
        $semanticIndex = $this->cache()->get($this->key($this->semanticIndexKey($intentKey, $context)), []);
        $embedding = [];

        if (!app()->environment('testing') && !empty($semanticIndex)) {
            $embedding = $this->getOrCacheEmbedding($query);
            $this->addToSemanticIndex($queryHash, $embedding, $intentKey, $context);
        }

        $cacheData = [
            'query' => $query,
            'intent' => $intent->intent(),
            'response' => $response,
            'embedding' => $embedding,
            'metadata' => $metadata,
            'cached_at' => time(),
        ];

        $this->cache()->put(
            $this->key("response:{$queryHash}"),
            $cacheData,
            config('chatbot.caching.layers.response.ttl', self::RESPONSE_CACHE_TTL)
        );
    }

    /**
     * Get or cache embedding for a query
     */
    public function getOrCacheEmbedding(string $query): array
    {
        if (!config('chatbot.caching.enabled', true)) {
            return $this->embeddingService->embed($query);
        }

        // Embeddings depend only on the query, so the existing shared cache
        // remains valid across widget cohorts and social channels.
        $queryHash = md5('v5|' . mb_strtolower(trim($query)) . '|||');

        return $this->cache()->remember(
            $this->key("embedding:{$queryHash}"),
            config('chatbot.caching.layers.embedding.ttl', self::EMBEDDING_CACHE_TTL),
            fn() => $this->embeddingService->embed($query)
        );
    }

    /**
     * Invalidate cache for product updates
     */
    public function invalidateProductCache(int $productId): void
    {
        $this->bumpNamespaceVersion();
    }

    /**
     * Clear all chatbot caches
     */
    public function clearAll(): void
    {
        $this->bumpNamespaceVersion();
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        return [
            'enabled' => config('chatbot.caching.enabled', true),
            'layers' => [
                'embedding' => [
                    'ttl' => config('chatbot.caching.layers.embedding.ttl', self::EMBEDDING_CACHE_TTL),
                ],
                'semantic' => [
                    'ttl' => config('chatbot.caching.layers.semantic.ttl', self::SEMANTIC_CACHE_TTL),
                    'threshold' => config('chatbot.caching.layers.semantic.threshold', self::SEMANTIC_SIMILARITY_THRESHOLD),
                ],
                'response' => [
                    'ttl' => config('chatbot.caching.layers.response.ttl', self::RESPONSE_CACHE_TTL),
                ],
            ],
        ];
    }

    private function getExactMatch(string $query, IntentResult $intent, array $context): ?array
    {
        $queryHash = $this->hashQuery($query, $intent, $context);
        return $this->cache()->get($this->key("response:{$queryHash}"));
    }

    private function getSemanticMatch(string $query, IntentResult $intent, array $context): ?array
    {
        $embedding = $this->getOrCacheEmbedding($query);
        $intentKey = $intent->intent();

        $semanticIndex = $this->cache()->get($this->key($this->semanticIndexKey($intentKey, $context)), []);

        $threshold = config('chatbot.caching.layers.semantic.threshold', self::SEMANTIC_SIMILARITY_THRESHOLD);

        foreach ($semanticIndex as $cachedHash => $cachedEmbedding) {
            $similarity = $this->cosineSimilarity($embedding, $cachedEmbedding);

            if ($similarity >= $threshold) {
                $cached = $this->cache()->get($this->key("response:{$cachedHash}"));
                if ($cached) {
                    return array_merge($cached, ['similarity' => $similarity]);
                }
            }
        }

        return null;
    }

    private function addToSemanticIndex(string $queryHash, array $embedding, string $intent, array $context): void
    {
        $intentKey = $intent;
        $indexKey = $this->semanticIndexKey($intentKey, $context);
        $semanticIndex = $this->cache()->get($this->key($indexKey), []);

        $semanticIndex[$queryHash] = $embedding;

        if (count($semanticIndex) > 100) {
            $semanticIndex = array_slice($semanticIndex, -100, 100, true);
        }

        $this->cache()->put(
            $this->key($indexKey),
            $semanticIndex,
            config('chatbot.caching.layers.semantic.ttl', self::SEMANTIC_CACHE_TTL)
        );
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $magnitudeA += $a[$i] * $a[$i];
            $magnitudeB += $b[$i] * $b[$i];
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        if ($magnitudeA == 0 || $magnitudeB == 0) {
            return 0.0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }

    private function cache()
    {
        $cache = Cache::store();

        return $cache->supportsTags()
            ? $cache->tags(['chatbot'])
            : $cache;
    }

    private function key(string $suffix): string
    {
        $version = (int) Cache::store()->get('chatbot:namespace_version', 1);

        return "chatbot:v{$version}:{$suffix}";
    }

    private function bumpNamespaceVersion(): void
    {
        $cache = Cache::store();
        $current = (int) $cache->get('chatbot:namespace_version', 1);
        $cache->forever('chatbot:namespace_version', $current + 1);
    }

    private function hashQuery(string $query, IntentResult $intent, array $context): string
    {
        $intentKey = $intent->intent();
        $category = $intent->category() ?? '';
        $facetKey = implode('|', array_filter([
            $intent->hasCatalogFacet() ? 'catalog_facet' : '',
            $intent->mentionsTwoGCatalog() ? '2g' : '',
            $intent->mentionsFourGCatalog() ? '4g' : '',
            $intent->mentionsDiscountCatalog() ? 'discount' : '',
        ]));

        $legacy = 'v5|' . mb_strtolower(trim($query)) . '|' . $intentKey . '|' . $category . '|' . $facetKey;
        return $context === []
            ? md5($legacy)
            : md5(self::CACHE_KEY_VERSION . '|' . $legacy . '|' . $this->contextHash($context));
    }

    private function semanticIndexKey(string $intent, array $context): string
    {
        return $context === []
            ? 'semantic_index:' . $intent
            : 'semantic_index:' . md5($intent . '|' . $this->contextHash($context));
    }

    private function contextHash(array $context): string
    {
        $prompt = config('chatbot-prompt', []);
        $normalized = [
            'channel' => (string) ($context['channel'] ?? 'omnichannel'),
            'model' => (string) ($context['model'] ?? config('chatbot.supervisor.model', 'gpt-4.1-mini')),
            'prompt_version' => (string) config('chatbot.caching.prompt_version', 'v1'),
            'prompt_hash' => hash('sha256', serialize($prompt)),
            'knowledge_version' => (string) ($context['knowledge_version'] ?? 'none'),
            'catalog_version' => (string) ($context['catalog_version'] ?? config('chatbot.caching.catalog_version', 'v1')),
            'conversation_id' => (string) ($context['conversation_id'] ?? ''),
        ];

        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }
}
