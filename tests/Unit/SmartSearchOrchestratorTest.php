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
        $this->createProduct('wonlex-kt34', 'Wonlex KT34', 'Wonlex', 'KT34');
        $this->createProduct('wonlex-ct27', 'Wonlex CT27', 'Wonlex', 'CT27');

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

    private function createProduct(string $slug, string $nameKa, string $brand, string $model): Product
    {
        return Product::query()->create([
            'name_en' => $nameKa,
            'name_ka' => $nameKa,
            'slug' => $slug,
            'brand' => $brand,
            'model' => $model,
            'price' => 299,
            'currency' => 'GEL',
            'is_active' => true,
        ]);
    }
}
