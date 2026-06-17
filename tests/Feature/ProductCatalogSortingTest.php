<?php

namespace Tests\Feature;

use App\Models\Product;
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
