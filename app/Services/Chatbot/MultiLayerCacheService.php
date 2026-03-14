<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Cache;

class MultiLayerCacheService
{
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
    public function getCachedResponse(string $query, IntentResult $intent): ?array
    {
        if (!config('chatbot.caching.enabled', true)) {
            return null;
        }

        // Try exact match first (fast)
        $exactMatch = $this->getExactMatch($query);
        if ($exactMatch) {
            return array_merge($exactMatch, ['cache_layer' => 'exact']);
        }

        // Skip semantic matching if semantic cache is disabled or empty
        $intentKey = $intent->intent();
        $semanticIndex = Cache::get("chatbot:semantic_index:{$intentKey}", []);

        if (empty($semanticIndex)) {
            return null; // No semantic cache yet, skip expensive embedding
        }

        // Only do semantic matching if we have cached embeddings
        $semanticMatch = $this->getSemanticMatch($query, $intent);
        if ($semanticMatch) {
            return array_merge($semanticMatch, ['cache_layer' => 'semantic']);
        }

        return null;
    }

    /**
     * Store response in cache
     */
    public function cacheResponse(string $query, IntentResult $intent, string $response, array $metadata = []): void
    {
        if (!config('chatbot.caching.enabled', true)) {
            return;
        }

        $queryHash = $this->hashQuery($query);
        $embedding = $this->getOrCacheEmbedding($query);

        $cacheData = [
            'query' => $query,
            'intent' => $intent->intent(),
            'response' => $response,
            'embedding' => $embedding,
            'metadata' => $metadata,
            'cached_at' => time(),
        ];

        Cache::put(
            "chatbot:response:{$queryHash}",
            $cacheData,
            config('chatbot.caching.layers.response.ttl', self::RESPONSE_CACHE_TTL)
        );

        $this->addToSemanticIndex($queryHash, $embedding, $intent->intent());
    }

    /**
     * Get or cache embedding for a query
     */
    public function getOrCacheEmbedding(string $query): array
    {
        if (!config('chatbot.caching.enabled', true)) {
            return $this->embeddingService->embed($query);
        }

        $queryHash = $this->hashQuery($query);

        return Cache::remember(
            "chatbot:embedding:{$queryHash}",
            config('chatbot.caching.layers.embedding.ttl', self::EMBEDDING_CACHE_TTL),
            fn() => $this->embeddingService->embed($query)
        );
    }

    /**
     * Invalidate cache for product updates
     */
    public function invalidateProductCache(int $productId): void
    {
        Cache::tags(['chatbot:products'])->flush();
    }

    /**
     * Clear all chatbot caches
     */
    public function clearAll(): void
    {
        Cache::tags(['chatbot'])->flush();
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

    private function getExactMatch(string $query): ?array
    {
        $queryHash = $this->hashQuery($query);
        return Cache::get("chatbot:response:{$queryHash}");
    }

    private function getSemanticMatch(string $query, IntentResult $intent): ?array
    {
        $embedding = $this->getOrCacheEmbedding($query);
        $intentKey = $intent->intent();

        $semanticIndex = Cache::get("chatbot:semantic_index:{$intentKey}", []);

        $threshold = config('chatbot.caching.layers.semantic.threshold', self::SEMANTIC_SIMILARITY_THRESHOLD);

        foreach ($semanticIndex as $cachedHash => $cachedEmbedding) {
            $similarity = $this->cosineSimilarity($embedding, $cachedEmbedding);

            if ($similarity >= $threshold) {
                $cached = Cache::get("chatbot:response:{$cachedHash}");
                if ($cached) {
                    return array_merge($cached, ['similarity' => $similarity]);
                }
            }
        }

        return null;
    }

    private function addToSemanticIndex(string $queryHash, array $embedding, string $intent): void
    {
        $intentKey = $intent;
        $semanticIndex = Cache::get("chatbot:semantic_index:{$intentKey}", []);

        $semanticIndex[$queryHash] = $embedding;

        if (count($semanticIndex) > 100) {
            $semanticIndex = array_slice($semanticIndex, -100, 100, true);
        }

        Cache::put(
            "chatbot:semantic_index:{$intentKey}",
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

    private function hashQuery(string $query): string
    {
        return md5(mb_strtolower(trim($query)));
    }
}
