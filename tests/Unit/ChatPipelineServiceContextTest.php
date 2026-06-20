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

    public function testTwoGCatalogPromptContextIncludesAllMatchingModels(): void
    {
        $service = new ProductContextService();
        $intent = $this->intent('recommendation', 'რომელი 2G მოდელები გაქვთ?', '2g_catalog', ['2G']);
        $products = new Collection([
            $this->catalogProduct(1, 'q21-2g', 'Q21 2G', 79),
            $this->catalogProduct(2, 'q19-2g', 'Q19 2G', 79, 59),
            $this->catalogProduct(3, 'q12-2g', 'Q12 2G', 69),
            $this->catalogProduct(4, 'q15-2g', 'Q15 2G', 89),
            $this->catalogProduct(5, 'ct24-4g', 'CT24 4G', 189, 159),
        ]);

        $resolved = $service->selectForPrompt($products, $intent);

        $this->assertCount(4, $resolved);
        $this->assertSame(['q21-2g', 'q19-2g', 'q12-2g', 'q15-2g'], $resolved->pluck('slug')->all());
    }

    public function testFourGCatalogPromptContextIncludesMoreThanFourModels(): void
    {
        $service = new ProductContextService();
        $intent = $this->intent('recommendation', 'რომელი 4G მოდელები გაქვთ?', '4g_catalog', ['4G']);
        $products = new Collection([
            $this->catalogProduct(1, 'q21-2g', 'Q21 2G', 79),
            $this->catalogProduct(2, 'ct24-4g', 'CT24 4G', 189, 159),
            $this->catalogProduct(3, 'ct23-4g', 'CT23 4G', 199),
            $this->catalogProduct(4, 'ct27-4g', 'CT27 4G', 209),
            $this->catalogProduct(5, 'kt34-4g', 'KT34 4G', 219),
            $this->catalogProduct(6, 't53-4g', 'T53 4G', 229),
        ]);

        $resolved = $service->selectForPrompt($products, $intent);

        $this->assertCount(5, $resolved);
        $this->assertSame(['ct24-4g', 'ct23-4g', 'ct27-4g', 'kt34-4g', 't53-4g'], $resolved->pluck('slug')->all());
    }

    public function testDiscountedTwoGCatalogPromptContextKeepsOnlyDiscountedModels(): void
    {
        $service = new ProductContextService();
        $intent = $this->intent('price_query', 'ფასდაკლებით რომელი 2G მოდელები გაქვთ?', 'discounted_catalog', ['2G', 'ფასდაკლება']);
        $products = new Collection([
            $this->catalogProduct(1, 'q21-2g', 'Q21 2G', 79, 69),
            $this->catalogProduct(2, 'q19-2g', 'Q19 2G', 79, 59),
            $this->catalogProduct(3, 'q12-2g', 'Q12 2G', 69),
            $this->catalogProduct(4, 'ct24-4g', 'CT24 4G', 189, 159),
            $this->catalogProduct(5, 'ct23-4g', 'CT23 4G', 199),
        ]);

        $resolved = $service->selectForPrompt($products, $intent);

        $this->assertCount(2, $resolved);
        $this->assertSame(['q21-2g', 'q19-2g'], $resolved->pluck('slug')->all());
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

    private function intent(string $intent, string $query = 'query', ?string $category = null, array $searchKeywords = []): IntentResult
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
            0.9,
            10,
            false
        );
    }

    private function catalogProduct(int $id, string $slug, string $name, float $price, ?float $salePrice = null): Product
    {
        $product = new Product();
        $product->id = $id;
        $product->slug = $slug;
        $product->name_en = $name;
        $product->name_ka = $name;
        $product->brand = 'Wonlex';
        $product->model = $slug;
        $product->setAttribute('price', $price);
        $product->setAttribute('sale_price', $salePrice);

        return $product;
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
