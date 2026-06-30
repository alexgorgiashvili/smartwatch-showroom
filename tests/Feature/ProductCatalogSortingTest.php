<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCatalogSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_can_be_sorted_by_effective_price_ascending(): void
    {
        Product::query()->create($this->productPayload('alpha-watch', 'Alpha Watch', 120, null));
        Product::query()->create($this->productPayload('beta-watch', 'Beta Watch', 150, 90));
        Product::query()->create($this->productPayload('gamma-watch', 'Gamma Watch', 100, null));

        $response = $this->get(route('products.index', ['sort' => 'price_low']));

        $response->assertOk();
        $response->assertSeeInOrder([
            'Beta Watch',
            'Gamma Watch',
            'Alpha Watch',
        ]);
    }

    public function test_products_can_be_sorted_by_effective_price_descending(): void
    {
        Product::query()->create($this->productPayload('alpha-watch', 'Alpha Watch', 120, null));
        Product::query()->create($this->productPayload('beta-watch', 'Beta Watch', 150, 90));
        Product::query()->create($this->productPayload('gamma-watch', 'Gamma Watch', 100, null));

        $response = $this->get(route('products.index', ['sort' => 'price_high']));

        $response->assertOk();
        $response->assertSeeInOrder([
            'Alpha Watch',
            'Gamma Watch',
            'Beta Watch',
        ]);
    }

    public function test_catalog_shows_only_selected_variants_as_separate_cards(): void
    {
        $singleProduct = Product::query()->create($this->productPayload('single-watch', 'Single Watch', 100, null));
        ProductVariant::query()->create([
            'product_id' => $singleProduct->id,
            'name' => 'Default',
            'color_name' => null,
            'color_hex' => null,
            'is_listed_separately' => false,
            'quantity' => 5,
            'low_stock_threshold' => 1,
        ]);

        $multiVariantProduct = Product::query()->create($this->productPayload('duo-watch', 'Duo Watch', 180, null));
        ProductVariant::query()->create([
            'product_id' => $multiVariantProduct->id,
            'name' => 'Red',
            'color_name' => 'Red',
            'color_hex' => '#FF0000',
            'is_listed_separately' => true,
            'quantity' => 5,
            'low_stock_threshold' => 1,
        ]);
        ProductVariant::query()->create([
            'product_id' => $multiVariantProduct->id,
            'name' => 'Blue',
            'color_name' => 'Blue',
            'color_hex' => '#0000FF',
            'is_listed_separately' => false,
            'quantity' => 5,
            'low_stock_threshold' => 1,
        ]);

        $response = $this->get(route('products.index'));

        $response->assertOk();
        $response->assertSee('Single Watch');
        $response->assertSee('Duo Watch - Red');
        $response->assertDontSee('Duo Watch - Blue');
    }

    private function productPayload(string $slug, string $name, float $price, ?float $salePrice): array
    {
        return [
            'name_en' => $name,
            'name_ka' => $name,
            'slug' => $slug,
            'price' => $price,
            'sale_price' => $salePrice,
            'currency' => 'GEL',
            'sim_support' => true,
            'gps_features' => true,
            'is_active' => true,
            'featured' => false,
        ];
    }
}
