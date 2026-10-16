<?php

namespace Tests\Feature;

use App\Events\OrderCreated;
use App\Models\City;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReadyGiftBox;
use App\Models\User;
use App\Services\BogPayService;
use App\Services\Cart\CartSnapshotService;
use App\Services\GiftBuilder\GiftBuilderPricingService;
use App\Services\GiftBuilder\GiftEconomicsService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class GiftBuilderV2Test extends TestCase
{
    public function createApplication(): Application
    {
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::getDriverName());
        $this->assertSame(':memory:', DB::getDatabaseName());
        // Only the commerce prerequisites; unrelated production migrations are
        // deliberately excluded from this disposable SQLite integration suite.
        foreach ([
            '2014_10_12_000000_create_users_table.php',
            '2026_02_13_000000_create_products_table.php',
            '2026_02_13_000001_create_product_images_table.php',
            '2026_02_13_010000_add_is_admin_to_users_table.php',
            '2026_02_14_000003_add_sale_price_to_products_table.php',
            '2026_02_19_000001_create_product_variants_table.php',
            '2026_02_19_100001_create_orders_table.php',
            '2026_02_19_100002_create_order_items_table.php',
            '2026_02_23_001000_add_color_fields_to_product_variants_table.php',
            '2026_02_23_002000_add_payment_fields_to_orders_table.php',
            '2026_02_23_150000_create_cities_table.php',
            '2026_02_23_160500_add_checkout_identity_and_city_fields_to_orders_table.php',
            '2026_02_27_000100_add_external_source_fields_to_products_table.php',
            '2026_06_07_060000_add_bridge_fulfillment_fields.php',
            '2026_06_11_000000_add_gift_builder_fields_to_products_table.php',
            '2026_06_11_000001_add_gift_metadata_to_orders_and_order_items.php',
            '2026_06_11_000002_create_order_adjustments_table.php',
            '2026_06_26_000200_create_product_variant_images_table.php',
            '2026_08_18_000100_create_ready_gift_boxes_tables.php',
            '2026_10_16_000300_add_gift_box_pricing_and_settings.php',
            '2026_10_16_000400_add_private_gift_cost_snapshot_to_orders.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        config()->set('gift_builder.pricing_v2_enabled', true);
        \Illuminate\Support\Facades\Schema::create('conversations', function ($table): void {
            $table->id();
            $table->unsignedInteger('unread_count')->default(0);
        });
        config()->set('gift_builder.enabled', true);
        config()->set('gift_builder.public_enabled', true);
        Http::preventStrayRequests();
        Event::fake([OrderCreated::class]);
    }

    public function test_inactive_components_use_box_prices_without_main_roles_or_discounts(): void
    {
        $a = $this->variant(17, 39);
        $b = $this->variant(7, 19);
        $quote = app(GiftBuilderPricingService::class)->price($this->payload([$a, $b]));
        $this->assertSame(29.0, $quote['total']);
        $this->assertEquals(0, $quote['discount_amount']);
        $this->assertSame([17.0, 7.0], array_column($quote['items'], 'unit_price'));
        $this->assertArrayNotHasKey('gift_landed_cost', $a->product->toArray());
        $this->assertArrayNotHasKey('gift_cost_status', $a->product->toArray());
        $this->assertSame(39.0, (float) $a->product->fresh()->price);
    }

    public function test_a_second_variant_of_the_same_product_is_rejected(): void
    {
        $a = $this->variant(17, 39);
        $second = $a->product->variants()->create(['name' => 'Other', 'quantity' => 5]);
        $this->expectException(ValidationException::class);
        app(GiftBuilderPricingService::class)->price($this->payload([$a, $second]));
    }

    public function test_delivery_is_charged_once_and_mixed_retail_crosses_the_threshold(): void
    {
        $a = $this->variant(17, 39);
        $b = $this->variant(7, 19);
        $retail = $this->variant(1, 42, active: true);
        $groups = ['one' => $this->payload([$a, $b]), 'two' => $this->payload([$a, $b])];
        $snapshot = $this->snapshot($groups);
        $this->assertSame(58.0, $snapshot['summary']['total']);
        $this->assertEquals(7.0, $snapshot['summary']['delivery_total']);
        $this->assertSame(65.0, $snapshot['summary']['payable_total']);
        $mixed = $this->snapshot($groups, [$retail->id => ['quantity' => 1]]);
        $this->assertSame(100.0, $mixed['summary']['total']);
        $this->assertEquals(0.0, $mixed['summary']['delivery_total']);
        $this->assertNotSame($snapshot['summary']['quote_version'], $mixed['summary']['quote_version']);
        $retailOnly = $this->snapshot([], [$retail->id => ['quantity' => 1]]);
        $this->assertSame(42.0, $retailOnly['summary']['payable_total']);
        $this->assertEquals(0.0, $retailOnly['summary']['delivery_total']);
    }

    public function test_combined_retail_and_gift_demand_cannot_exceed_variant_stock(): void
    {
        $a = $this->variant(17, 39, active: true);
        $a->update(['quantity' => 1]);
        $b = $this->variant(7, 19);
        $this->expectException(\RuntimeException::class);
        $this->snapshot(['one' => $this->payload([$a, $b])], [$a->id => ['quantity' => 1]], true);
    }

    public function test_two_product_admin_form_accepts_empty_optional_rows_and_stays_private(): void
    {
        config()->set('gift_builder.pricing_v2_enabled', false);
        $a = $this->variant(17, 39);
        $b = $this->variant(7, 19);
        $admin = User::query()->create(['name' => 'Test', 'email' => 'gift@example.test', 'password' => 'test-only', 'is_admin' => true]);
        $this->actingAs($admin)->post(route('admin.gift-boxes.store'), [
            'slug' => 'test-v2-draft', 'title_ka' => 'Test draft', 'theme_key' => 'grape',
            'packaging_slug' => 'standard', 'is_active' => 1,
            'items' => [
                ['product_id' => $a->product_id, 'default_variant_id' => $a->id],
                ['product_id' => $b->product_id, 'default_variant_id' => $b->id],
                ['product_id' => '', 'default_variant_id' => ''],
                ['product_id' => '', 'default_variant_id' => ''],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $box = ReadyGiftBox::query()->firstOrFail();
        $this->assertSame(2, $box->pricing_version);
        $this->assertFalse($box->is_active);
        $this->assertSame(2, $box->items()->count());
        $this->withoutExceptionHandling();
        $this->get(route('admin.gift-boxes.edit', $box))->assertOk()->assertSee('items[0][product_id]', false);
        $this->get(route('admin.gift-boxes.settings.edit'))->assertOk();
        $this->actingAs($admin)->patch(route('admin.gift-boxes.toggle-status', $box))->assertSessionHasErrors('is_active');
        $this->assertFalse($box->fresh()->is_active);
    }

    public function test_stale_quote_writes_nothing_then_cod_persists_exact_box_and_delivery_amounts(): void
    {
        $a = $this->variant(17, 39);
        $b = $this->variant(7, 19);
        $groups = ['one' => $this->payload([$a, $b])];
        $old = $this->snapshot($groups)['summary']['quote_version'];
        $a->product->update(['gift_box_price' => 18]);
        $city = City::query()->create(['name' => 'თბილისი']);
        $input = [
            'customer_name' => 'Integration fixture', 'customer_phone' => '555123456',
            'personal_number' => '00000000000', 'city_id' => $city->id,
            'exact_address' => 'Test address', 'payment_type' => 2, 'quote_version' => $old,
        ];
        $this->mock(BogPayService::class)->shouldNotReceive('create');
        $this->withSession(['gift_cart_groups' => $groups])->postJson(route('payment.validate'), $input)
            ->assertStatus(409)->assertJsonPath('cart_summary.payable_total', 37);
        $this->assertSame(0, Order::count());
        $this->assertSame(5, $a->fresh()->quantity);
        $input['quote_version'] = $this->snapshot($groups)['summary']['quote_version'];
        $this->withSession(['gift_cart_groups' => $groups])->postJson(route('payment.validate'), $input)->assertOk();
        $order = Order::query()->firstOrFail();
        $this->assertSame(37.0, (float) $order->total_amount);
        $this->assertSame(25.0, (float) $order->items->sum('subtotal'));
        $this->assertSame(12.0, (float) $order->adjustments->sum('amount'));
        $this->assertSame(1, $order->adjustments->where('type', 'delivery')->count());
        $this->assertSame(0, $order->adjustments->where('type', 'gift_discount')->count());
        $this->assertSame(4, $a->fresh()->quantity);
        $this->assertArrayNotHasKey('gift_cost_snapshot', $order->toArray());
        $this->assertNotNull($order->gift_cost_snapshot);
        $a->product->update(['gift_box_price' => 99]);
        $this->assertSame(37.0, (float) $order->fresh()->total_amount);
    }

    public function test_missing_costs_do_not_become_zero_or_fabricated_profit(): void
    {
        $report = app(GiftEconomicsService::class)->evaluate([
            'gift_revenue' => 29, 'products' => [['product_id' => 1, 'landed_cost' => null]],
            'packaging' => [['slug' => 'standard', 'actual_cost' => null]],
        ]);
        $this->assertNull($report['known_costs']);
        $this->assertNull($report['probable_remaining_amount']);
        $this->assertFalse($report['is_net_profit']);
    }

    public function test_settings_save_uses_the_explicit_singleton_id_without_auto_increment(): void
    {
        $service = app(\App\Services\GiftBuilder\GiftBuilderSettingsService::class);
        $settings = $service->current();
        DB::table('gift_builder_settings')->insert([
            'id' => 2, 'packaging_options' => json_encode($settings['packaging_options']),
            'delivery_fee' => 9, 'free_delivery_threshold' => 150,
        ]);
        $saved = $service->save($settings);
        $this->assertSame(1, $saved->id);
        $this->assertFalse($saved->getIncrementing());
        $this->assertNull($saved->ad_cost_per_order);
        $service->save($settings);
        $this->assertSame(1, DB::table('gift_builder_settings')->where('id', 1)->count());
        $this->assertEquals(9, DB::table('gift_builder_settings')->where('id', 2)->value('delivery_fee'));
    }

    public function test_v2_assets_load_independently_and_legacy_selector_remains_behind_flag(): void
    {
        $this->variant(17, 39);
        $this->variant(7, 19);
        $this->withoutExceptionHandling();
        $this->get(route('gift-builder.show'))->assertOk()
            ->assertSee('data-gift-builder-v2', false)->assertSee('/build/gift-v2/assets/', false);
        $this->get(route('gift-builder.boxes'))->assertOk()
            ->assertSee('data-gift-box-v2', false)->assertSee('/build/gift-v2/assets/', false);
        config()->set('gift_builder.pricing_v2_enabled', false);
        $this->get(route('gift-builder.show'))->assertOk()
            ->assertSee('data-gift-builder-experience', false)->assertDontSee('/build/gift-v2/assets/', false);
    }

    private function variant(float $box, float $retail, bool $active = false): ProductVariant
    {
        $product = Product::query()->create([
            'name_en' => 'Fixture', 'name_ka' => 'Fixture', 'slug' => 'fixture-'.uniqid(),
            'price' => $retail, 'gift_box_price' => $box, 'currency' => 'GEL',
            'gift_builder_enabled' => true, 'gift_builder_role' => 'none',
            'fulfillment_mode' => 'local_stock', 'is_active' => $active,
        ]);
        return $product->variants()->create(['name' => 'Default', 'quantity' => 5]);
    }

    private function payload(array $variants): array
    {
        return ['packaging_slug' => 'standard', 'items' => array_map(fn ($v) => ['variant_id' => $v->id], $variants)];
    }

    private function snapshot(array $groups, array $retail = [], bool $enforce = false): array
    {
        $request = Request::create('/cart');
        $session = app('session')->driver();
        $session->put('gift_cart_groups', $groups);
        $session->put('cart', $retail);
        $request->setLaravelSession($session);
        return app(CartSnapshotService::class)->build($request, ['enforce_stock' => $enforce]);
    }
}
