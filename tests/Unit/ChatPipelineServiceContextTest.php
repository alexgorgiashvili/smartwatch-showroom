<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\Chatbot\ChatbotProductSelectionService;
use App\Services\Chatbot\IntentResult;
use App\Services\Chatbot\PipelineResult;
use App\Services\Chatbot\ProductContextService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ChatPipelineServiceContextTest extends TestCase
{
    public function testBudgetPromptContextPrefersWithinBudgetProductsFirst(): void
    {
        $service = new ProductContextService();
        $intent = $this->intent('recommendation');
        $products = new Collection([$this->pricedProduct(1, 'ct27', 20.50), $this->pricedProduct(2, 'ct23', 16.50)]);

        $resolved = $service->selectForPrompt($products, $intent, ['budget_max_gel' => 20]);

        $this->assertSame('ct23', $resolved->first()->slug);
    }

    public function testComparisonPromptContextIsLimitedToFourProducts(): void
    {
        $service = new ProductContextService();
        $intent = $this->intent('comparison');
        $products = new Collection([
            $this->pricedProduct(1, 'p1', 10),
            $this->pricedProduct(2, 'p2', 12),
            $this->pricedProduct(3, 'p3', 14),
            $this->pricedProduct(4, 'p4', 16),
            $this->pricedProduct(5, 'p5', 18),
        ]);

        $resolved = $service->selectForPrompt($products, $intent);

        $this->assertCount(4, $resolved);
        $this->assertSame(['p1', 'p2', 'p3', 'p4'], $resolved->pluck('slug')->all());
    }

    public function testRecommendationIntentDoesNotReturnWidgetCards(): void
    {
        $service = new ChatbotProductSelectionService();

        $selected = $service->selectWidgetProductsForResponse([
            $this->widgetProduct('Wonlex CT23', 'wonlex-ct23', 199),
            $this->widgetProduct('Wonlex KT34', 'wonlex-kt34', 219),
        ], $this->pipelineResult(
            'თქვენი ბიუჯეტისთვის რამდენიმე კარგი ვარიანტი გვაქვს.',
            $this->intent('recommendation')
        ));

        $this->assertSame([], $selected);
    }

    public function testAdultCatalogFallbackSuppressesWidgetProducts(): void
    {
        $service = new ChatbotProductSelectionService();

        $result = $this->pipelineResult(
            'ჩვენი კატალოგი ამ ეტაპზე ძირითადად საბავშვო სმარტსაათებზეა ფოკუსირებული. ზრდასრულის სმარტსაათები არ გვაქვს.',
            new IntentResult(
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
            )
        );

        $this->assertFalse($service->shouldIncludeWidgetProducts($result));
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

    private function widgetProduct(string $name, string $slug, float $price): array
    {
        return [
            'name' => $name,
            'slug' => $slug,
            'price' => $price,
            'sale_price' => null,
        ];
    }

    private function pipelineResult(string $response, IntentResult $intent): PipelineResult
    {
        return new PipelineResult(
            $response,
            1,
            '',
            $intent,
            [],
            true,
            null,
            true,
            [],
            true,
            10
        );
    }
}
