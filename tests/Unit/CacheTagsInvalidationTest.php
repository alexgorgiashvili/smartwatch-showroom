<?php

namespace Tests\Unit;

use App\Services\Chatbot\EmbeddingService;
use App\Services\Chatbot\IntentResult;
use App\Services\Chatbot\MultiLayerCacheService;
use Tests\TestCase;

class CacheTagsInvalidationTest extends TestCase
{
    private MultiLayerCacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('chatbot.caching.enabled', true);

        $embedding = $this->createMock(EmbeddingService::class);
        $embedding->method('embed')->willReturn(array_fill(0, 16, 0.1));

        $this->cacheService = new MultiLayerCacheService($embedding);
    }

    public function testCachedEmbeddingIsFoundAfterWrite(): void
    {
        $embedding = $this->cacheService->getOrCacheEmbedding('GPS საათი');

        $this->assertIsArray($embedding);
        $this->assertNotEmpty($embedding);

        $again = $this->cacheService->getOrCacheEmbedding('GPS საათი');
        $this->assertSame($embedding, $again);
    }

    public function testClearAllFlushesEmbeddingCache(): void
    {
        $this->cacheService->getOrCacheEmbedding('GPS საათი');

        $this->cacheService->clearAll();

        $callCount = 0;
        $embedding = $this->createMock(EmbeddingService::class);
        $embedding->expects($this->once())
            ->method('embed')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                return array_fill(0, 16, 0.2);
            });

        $fresh = new MultiLayerCacheService($embedding);
        $fresh->getOrCacheEmbedding('GPS საათი');

        $this->assertSame(1, $callCount, 'EmbeddingService::embed must be called again after clearAll()');
    }

    public function testClearAllFlushesResponseCache(): void
    {
        $intent = IntentResult::fromArray([
            'intent' => 'price_query',
            'standalone_query' => 'GPS ფასი',
            'entities' => [],
            'search_keywords' => [],
            'needs_product_data' => false,
            'is_out_of_domain' => false,
            'confidence' => 0.9,
        ], 0);

        $this->cacheService->cacheResponse('GPS ფასი', $intent, 'GPS საათი 200 ₾ ღირს.', []);

        $hit = $this->cacheService->getCachedResponse('GPS ფასი', $intent);
        $this->assertNotNull($hit, 'Response must be cached before clearAll()');

        $this->cacheService->clearAll();

        $miss = $this->cacheService->getCachedResponse('GPS ფასი', $intent);
        $this->assertNull($miss, 'Response cache must be empty after clearAll()');
    }

    public function testInvalidateProductCacheFlushesResponseCache(): void
    {
        $intent = IntentResult::fromArray([
            'intent' => 'stock_query',
            'standalone_query' => 'მარაგი',
            'entities' => [],
            'search_keywords' => [],
            'needs_product_data' => false,
            'is_out_of_domain' => false,
            'confidence' => 0.9,
        ], 0);

        $this->cacheService->cacheResponse('მარაგი', $intent, 'მარაგშია.', []);

        $this->cacheService->invalidateProductCache(42);

        $hit = $this->cacheService->getCachedResponse('მარაგი', $intent);
        $this->assertNull($hit, 'Product cache flush must also clear response cache');
    }
}
