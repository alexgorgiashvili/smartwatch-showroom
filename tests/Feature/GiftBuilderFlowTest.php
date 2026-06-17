<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GiftBuilderFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_builder_page_loads_with_config_and_pdp_preselect(): void
    {
        $main = $this->giftVariant('Kids GPS Watch', 'main', 149);

        $response = $this->get(route('gift-builder.show', [
            'product' => $main->product->slug,
            'variant_id' => $main->id,
        ]));

        $response->assertOk();
        $response->assertSee('gift-builder-app');
        $response->assertSee((string) $main->id);
    }

    public function test_add_to_cart_creates_gift_group_without_touching_standard_cart(): void
    {
        $standard = $this->standardVariant('Standard Cart Watch', 99);
        $main = $this->giftVariant('Gift Watch', 'main', 149, compatibilityTags: ['starter']);
        $addon = $this->giftVariant('Gift Strap', 'addon', 25);

        $response = $this
            ->withSession([
                'cart' => [
                    $standard->id => [
                        'variant_id' => $standard->id,
                        'quantity' => 2,
                    ],
                ],
            ])
            ->postJson(route('gift-builder.add-to-cart'), [
                'recipient_type' => 'child_8_12',
                'occasion' => 'birthday',
                'budget_band' => '150_250',
                'packaging_slug' => 'standard',
                'message' => '<b>Congrats</b>',
                'items' => [
                    ['variant_id' => $main->id, 'quantity' => 1, 'role' => 'main'],
                    ['variant_id' => $addon->id, 'quantity' => 1, 'role' => 'addon'],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('cart_count', 4);

        $this->assertSame(2, session("cart.{$standard->id}.quantity"));
        $groups = session('gift_cart_groups');
        $this->assertIsArray($groups);
        $this->assertCount(1, $groups);
        $group = array_values($groups)[0];
        $this->assertSame('Congrats', $group['message']);
        $this->assertCount(2, $group['items']);
    }

    private function standardVariant(string $name, float $price): ProductVariant
    {
        $product = Product::query()->create([
            'name_en' => $name,
            'name_ka' => $name,
            'slug' => str()->slug($name . '-' . uniqid()),
            'price' => $price,
            'currency' => 'GEL',
            'is_active' => true,
            'featured' => false,
            'fulfillment_mode' => 'local_stock',
        ]);

        return ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Default',
            'quantity' => 5,
            'low_stock_threshold' => 1,
        ]);
    }

    private function giftVariant(
        string $name,
        string $role,
        float $price,
        array $compatibilityTags = []
    ): ProductVariant {
        $product = Product::query()->create([
            'name_en' => $name,
            'name_ka' => $name,
            'slug' => str()->slug($name . '-' . uniqid()),
            'price' => $price,
            'currency' => 'GEL',
            'is_active' => true,
            'featured' => false,
            'fulfillment_mode' => 'local_stock',
            'gift_builder_enabled' => true,
            'gift_builder_role' => $role,
            'gift_budget_band' => 'all',
            'gift_compatibility_tags' => $compatibilityTags,
            'gift_capacity_units' => 1,
            'gift_sort_order' => 0,
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Default',
            'quantity' => 5,
            'low_stock_threshold' => 1,
        ]);

        $variant->setRelation('product', $product);

        return $variant;
    }
}
