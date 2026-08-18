<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReadyGiftBox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GiftBuilderFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('gift_builder.enabled', true);
        config()->set('gift_builder.public_enabled', true);
    }

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

    public function test_private_preview_requires_key_then_persists_access_in_session(): void
    {
        config()->set('gift_builder.public_enabled', false);
        config()->set('gift_builder.preview_key', 'private-preview-key');

        $this->get(route('gift-builder.show'))->assertNotFound();

        $this->get(route('gift-builder.show', ['preview' => 'wrong-key']))->assertNotFound();

        $this->get(route('gift-builder.show', ['preview' => 'private-preview-key']))
            ->assertRedirect(route('gift-builder.show'))
            ->assertSessionHas('gift_builder_preview_access', true);

        $this->get(route('gift-builder.show'))->assertOk();
        $this->get(route('gift-builder.boxes'))->assertOk();
        $this->getJson(route('gift-builder.products'))->assertOk();
    }

    public function test_ready_box_page_links_to_a_preselected_builder(): void
    {
        $main = $this->giftVariant('Ready Box Watch', 'main', 79);
        $addon = $this->giftVariant('Ready Box Add-on', 'addon', 29);

        $box = ReadyGiftBox::query()->create([
            'slug' => 'ready-test',
            'title_ka' => 'მზა სატესტო ყუთი',
            'title_en' => 'Ready Test Box',
            'short_description_ka' => 'მზა კომბინაცია.',
            'short_description_en' => 'Ready combination.',
            'theme_key' => 'grape',
            'packaging_slug' => 'standard',
            'discount_type' => 'none',
            'discount_value' => 0,
            'is_active' => true,
        ]);
        $box->items()->createMany([
            ['product_id' => $main->product_id, 'default_variant_id' => $main->id, 'role' => 'main', 'sort_order' => 0],
            ['product_id' => $addon->product_id, 'default_variant_id' => $addon->id, 'role' => 'addon', 'sort_order' => 1],
        ]);

        $this->get(route('gift-builder.boxes'))
            ->assertOk()
            ->assertSee('მზა სატესტო ყუთი')
            ->assertSee(route('gift-builder.show', ['box' => 'ready-test']), false);

        $this->get(route('gift-builder.show', ['box' => 'ready-test']))
            ->assertOk()
            ->assertSee((string) $main->id)
            ->assertSee((string) $addon->id);
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
                'budget_band' => 'under_250',
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
