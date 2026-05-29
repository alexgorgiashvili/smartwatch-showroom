<?php

namespace Tests\Unit;

use App\Services\Chatbot\EmbeddingService;
use App\Services\Chatbot\HybridSearchService;
use App\Services\Chatbot\MultiLayerCacheService;
use App\Services\Chatbot\PineconeService;
use App\Services\Chatbot\UnifiedAiPolicyService;
use Mockery;
use Tests\TestCase;

class EmbeddingCacheHitTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testSecondEmbeddingCallHitsCache(): void
    {
        config()->set('services.openai.key', 'test-key');
        config()->set('chatbot.caching.enabled', true);

        $fakeVector = array_fill(0, 10, 0.1);

        $embeddingMock = Mockery::mock(EmbeddingService::class);
        $embeddingMock->shouldReceive('isConfigured')->andReturn(true);
        // embed() should be called exactly ONCE — the second call should hit cache
        $embeddingMock->shouldReceive('embed')
            ->once()
            ->with('test query')
            ->andReturn($fakeVector);

        $cacheService = new MultiLayerCacheService($embeddingMock);

        // First call — triggers real embed
        $result1 = $cacheService->getOrCacheEmbedding('test query');
        $this->assertSame($fakeVector, $result1);

        // Second call — should hit cache, NOT call embed again
        $result2 = $cacheService->getOrCacheEmbedding('test query');
        $this->assertSame($fakeVector, $result2);
    }

    public function testHybridSearchUsesEmbeddingCache(): void
    {
        config()->set('services.openai.key', 'test-key');
        config()->set('services.pinecone.api_key', 'test-key');
        config()->set('services.pinecone.host', 'https://test.pinecone.io');
        config()->set('chatbot.caching.enabled', true);

        $fakeVector = array_fill(0, 10, 0.1);

        $embeddingMock = Mockery::mock(EmbeddingService::class);
        $embeddingMock->shouldReceive('isConfigured')->andReturn(true);
        // embed() should be called exactly ONCE across two hybridSearch calls
        $embeddingMock->shouldReceive('embed')
            ->once()
            ->with('test query')
            ->andReturn($fakeVector);

        $pineconeMock = Mockery::mock(PineconeService::class);
        $pineconeMock->shouldReceive('isConfigured')->andReturn(true);
        $pineconeMock->shouldReceive('query')->andReturn([]);

        $policyMock = Mockery::mock(UnifiedAiPolicyService::class);
        $policyMock->shouldReceive('normalizeIncomingMessage')
            ->andReturnUsing(fn($q) => $q);

        $cacheService = new MultiLayerCacheService($embeddingMock);

        $hybrid = new HybridSearchService(
            $embeddingMock,
            $pineconeMock,
            $policyMock,
            $cacheService
        );

        // First search — triggers embed via cache
        $hybrid->hybridSearch('test query');

        // Second search — cache hit, no new embed call
        $hybrid->hybridSearch('test query');

        // Mockery's once() constraint is the assertion — embed called only once
        $this->addToAssertionCount(1);
    }

    public function testDifferentQueriesCallEmbedSeparately(): void
    {
        config()->set('services.openai.key', 'test-key');
        config()->set('chatbot.caching.enabled', true);

        $fakeVector1 = array_fill(0, 10, 0.1);
        $fakeVector2 = array_fill(0, 10, 0.2);

        $embeddingMock = Mockery::mock(EmbeddingService::class);
        $embeddingMock->shouldReceive('embed')
            ->once()
            ->with('query one')
            ->andReturn($fakeVector1);
        $embeddingMock->shouldReceive('embed')
            ->once()
            ->with('query two')
            ->andReturn($fakeVector2);

        $cacheService = new MultiLayerCacheService($embeddingMock);

        $result1 = $cacheService->getOrCacheEmbedding('query one');
        $result2 = $cacheService->getOrCacheEmbedding('query two');

        $this->assertSame($fakeVector1, $result1);
        $this->assertSame($fakeVector2, $result2);
    }
}
