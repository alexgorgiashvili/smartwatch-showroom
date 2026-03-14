<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;

class AiCacheService
{
    /**
     * Cache TTLs in seconds
     */
    private const PRODUCT_CATALOG_TTL = 300;      // 5 minutes
    private const RECOMMENDATIONS_TTL = 900;       // 15 minutes
    private const KNOWLEDGE_BASE_TTL = 3600;       // 1 hour
    private const SCHEMA_MARKUP_TTL = 86400;       // 1 day

    /**
     * Cache product catalog
     */
    public function cacheProductCatalog(string $locale, callable $callback): mixed
    {
        $key = "ai:products:catalog:{$locale}";
        
        return Cache::remember($key, self::PRODUCT_CATALOG_TTL, $callback);
    }

    /**
     * Cache recommendations
     */
    public function cacheRecommendations(string $query, ?int $age, ?float $budget, array $features, callable $callback): mixed
    {
        $key = $this->generateRecommendationKey($query, $age, $budget, $features);
        
        return Cache::remember($key, self::RECOMMENDATIONS_TTL, $callback);
    }

    /**
     * Cache knowledge base content
     */
    public function cacheKnowledgeBase(string $topic, string $locale, callable $callback): mixed
    {
        $key = "ai:knowledge:{$topic}:{$locale}";
        
        return Cache::remember($key, self::KNOWLEDGE_BASE_TTL, $callback);
    }

    /**
     * Cache schema markup
     */
    public function cacheSchemaMarkup(string $type, int $id, callable $callback): mixed
    {
        $key = "ai:schema:{$type}:{$id}";
        
        return Cache::remember($key, self::SCHEMA_MARKUP_TTL, $callback);
    }

    /**
     * Invalidate product catalog cache
     */
    public function invalidateProductCatalog(?string $locale = null): void
    {
        if ($locale) {
            Cache::forget("ai:products:catalog:{$locale}");
        } else {
            // Invalidate all locales
            Cache::forget("ai:products:catalog:ka");
            Cache::forget("ai:products:catalog:en");
        }
    }

    /**
     * Invalidate all recommendations cache
     */
    public function invalidateRecommendations(): void
    {
        // Clear all recommendation caches by pattern
        Cache::tags(['ai:recommendations'])->flush();
    }

    /**
     * Invalidate specific product schema
     */
    public function invalidateProductSchema(int $productId): void
    {
        Cache::forget("ai:schema:product:{$productId}");
    }

    /**
     * Invalidate all AI caches
     */
    public function invalidateAll(): void
    {
        $this->invalidateProductCatalog();
        $this->invalidateRecommendations();
        
        // Clear all AI-related caches
        Cache::tags(['ai'])->flush();
    }

    /**
     * Generate cache key for recommendations
     */
    private function generateRecommendationKey(string $query, ?int $age, ?float $budget, array $features): string
    {
        $params = [
            'query' => mb_strtolower(trim($query)),
            'age' => $age,
            'budget' => $budget,
            'features' => sort($features) ? implode(',', $features) : '',
        ];
        
        $hash = md5(json_encode($params));
        
        return "ai:recommendations:{$hash}";
    }

    /**
     * Get cache statistics
     */
    public function getStatistics(): array
    {
        return [
            'product_catalog_ttl' => self::PRODUCT_CATALOG_TTL,
            'recommendations_ttl' => self::RECOMMENDATIONS_TTL,
            'knowledge_base_ttl' => self::KNOWLEDGE_BASE_TTL,
            'schema_markup_ttl' => self::SCHEMA_MARKUP_TTL,
        ];
    }

    /**
     * Warm up cache with popular data
     */
    public function warmUp(): void
    {
        // Warm up Georgian product catalog
        $this->cacheProductCatalog('ka', function () {
            return app(\App\Http\Controllers\Api\AiProductsController::class)
                ->index(request()->merge(['lang' => 'ka']))
                ->getData(true);
        });

        // Warm up English product catalog
        $this->cacheProductCatalog('en', function () {
            return app(\App\Http\Controllers\Api\AiProductsController::class)
                ->index(request()->merge(['lang' => 'en']))
                ->getData(true);
        });
    }
}
