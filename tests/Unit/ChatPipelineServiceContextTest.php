<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\Chatbot\AdaptiveLearningService;
use App\Services\Chatbot\ChatPipelineService;
use App\Services\Chatbot\ChatbotFallbackStrategyService;
use App\Services\Chatbot\ChatbotQualityMetricsService;
use App\Services\Chatbot\ConversationMemoryService;
use App\Services\Chatbot\InputGuardService;
use App\Services\Chatbot\IntentAnalyzerService;
use App\Services\Chatbot\IntentResult;
use App\Services\Chatbot\ResponseValidatorService;
use App\Services\Chatbot\SearchContext;
use App\Services\Chatbot\SmartSearchOrchestrator;
use App\Services\Chatbot\UnifiedAiPolicyService;
use App\Services\Chatbot\WidgetTraceLogger;
use Illuminate\Support\Collection;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class ChatPipelineServiceContextTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testRecommendationPromptSkipsRagWhenProductsAreAvailable(): void
    {
        $service = $this->makeService();
        $intent = $this->intent('recommendation');
        $products = new Collection([$this->product('wonlex-ct23')]);
        $searchContext = new SearchContext(str_repeat('RAG ', 500), $products, null, null);

        $resolved = $this->invokeResolvePromptRagContext($service, $intent, $searchContext, $products, null);

        $this->assertSame('', $resolved);
    }

    public function testGeneralPromptKeepsTrimmedRagWhenNoProductsAreAvailable(): void
    {
        $service = $this->makeService();
        $intent = $this->intent('general');
        $products = new Collection();
        $searchContext = new SearchContext(str_repeat('RAG ', 500), $products, null, null);

        $resolved = $this->invokeResolvePromptRagContext($service, $intent, $searchContext, $products, null);

        $this->assertNotSame('', $resolved);
        $this->assertLessThanOrEqual(1212, mb_strlen($resolved));
    }

    public function testAdultOutOfDomainShortcutMentionsKidFocusedCatalog(): void
    {
        $service = $this->makeService();
        $intent = new IntentResult(
            'ზრდასრულისთვის მინდა',
            'out_of_domain',
            null,
            null,
            null,
            null,
            'adult_smartwatch',
            false,
            [],
            true,
            0.94,
            0,
            false
        );

        $method = new ReflectionMethod(ChatPipelineService::class, 'routeNonSearchIntent');
        $method->setAccessible(true);
        $reply = $method->invoke($service, $intent);

        $this->assertIsString($reply);
        $this->assertStringContainsString('ძირითადად საბავშვო', $reply);
        $this->assertStringContainsString('ზრდასრულის მოდელი არ გვაქვს', $reply);
    }

    public function testBudgetPromptContextPrefersWithinBudgetProductsFirst(): void
    {
        $service = $this->makeService();
        $intent = $this->intent('recommendation');
        $products = new Collection([$this->pricedProduct(1, 'ct27', 20.50), $this->pricedProduct(2, 'ct23', 16.50)]);

        $method = new ReflectionMethod(ChatPipelineService::class, 'selectProductsForPromptContext');
        $method->setAccessible(true);
        $resolved = $method->invoke($service, $products, $intent, null, ['budget_max_gel' => 20]);

        $this->assertSame('ct23', $resolved->first()->slug);
    }

    private function makeService(): ChatPipelineService
    {
        return new ChatPipelineService(
            Mockery::mock(IntentAnalyzerService::class),
            Mockery::mock(SmartSearchOrchestrator::class),
            Mockery::mock(InputGuardService::class),
            Mockery::mock(ConversationMemoryService::class),
            Mockery::mock(UnifiedAiPolicyService::class),
            Mockery::mock(AdaptiveLearningService::class),
            Mockery::mock(ResponseValidatorService::class),
            Mockery::mock(ChatbotQualityMetricsService::class),
            Mockery::mock(ChatbotFallbackStrategyService::class),
            new WidgetTraceLogger()
        );
    }

    private function intent(string $intent): IntentResult
    {
        return new IntentResult(
            'query',
            $intent,
            null,
            null,
            null,
            null,
            null,
            true,
            [],
            false,
            0.9,
            10,
            false
        );
    }

    private function product(string $slug): Product
    {
        $product = new Product();
        $product->id = 1;
        $product->slug = $slug;

        return $product;
    }

    private function pricedProduct(int $id, string $slug, float $price): Product
    {
        $product = new Product();
        $product->id = $id;
        $product->slug = $slug;
        $product->setAttribute('price', $price);
        $product->setAttribute('sale_price', null);

        return $product;
    }

    private function invokeResolvePromptRagContext(
        ChatPipelineService $service,
        IntentResult $intent,
        SearchContext $searchContext,
        Collection $products,
        ?Product $requestedProduct
    ): string {
        $method = new ReflectionMethod(ChatPipelineService::class, 'resolvePromptRagContext');
        $method->setAccessible(true);

        return $method->invoke($service, $intent, $searchContext, $products, $requestedProduct);
    }
}
