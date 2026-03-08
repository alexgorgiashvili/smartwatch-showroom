<?php

namespace Tests\Unit;

use App\Services\Chatbot\IntentAnalyzerService;
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

        $service = new IntentAnalyzerService($policy, new WidgetTraceLogger());

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

        $service = new IntentAnalyzerService($policy, new WidgetTraceLogger());

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

        $service = new IntentAnalyzerService($policy, new WidgetTraceLogger());

        $intent = $service->analyze('მხოლოდ ლოკაცია და გადაადგილების ისტორია მინდა, ზარი და კამერა საერთოდ არ არის მნიშვნელოვანი');

        $this->assertSame('recommendation', $intent->intent());
        $this->assertContains('ლოკაცია', $intent->searchKeywords());
        $this->assertContains('გადაადგილების ისტორია', $intent->searchKeywords());
    }
}
