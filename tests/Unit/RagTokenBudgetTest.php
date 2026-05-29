<?php

namespace Tests\Unit;

use App\Models\ChatbotDocument;
use App\Services\Chatbot\HybridSearchService;
use App\Services\Chatbot\IntentResult;
use App\Services\Chatbot\RagContextBuilder;
use App\Services\Chatbot\RerankService;
use App\Services\Chatbot\UnifiedAiPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RagTokenBudgetTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testVeryLongRagContextIsTruncatedAtConfiguredMaxChars(): void
    {
        config()->set('chatbot.rag.max_chars', 3000);

        $longContent = str_repeat('ეს არის ძალიან გრძელი RAG კონტექსტის ტექსტი. ', 250);

        ChatbotDocument::query()->create([
            'key' => 'faq-long',
            'type' => 'faq',
            'title' => 'გრძელი FAQ',
            'content_ka' => $longContent,
            'product_id' => null,
            'metadata' => [],
            'pinecone_id' => 'pc-faq-long',
            'is_active' => true,
        ]);

        $hybridSearch = Mockery::mock(HybridSearchService::class);
        $hybridSearch->shouldReceive('isConfigured')->once()->andReturn(true);
        $hybridSearch->shouldReceive('hybridSearch')
            ->once()
            ->andReturn([
                [
                    'metadata' => ['key' => 'faq-long'],
                ],
            ]);

        $rerank = Mockery::mock(RerankService::class);
        $rerank->shouldReceive('rerank')
            ->once()
            ->andReturn([
                [
                    'metadata' => ['key' => 'faq-long'],
                    'score' => 0.95,
                ],
            ]);
        $rerank->shouldReceive('isConfigured')->once()->andReturn(true);

        $policy = Mockery::mock(UnifiedAiPolicyService::class);
        $policy->shouldReceive('normalizeIncomingMessage')
            ->andReturnUsing(fn (string $message): string => $message);

        $builder = new RagContextBuilder($hybridSearch, $rerank, $policy);

        $intent = IntentResult::fromArray([
            'standalone_query' => 'GPS საათი',
            'intent' => 'features',
            'entities' => [],
            'needs_product_data' => true,
            'search_keywords' => ['GPS'],
            'is_out_of_domain' => false,
            'confidence' => 0.9,
        ], 0);

        $ragContext = $builder->build('GPS საათი', 5, [], $intent);

        $this->assertNotNull($ragContext);
        $this->assertLessThanOrEqual(3000, mb_strlen((string) $ragContext));
        $this->assertMatchesRegularExpression('/\p{Georgian}/u', (string) $ragContext);
    }
}
