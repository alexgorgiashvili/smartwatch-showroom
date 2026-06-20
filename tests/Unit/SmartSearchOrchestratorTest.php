<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\Chatbot\IntentResult;
use App\Services\Chatbot\RagContextBuilder;
use App\Services\Chatbot\SmartSearchOrchestrator;
use App\Services\Chatbot\UnifiedAiPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SmartSearchOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testSearchRanksExactBrandModelMatchBeforeLooserVariants(): void
    {
        $this->createProduct('mytechnic-ultra-pro', 'MyTechnic Ultra Pro', 'MyTechnic', 'Ultra Pro');
        $exact = $this->createProduct('mytechnic-ultra', 'MyTechnic Ultra', 'MyTechnic', 'Ultra');
        $this->createProduct('mytechnic-lite', 'MyTechnic Lite', 'MyTechnic', 'Lite');

        $context = $this->makeOrchestrator()->search($this->makeIntent(slugHint: null));

        $this->assertSame($exact->id, $context->requestedProduct()?->id);
        $this->assertSame(['mytechnic-ultra', 'mytechnic-ultra-pro'], $context->products()->take(2)->pluck('slug')->all());
    }

    public function testSearchRanksClosestFuzzySlugMatchFirstWhenExactSlugIsMissing(): void
    {
        $closest = $this->createProduct('mytechnic-ultra-2025', 'MyTechnic Ultra 2025', 'MyTechnic', 'Ultra 2025');
        $this->createProduct('mytechnic-ultra-pro', 'MyTechnic Ultra Pro', 'MyTechnic', 'Ultra Pro');
        $this->createProduct('mytechnic-neo', 'MyTechnic Neo', 'MyTechnic', 'Neo');

        $intent = $this->makeIntent(slugHint: 'mytechnic-ultra-202');
        $context = $this->makeOrchestrator()->search($intent);

        $this->assertSame($closest->id, $context->requestedProduct()?->id);
        $this->assertSame('mytechnic-ultra-2025', $context->products()->first()?->slug);
    }

    public function testRecommendationSearchSkipsRagWhenProductsAlreadyMatch(): void
    {
        $this->createProduct('wonlex-gps-kt34', 'Wonlex KT34 GPS SOS', 'Wonlex', 'KT34');
        $this->createProduct('wonlex-gps-ct27', 'Wonlex CT27 GPS SOS', 'Wonlex', 'CT27');

        $ragBuilder = Mockery::mock(RagContextBuilder::class);
        $ragBuilder->shouldNotReceive('build');

        $context = $this->makeOrchestrator($ragBuilder)->search(new IntentResult(
            'ბავშვის საათი GPS და SOS ფუნქციებით',
            'recommendation',
            null,
            null,
            null,
            null,
            null,
            true,
            ['GPS', 'SOS'],
            false,
            0.9,
            20,
            false
        ));

        $this->assertSame('', $context->ragContext());
        $this->assertCount(2, $context->products());
    }

    public function testRecommendationSearchBuildsRagWhenNoProductsMatch(): void
    {
        $ragBuilder = Mockery::mock(RagContextBuilder::class);
        $ragBuilder->shouldReceive('build')
            ->once()
            ->andReturn('faq context');

        $intent = new IntentResult(
            'ბავშვის საათი GPS და SOS ფუნქციებით',
            'recommendation',
            null,
            null,
            null,
            null,
            null,
            true,
            ['GPS', 'SOS'],
            false,
            0.9,
            20,
            false
        );

        $context = $this->makeOrchestrator($ragBuilder)->search($intent);

        $this->assertSame('faq context', $context->ragContext());
        $this->assertCount(0, $context->products());
    }

    public function testUnknownProductSearchReturnsEmptyCollectionInsteadOfRecentProducts(): void
    {
        $this->createProduct('wonlex-kt34', 'Wonlex KT34', 'Wonlex', 'KT34');
        $this->createProduct('wonlex-ct27', 'Wonlex CT27', 'Wonlex', 'CT27');

        $ragBuilder = Mockery::mock(RagContextBuilder::class);
        $ragBuilder->shouldReceive('build')
            ->once()
            ->andReturn('fallback faq context');

        $intent = new IntentResult(
            'აბსოლუტურად უცნობი მოდელი მაჩვენე',
            'price_query',
            'UnknownBrand',
            'UnknownModel',
            'unknown-brand-unknown-model',
            null,
            null,
            true,
            ['UnknownBrand', 'UnknownModel'],
            false,
            0.9,
            20,
            false
        );

        $context = $this->makeOrchestrator($ragBuilder)->search($intent);

        $this->assertCount(0, $context->products());
        $this->assertNull($context->requestedProduct());
        $this->assertSame('fallback faq context', $context->ragContext());
    }

    public function testComparisonSearchKeepsSecondaryModelFromKeywords(): void
    {
        $this->createProduct('q12-watch', 'Q12', 'Wonlex', 'Q12');
        $this->createProduct('wonlex-ct23', 'Wonlex CT23', 'Wonlex', 'CT23');
        $this->createProduct('wonlex-kt34', 'Wonlex KT34', 'Wonlex', 'KT34');

        $ragBuilder = Mockery::mock(RagContextBuilder::class);
        $ragBuilder->shouldReceive('build')->once()->andReturn('comparison context');

        $context = $this->makeOrchestrator($ragBuilder)->search(new IntentResult(
            'Q12 ჯობია თუ CT23?',
            'comparison',
            null,
            'Q12',
            null,
            null,
            null,
            true,
            ['Q12', 'CT23'],
            false,
            0.9,
            20,
            false
        ));

        $this->assertSame(['q12-watch', 'wonlex-ct23'], $context->products()->take(2)->pluck('slug')->all());
    }

    public function testTwoGCatalogSearchReturnsEveryMatchingModel(): void
    {
        $this->createProduct('q21-2g', 'Q21 2G', 'Wonlex', 'Q21 2G', 79);
        $this->createProduct('q19-2g', 'Q19 2G', 'Wonlex', 'Q19 2G', 79, 59);
        $this->createProduct('q12-2g', 'Q12 2G', 'Wonlex', 'Q12 2G', 69);
        $this->createProduct('q15-2g', 'Q15 2G', 'Wonlex', 'Q15 2G', 89);
        $this->createProduct('ct24-4g', 'CT24 4G', 'Wonlex', 'CT24 4G', 189, 159);

        $context = $this->makeOrchestrator()->search($this->catalogIntent('რომელი 2G მოდელები გაქვთ?', 'recommendation', '2g_catalog', ['2G']));

        $this->assertSame(['q21-2g', 'q19-2g', 'q12-2g', 'q15-2g'], $context->products()->pluck('slug')->all());
        $this->assertSame('q21-2g', $context->requestedProduct()?->slug);
        $this->assertSame('', $context->ragContext());
    }

    public function testFourGCatalogSearchReturnsMoreThanFourModels(): void
    {
        $this->createProduct('q21-2g', 'Q21 2G', 'Wonlex', 'Q21 2G', 79);
        $this->createProduct('ct24-4g', 'CT24 4G', 'Wonlex', 'CT24 4G', 189, 159);
        $this->createProduct('ct23-4g', 'CT23 4G', 'Wonlex', 'CT23 4G', 199);
        $this->createProduct('ct27-4g', 'CT27 4G', 'Wonlex', 'CT27 4G', 209);
        $this->createProduct('kt34-4g', 'KT34 4G', 'Wonlex', 'KT34 4G', 219);
        $this->createProduct('t53-4g', 'T53 4G', 'Wonlex', 'T53 4G', 229);

        $context = $this->makeOrchestrator()->search($this->catalogIntent('რომელი 4G მოდელები გაქვთ?', 'recommendation', '4g_catalog', ['4G']));

        $this->assertSame(['ct24-4g', 'ct23-4g', 'ct27-4g', 'kt34-4g', 't53-4g'], $context->products()->pluck('slug')->all());
        $this->assertCount(5, $context->products());
    }

    public function testDiscountedTwoGCatalogSearchKeepsOnlyDiscountedModels(): void
    {
        $this->createProduct('q21-2g', 'Q21 2G', 'Wonlex', 'Q21 2G', 79, 69);
        $this->createProduct('q19-2g', 'Q19 2G', 'Wonlex', 'Q19 2G', 79, 59);
        $this->createProduct('q12-2g', 'Q12 2G', 'Wonlex', 'Q12 2G', 69);
        $this->createProduct('ct24-4g', 'CT24 4G', 'Wonlex', 'CT24 4G', 189, 159);
        $this->createProduct('ct23-4g', 'CT23 4G', 'Wonlex', 'CT23 4G', 199);

        $context = $this->makeOrchestrator()->search($this->catalogIntent('ფასდაკლებით რომელი 2G მოდელები გაქვთ?', 'price_query', 'discounted_catalog', ['2G', 'ფასდაკლება']));

        $this->assertSame(['q21-2g', 'q19-2g'], $context->products()->pluck('slug')->all());
        $this->assertCount(2, $context->products());
    }

    private function makeOrchestrator(?RagContextBuilder $ragBuilder = null): SmartSearchOrchestrator
    {
        $ragBuilder ??= tap(Mockery::mock(RagContextBuilder::class), function ($builder): void {
            $builder->shouldReceive('build')
                ->andReturn('');
        });

        $policy = Mockery::mock(UnifiedAiPolicyService::class);
        $policy->shouldReceive('normalizeIncomingMessage')
            ->andReturnUsing(fn (string $message): string => $message);

        return new SmartSearchOrchestrator($ragBuilder, $policy);
    }

    private function catalogIntent(string $query, string $intent, ?string $category, array $searchKeywords): IntentResult
    {
        return new IntentResult(
            $query,
            $intent,
            null,
            null,
            null,
            null,
            $category,
            true,
            $searchKeywords,
            false,
            0.97,
            15,
            false
        );
    }

    private function makeIntent(?string $slugHint = 'mytechnic-ultra'): IntentResult
    {
        return new IntentResult(
            'MyTechnic Ultra რა ღირს?',
            'price_query',
            'MyTechnic',
            'Ultra',
            $slugHint,
            null,
            null,
            true,
            ['MyTechnic', 'Ultra'],
            false,
            0.97,
            15,
            false
        );
    }

    private function createProduct(string $slug, string $nameKa, string $brand, string $model, float $price = 299, ?float $salePrice = null): Product
    {
        return Product::query()->create([
            'name_en' => $nameKa,
            'name_ka' => $nameKa,
            'slug' => $slug,
            'brand' => $brand,
            'model' => $model,
            'price' => $price,
            'sale_price' => $salePrice,
            'currency' => 'GEL',
            'is_active' => true,
            'featured' => false,
        ]);
    }
}
