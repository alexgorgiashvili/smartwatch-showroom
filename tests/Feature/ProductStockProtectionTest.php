<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductStockProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_out_of_stock_variant_cannot_be_added_to_cart(): void
    {
        $product = $this->createProduct('stock-protection-one');
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Black',
            'color_name' => 'შავი',
            'color_hex' => '#000000',
            'quantity' => 0,
            'low_stock_threshold' => 1,
        ]);

        $this->postJson(route('cart.add'), [
            'variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertUnprocessable();

        $this->assertSame([], session('cart', []));
    }

    public function test_checkout_rejects_cart_when_stock_has_become_insufficient(): void
    {
        $product = $this->createProduct('stock-protection-two');
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Black',
            'quantity' => 0,
            'low_stock_threshold' => 1,
        ]);

        $this->withSession([
            'cart' => [
                $variant->id => ['variant_id' => $variant->id, 'quantity' => 1],
            ],
        ])->get(route('checkout.index'))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('cart_error');
    }

    private function createProduct(string $slug): Product
    {
        return Product::query()->create([
            'name_en' => 'Stock protection test',
            'name_ka' => 'მარაგის ტესტი',
            'slug' => $slug,
            'price' => 100,
            'currency' => 'GEL',
            'sim_support' => false,
            'gps_features' => false,
            'is_active' => true,
            'featured' => false,
        ]);
    }
}
