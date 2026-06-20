<?php

namespace Tests\Unit;

use App\Services\Chatbot\IntentAnalyzerService;
use App\Services\Chatbot\ModelCompletionService;
use App\Services\Chatbot\UnifiedAiPolicyService;
use App\Services\Chatbot\WidgetTraceLogger;
use Mockery;
use Tests\TestCase;

class IntentAnalyzerServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testBudgetRecommendationMessageUsesLocalHeuristic(): void
    {
        config()->set('services.openai.intent_enabled', true);
        config()->set('services.openai.key', '');

        $policy = Mockery::mock(UnifiedAiPolicyService::class);
        $policy->shouldReceive('normalizeIncomingMessage')
            ->once()
            ->andReturn('რამე 20 ლარის ფარგლებში გაქვთ?');

        $service = new IntentAnalyzerService($policy, new WidgetTraceLogger(), Mockery::mock(ModelCompletionService::class));

        $intent = $service->analyze('რამე 20 ლარის ფარგლებში გაქვთ?', [], ['budget_max_gel' => 20]);

        $this->assertSame('recommendation', $intent->intent());
        $this->assertSame('რამე 20 ლარის ფარგლებში გაქვთ?', $intent->standaloneQuery());
        $this->assertTrue($intent->needsProductData());
        $this->assertContains('20 ლარის ფარგლებში', $intent->searchKeywords());
    }

    public function testAdultCatalogRequestUsesOutOfDomainHeuristic(): void
    {
        config()->set('services.openai.intent_enabled', true);
        config()->set('services.openai.key', '');

        $policy = Mockery::mock(UnifiedAiPolicyService::class);
        $policy->shouldReceive('normalizeIncomingMessage')
            ->once()
            ->andReturn('არ მინდა საბავშვო, ზრდასრულის smartwatch გაქვთ?');

        $service = new IntentAnalyzerService($policy, new WidgetTraceLogger(), Mockery::mock(ModelCompletionService::class));

        $intent = $service->analyze('არ მინდა საბავშვო, ზრდასრულის smartwatch გაქვთ?');

        $this->assertSame('out_of_domain', $intent->intent());
        $this->assertTrue($intent->isOutOfDomain());
        $this->assertFalse($intent->needsProductData());
    }

    public function testTrackingHistoryRequestUsesRecommendationHeuristic(): void
    {
        config()->set('services.openai.intent_enabled', true);
        config()->set('services.openai.key', '');

        $policy = Mockery::mock(UnifiedAiPolicyService::class);
        $policy->shouldReceive('normalizeIncomingMessage')
            ->once()
            ->andReturn('მხოლოდ ლოკაცია და გადაადგილების ისტორია მინდა, ზარი და კამერა საერთოდ არ არის მნიშვნელოვანი');

        $service = new IntentAnalyzerService($policy, new WidgetTraceLogger(), Mockery::mock(ModelCompletionService::class));

        $intent = $service->analyze('მხოლოდ ლოკაცია და გადაადგილების ისტორია მინდა, ზარი და კამერა საერთოდ არ არის მნიშვნელოვანი');

        $this->assertSame('recommendation', $intent->intent());
        $this->assertContains('ლოკაცია', $intent->searchKeywords());
        $this->assertContains('გადაადგილების ისტორია', $intent->searchKeywords());
    }

    public function testModelCompletionResponseIsParsedIntoIntentResult(): void
    {
        config()->set('services.openai.intent_enabled', true);

        $policy = Mockery::mock(UnifiedAiPolicyService::class);
        $policy->shouldReceive('normalizeIncomingMessage')
            ->once()
            ->andReturn('Q12 ჯობია თუ CT23?');

        $modelCompletion = Mockery::mock(ModelCompletionService::class);
        $modelCompletion->shouldReceive('complete')
            ->once()
            ->andReturn([
                'reply' => json_encode([
                    'standalone_query' => 'Q12 და CT23 შედარება',
                    'intent' => 'comparison',
                    'entities' => [
                        'brand' => null,
                        'model' => 'Q12',
                        'product_slug_hint' => null,
                        'color' => null,
                        'category' => null,
                    ],
                    'needs_product_data' => true,
                    'search_keywords' => ['Q12', 'CT23'],
                    'is_out_of_domain' => false,
                    'confidence' => 0.93,
                ], JSON_UNESCAPED_UNICODE),
                'reason' => null,
                'usage' => [],
            ]);

        $service = new IntentAnalyzerService($policy, new WidgetTraceLogger(), $modelCompletion);

        $intent = $service->analyze('Q12 ჯობია თუ CT23?');

        $this->assertSame('comparison', $intent->intent());
        $this->assertSame('Q12 და CT23 შედარება', $intent->standaloneQuery());
        $this->assertContains('CT23', $intent->searchKeywords());
    }
    public function testTwoGCatalogRequestUsesLocalFacetHeuristic(): void
    {
        config()->set('services.openai.intent_enabled', true);
        config()->set('services.openai.key', '');

        $policy = Mockery::mock(UnifiedAiPolicyService::class);
        $policy->shouldReceive('normalizeIncomingMessage')
            ->once()
            ->andReturn('რომელი 2G მოდელები გაქვთ?');

        $service = new IntentAnalyzerService($policy, new WidgetTraceLogger(), Mockery::mock(ModelCompletionService::class));

        $intent = $service->analyze('რომელი 2G მოდელები გაქვთ?');

        $this->assertSame('recommendation', $intent->intent());
        $this->assertTrue($intent->hasCatalogFacet());
        $this->assertTrue($intent->mentionsTwoGCatalog());
        $this->assertFalse($intent->mentionsFourGCatalog());
        $this->assertSame('2g_catalog', $intent->category());
        $this->assertContains('2G', $intent->searchKeywords());
    }

    public function testFourGCatalogRequestUsesLocalFacetHeuristic(): void
    {
        config()->set('services.openai.intent_enabled', true);
        config()->set('services.openai.key', '');

        $policy = Mockery::mock(UnifiedAiPolicyService::class);
        $policy->shouldReceive('normalizeIncomingMessage')
            ->once()
            ->andReturn('რომელი 4G მოდელები გაქვთ?');

        $service = new IntentAnalyzerService($policy, new WidgetTraceLogger(), Mockery::mock(ModelCompletionService::class));

        $intent = $service->analyze('რომელი 4G მოდელები გაქვთ?');

        $this->assertSame('recommendation', $intent->intent());
        $this->assertTrue($intent->hasCatalogFacet());
        $this->assertTrue($intent->mentionsFourGCatalog());
        $this->assertFalse($intent->mentionsTwoGCatalog());
        $this->assertSame('4g_catalog', $intent->category());
        $this->assertContains('4G', $intent->searchKeywords());
    }

    public function testDiscountCatalogRequestUsesLocalFacetHeuristic(): void
    {
        config()->set('services.openai.intent_enabled', true);
        config()->set('services.openai.key', '');

        $policy = Mockery::mock(UnifiedAiPolicyService::class);
        $policy->shouldReceive('normalizeIncomingMessage')
            ->once()
            ->andReturn('ფასდაკლებით რომელი მოდელები გაქვთ?');

        $service = new IntentAnalyzerService($policy, new WidgetTraceLogger(), Mockery::mock(ModelCompletionService::class));

        $intent = $service->analyze('ფასდაკლებით რომელი მოდელები გაქვთ?');

        $this->assertSame('price_query', $intent->intent());
        $this->assertTrue($intent->hasCatalogFacet());
        $this->assertTrue($intent->mentionsDiscountCatalog());
        $this->assertSame('discounted_catalog', $intent->category());
        $this->assertContains('sale', $intent->searchKeywords());
    }
}
