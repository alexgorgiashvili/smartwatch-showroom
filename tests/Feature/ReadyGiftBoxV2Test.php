<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReadyGiftBox;
use App\Services\Cart\CartSnapshotService;
use App\Services\GiftBuilder\GiftBuilderPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class ReadyGiftBoxV2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('gift_builder.enabled', true);
        config()->set('gift_builder.public_enabled', true);
        config()->set('gift_builder.packaging.standard.price', 0);
        config()->set('gift_builder.packaging.standard.capacity_units', 4);
        config()->set('gift_builder.packaging.premium.price', 12);
        config()->set('gift_builder.packaging.premium.capacity_units', 6);
    }

    public function test_public_access_fails_closed_when_public_flag_is_missing(): void
    {
        config()->set('gift_builder.public_enabled', null);
        config()->set('gift_builder.preview_key', 'private-key');

        $this->get(route('gift-builder.boxes'))->assertNotFound();
        $this->postJson(route('gift-builder.price', ['preview' => 'private-key']), [])->assertNotFound();
        $this->assertFalse((bool) session('gift_builder_preview_access'));

        $this->get(route('gift-builder.show', [
            'preview' => 'private-key',
            'box' => 'sample-box',
            'utm_source' => 'meta',
        ]))
            ->assertRedirect(route('gift-builder.show', ['box' => 'sample-box']))
            ->assertSessionHas('gift_builder_preview_access', true)
            ->assertSessionHas('gift_campaign.utm_source', 'meta');

        $this->get(route('gift-builder.show', ['box' => 'sample-box']))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_options_exposes_live_variants_and_unavailable_boxes_return_conflict(): void
    {
        $main = $this->giftVariant('Options Watch', 'main', 120);
        $secondColor = ProductVariant::query()->create([
            'product_id' => $main->product_id,
            'name' => 'Purple',
            'color_name' => 'Purple',
            'color_hex' => '#6D28D9',
            'quantity' => 2,
            'low_stock_threshold' => 1,
        ]);
        $box = $this->readyBox('options-box', $main, [], discountType: 'percent', discountValue: 10);

        $this->getJson(route('gift-boxes.options', $box))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('box.slug', 'options-box')
            ->assertJsonPath('box.items.0.item_id', $box->items->first()->id)
            ->assertJsonCount(2, 'box.items.0.variants')
            ->assertJsonPath('box.items.0.variants.1.id', $secondColor->id);

        $main->update(['quantity' => 0]);
        $secondColor->update(['quantity' => 0]);

        $this->getJson(route('gift-boxes.options', $box))
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_builder_catalog_hides_non_positive_prices_and_ignores_their_preselection(): void
    {
        $variant = $this->giftVariant('Zero Price Watch', 'main', 0);

        $response = $this->get(route('gift-builder.show', [
            'product' => $variant->product->slug,
            'variant_id' => $variant->id,
        ]));

        $response->assertOk();
        $config = $response->viewData('builderConfig');

        $this->assertNotContains(
            (int) $variant->product_id,
            collect($config['products'])->pluck('id')->all(),
        );
        $this->assertNull($config['initial']['selected_variant_id']);
    }

    public function test_inactive_addon_only_product_is_available_in_builder_and_ready_boxes(): void
    {
        $main = $this->giftVariant('Active Builder Watch', 'main', 120);
        $addon = $this->giftVariant('Hidden Storefront Add-on', 'addon', 35);
        $addon->product()->update(['is_active' => false]);
        $box = $this->readyBox('builder-only-addon-box', $main, [$addon]);

        $builder = $this->get(route('gift-builder.show'))->assertOk();
        $products = collect($builder->viewData('builderConfig')['products']);
        $this->assertContains((int) $addon->product_id, $products->pluck('id')->all());

        $boxes = $this->get(route('gift-builder.boxes'))->assertOk();
        $this->assertContains('builder-only-addon-box', collect($boxes->viewData('readyBoxes'))->pluck('slug')->all());

        $this->getJson(route('gift-boxes.options', $box))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_quick_buy_requires_every_configured_item_id_and_rejects_cross_product_variant(): void
    {
        $main = $this->giftVariant('Strict Watch', 'main', 100);
        $addon = $this->giftVariant('Strict Add-on', 'addon', 30);
        $other = $this->giftVariant('Other Add-on', 'addon', 25);
        $box = $this->readyBox('strict-box', $main, [$addon]);
        $items = $box->items->keyBy('role');

        $this->postJson(route('gift-boxes.add-to-cart', $box), [
            'items' => [
                ['item_id' => $items['main']->id, 'variant_id' => $main->id],
            ],
        ])->assertUnprocessable();

        $this->postJson(route('gift-boxes.add-to-cart', $box), [
            'items' => [
                ['item_id' => $items['main']->id, 'variant_id' => $other->id],
                ['item_id' => $items['addon']->id, 'variant_id' => $addon->id],
            ],
        ])->assertUnprocessable();
    }

    public function test_quick_buy_reprices_server_side_and_keeps_discount_for_color_change(): void
    {
        $main = $this->giftVariant('Color Watch', 'main', 100);
        $purple = ProductVariant::query()->create([
            'product_id' => $main->product_id,
            'name' => 'Purple',
            'quantity' => 3,
            'low_stock_threshold' => 1,
        ]);
        $addon = $this->giftVariant('Color Add-on', 'addon', 30);
        $box = $this->readyBox('color-box', $main, [$addon], discountType: 'fixed', discountValue: 15);
        $items = $box->items->keyBy('role');

        $this->get(route('gift-builder.boxes', [
            'utm_source' => 'meta',
            'utm_campaign' => 'gift_launch',
        ]))->assertOk();

        $response = $this->postJson(route('gift-boxes.add-to-cart', $box), [
            'price' => 1,
            'discount_amount' => 999,
            'packaging_slug' => 'premium',
            'message' => '<b>Hello</b>',
            'items' => [
                ['item_id' => $items['main']->id, 'variant_id' => $purple->id],
                ['item_id' => $items['addon']->id, 'variant_id' => $addon->id],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('gift_box.items_subtotal', 130)
            ->assertJsonPath('gift_box.packaging_slug', 'standard')
            ->assertJsonPath('gift_box.discount_amount', 15)
            ->assertJsonPath('gift_box.total', 115)
            ->assertJsonPath('gift_box.discount_retained', true)
            ->assertJsonPath('gift_box.message', 'Hello');

        $group = collect(session('gift_cart_groups'))->first();
        $this->assertSame('ready_gift_box', $group['discount_source']);
        $this->assertSame('color-box', $group['ready_box']['slug']);
        $this->assertSame('meta', $group['campaign']['utm_source']);
        $this->assertSame('gift_launch', $group['campaign']['utm_campaign']);
    }

    public function test_customizing_product_or_packaging_removes_ready_box_discount(): void
    {
        $main = $this->giftVariant('Preset Watch', 'main', 100);
        $addon = $this->giftVariant('Preset Add-on', 'addon', 30);
        $replacement = $this->giftVariant('Replacement Add-on', 'addon', 20);
        $box = $this->readyBox('preset-box', $main, [$addon], discountType: 'percent', discountValue: 10);

        $pricing = app(GiftBuilderPricingService::class);
        $base = [
            'ready_box_slug' => $box->slug,
            'packaging_slug' => 'standard',
            'items' => [
                ['variant_id' => $main->id, 'role' => 'main'],
                ['variant_id' => $addon->id, 'role' => 'addon'],
            ],
        ];

        $exact = $pricing->price($base);
        $this->assertSame(13.0, $exact['discount_amount']);
        $this->assertTrue($exact['discount_retained']);

        $changedProduct = $pricing->price(array_replace($base, [
            'items' => [
                ['variant_id' => $main->id, 'role' => 'main'],
                ['variant_id' => $replacement->id, 'role' => 'addon'],
            ],
        ]));
        $this->assertSame(0.0, $changedProduct['discount_amount']);
        $this->assertTrue($changedProduct['preset_discount_removed']);
        $this->assertSame('preset_discount_removed', collect($changedProduct['warnings'])->last()['code']);

        $changedPackaging = $pricing->price(array_replace($base, ['packaging_slug' => 'premium']));
        $this->assertSame(0.0, $changedPackaging['discount_amount']);
        $this->assertTrue($changedPackaging['preset_discount_removed']);
    }

    public function test_legacy_group_discount_survives_two_cart_snapshots(): void
    {
        $main = $this->giftVariant('Legacy Watch', 'main', 100);
        $groupId = '11111111-1111-4111-8111-111111111111';
        $legacyGroup = [
            'recipient_type' => 'other',
            'occasion' => 'just_because',
            'budget_band' => 'all',
            'packaging_slug' => 'standard',
            'packaging_amount' => 0,
            'discount_amount' => 8,
            'message' => '',
            'items' => [[
                'variant_id' => $main->id,
                'quantity' => 1,
                'role' => 'main',
                'sort_order' => 1,
            ]],
        ];

        $this->withSession(['gift_cart_groups' => [$groupId => $legacyGroup]])
            ->get(route('cart.index'))
            ->assertOk()
            ->assertSessionHas("gift_cart_groups.{$groupId}.discount_amount", 8.0)
            ->assertSessionHas("gift_cart_groups.{$groupId}.discount_source", 'legacy');

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSessionHas("gift_cart_groups.{$groupId}.discount_amount", 8.0)
            ->assertSessionHas("gift_cart_groups.{$groupId}.discount_source", 'legacy');
    }

    public function test_checkout_stock_validation_aggregates_the_same_variant_across_gift_groups(): void
    {
        $main = $this->giftVariant('Shared Stock Watch', 'main', 100, quantity: 1);
        $group = [
            'recipient_type' => 'other',
            'occasion' => 'just_because',
            'budget_band' => 'all',
            'packaging_slug' => 'standard',
            'message' => '',
            'items' => [[
                'variant_id' => $main->id,
                'quantity' => 1,
                'role' => 'main',
                'sort_order' => 1,
            ]],
        ];

        $request = Request::create('/checkout', 'POST');
        $session = $this->app['session']->driver();
        $session->start();
        $request->setLaravelSession($session);
        $request->session()->put('gift_cart_groups', [
            'first-box' => $group,
            'second-box' => $group,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient stock for the combined cart quantity.');

        app(CartSnapshotService::class)->build($request, [
            'normalize_session' => false,
            'enforce_stock' => true,
        ]);
    }

    public function test_existing_gift_group_can_be_removed_after_the_feature_is_disabled(): void
    {
        config()->set('gift_builder.enabled', false);
        config()->set('gift_builder.public_enabled', false);
        $groupId = 'disabled-feature-group';

        $this->withSession([
            'gift_cart_groups' => [$groupId => ['items' => []]],
        ])->delete(route('cart.gift-groups.remove', $groupId))
            ->assertRedirect()
            ->assertSessionMissing("gift_cart_groups.{$groupId}");
    }

    public function test_quick_buy_checkout_snapshots_discount_and_survives_box_deletion(): void
    {
        Event::fake();
        $city = City::query()->create(['name' => 'თბილისი']);
        $main = $this->giftVariant('Snapshot Watch', 'main', 100);
        $addon = $this->giftVariant('Snapshot Add-on', 'addon', 30);
        $box = $this->readyBox('snapshot-box', $main, [$addon], discountType: 'fixed', discountValue: 15);
        $items = $box->items->keyBy('role');

        $this->get(route('gift-builder.boxes', [
            'utm_source' => 'meta',
            'utm_campaign' => 'snapshot-test',
        ]))->assertOk();

        $this->postJson(route('gift-boxes.add-to-cart', $box), [
            'items' => [
                ['item_id' => $items['main']->id, 'variant_id' => $main->id],
                ['item_id' => $items['addon']->id, 'variant_id' => $addon->id],
            ],
        ])->assertOk();

        $this->postJson(route('payment.validate'), [
            'customer_name' => 'Gift Snapshot',
            'customer_phone' => '995555123456',
            'personal_number' => '01001010101',
            'city_id' => $city->id,
            'exact_address' => 'Test address',
            'payment_type' => 2,
        ])->assertOk();

        $order = Order::query()->with('adjustments')->latest('id')->firstOrFail();
        $snapshot = collect($order->gift_groups)->first();
        $adjustment = $order->adjustments->firstWhere('type', 'gift_discount');

        $this->assertTrue($order->is_gift_order);
        $this->assertSame('115.00', $order->total_amount);
        $this->assertSame('15.00', $order->gift_discount_amount);
        $this->assertSame('snapshot-box', $snapshot['ready_box']['slug']);
        $this->assertSame('ready_gift_box', $snapshot['discount_source']);
        $this->assertSame('meta', $snapshot['campaign']['utm_source']);
        $this->assertNotNull($adjustment);
        $this->assertSame('-15.00', $adjustment->amount);
        $this->assertSame('snapshot-box', $adjustment->metadata['ready_box_slug']);

        $box->forceDelete();

        $historicalSnapshot = collect($order->fresh()->gift_groups)->first();
        $this->assertSame('snapshot-box', $historicalSnapshot['ready_box']['slug']);
        $this->assertSame('Snapshot Box', $historicalSnapshot['ready_box']['title']);
    }

    private function giftVariant(string $name, string $role, float $price, int $quantity = 3): ProductVariant
    {
        $product = Product::query()->create([
            'name_en' => $name,
            'name_ka' => $name,
            'slug' => str()->slug($name.'-'.uniqid()),
            'price' => $price,
            'currency' => 'GEL',
            'is_active' => true,
            'featured' => false,
            'fulfillment_mode' => 'local_stock',
            'gift_builder_enabled' => true,
            'gift_builder_role' => $role,
            'gift_budget_band' => 'all',
            'gift_compatibility_tags' => [],
            'gift_capacity_units' => 1,
            'gift_sort_order' => 0,
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Default',
            'quantity' => $quantity,
            'low_stock_threshold' => 1,
        ]);
        $variant->setRelation('product', $product);

        return $variant;
    }

    private function readyBox(
        string $slug,
        ProductVariant $main,
        array $addons,
        string $discountType = 'none',
        float $discountValue = 0,
    ): ReadyGiftBox {
        $box = ReadyGiftBox::query()->create([
            'slug' => $slug,
            'title_ka' => str($slug)->headline()->toString(),
            'title_en' => str($slug)->headline()->toString(),
            'theme_key' => 'grape',
            'packaging_slug' => 'standard',
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'is_active' => true,
            'is_featured' => true,
        ]);
        $box->items()->create([
            'product_id' => $main->product_id,
            'default_variant_id' => $main->id,
            'role' => 'main',
            'sort_order' => 0,
        ]);
        foreach ($addons as $index => $addon) {
            $box->items()->create([
                'product_id' => $addon->product_id,
                'default_variant_id' => $addon->id,
                'role' => 'addon',
                'sort_order' => $index + 1,
            ]);
        }

        return $box->fresh(['items.product.variants', 'items.defaultVariant']);
    }
}
