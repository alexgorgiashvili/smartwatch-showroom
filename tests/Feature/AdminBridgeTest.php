<?php

namespace Tests\Feature;

use App\Jobs\PullBridgeOrderStatusJob;
use App\Jobs\PushBridgeOrderJob;
use App\Jobs\SyncBridgeInventoryJob;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Bridge\BridgeAlertService;
use App\Services\Bridge\WooBridgeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminBridgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.bridge.base_url', 'https://bridge.example.test');
        config()->set('services.bridge.username', 'bridge-admin');
        config()->set('services.bridge.app_password', 'secret');
        config()->set('services.bridge.usd_to_gel', 2.75);
        config()->set('services.bridge.product_limit', 20);
        Cache::flush();
    }

    public function test_admin_can_open_bridge_dashboard(): void
    {
        Http::fake([
            'https://bridge.example.test/wp-json/' => Http::response(['name' => 'bridge'], 200),
            'https://bridge.example.test/wp-json/wp/v2/users/me' => Http::response(['id' => 1], 200),
            'https://bridge.example.test/wp-json/wc/v3/products*' => Http::response([
                [
                    'id' => 23,
                    'name' => 'Bridge Kids Watch',
                    'status' => 'draft',
                    'type' => 'variable',
                    'price' => '10.71',
                    'stock_quantity' => 8,
                    'permalink' => 'https://bridge.example.test/product/bridge-kids-watch',
                ],
            ], 200, ['X-WP-Total' => 1]),
        ]);

        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get(route('admin.bridge.index', ['refresh_remote' => 1]));

        $response->assertOk();
        $response->assertSee('DSers Bridge');
        $response->assertSee('Bridge Kids Watch');
        $response->assertSee('Sync displayed products');
    }

    public function test_admin_can_open_bridge_dashboard_without_refreshing_remote_data(): void
    {
        Http::fake(function () {
            throw new \RuntimeException('Unexpected remote call.');
        });

        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get(route('admin.bridge.index'));

        $response->assertOk();
        $response->assertSee('DSers Bridge');
        $response->assertSee('Refresh bridge data');
    }

    public function test_admin_can_mark_cod_bridge_order_as_confirmed(): void
    {
        Http::fake([
            'https://bridge.example.test/wp-json/wc/v3/orders' => Http::response([
                'id' => 7771,
                'number' => 'WC-7771',
                'status' => 'processing',
            ], 201),
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $order = $this->makeBridgeReadyOrder(paymentType: 2, paymentStatus: 'pending', status: 'pending');

        $response = $this->actingAs($admin)->patch(route('admin.orders.update-status', $order), [
            'status' => 'confirmed',
            'notes' => null,
        ]);

        $response->assertRedirect(route('admin.orders.show', $order));

        $order->refresh();
        $this->assertSame('confirmed', $order->status);
    }

    public function test_admin_can_sync_bridge_product_into_local_catalog(): void
    {
        Http::fake([
            'https://bridge.example.test/wp-json/wc/v3/products/23' => Http::response([
                'id' => 23,
                'name' => 'Kids Smart Watch Waterproof',
                'slug' => 'kids-smart-watch-waterproof',
                'permalink' => 'https://bridge.example.test/product/23',
                'type' => 'variable',
                'status' => 'draft',
                'price' => '10.71',
                'sale_price' => '',
                'short_description' => 'Bridge short description',
                'description' => '<p>Main functions: camera, gps, sos</p><p>Brand Name: Demo</p>',
                'stock_quantity' => 8,
                'images' => [
                    ['src' => 'https://bridge.example.test/img/main.webp', 'thumbnail' => 'https://bridge.example.test/img/main-300.webp', 'alt' => 'Watch main'],
                ],
            ], 200),
            'https://bridge.example.test/wp-json/wc/v3/products/23/variations*' => Http::response([
                [
                    'id' => 25,
                    'stock_quantity' => 4,
                    'attributes' => [
                        ['name' => 'Color', 'option' => 'Pink'],
                    ],
                ],
                [
                    'id' => 27,
                    'stock_quantity' => 4,
                    'attributes' => [
                        ['name' => 'Color', 'option' => 'Blue'],
                    ],
                ],
            ], 200),
            'https://bridge.example.test/wp-json/' => Http::response(['name' => 'bridge'], 200),
            'https://bridge.example.test/wp-json/wp/v2/users/me' => Http::response(['id' => 1], 200),
            'https://bridge.example.test/wp-json/wc/v3/products*' => Http::response([], 200),
        ]);

        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post(route('admin.bridge.sync-product', 23));

        $response->assertRedirect(route('admin.bridge.index'));

        $product = Product::query()
            ->where('external_source', 'woo_bridge')
            ->where('external_product_id', '23')
            ->first();

        $this->assertNotNull($product);
        $this->assertSame('Kids Smart Watch Waterproof', $product->name_en);
        $this->assertSame('https://bridge.example.test/product/23', $product->external_source_url);
        $this->assertSame('dropship_bridge', $product->fulfillment_mode);
        $this->assertSame('23', $product->bridge_product_id);
        $this->assertSame('29.45', number_format((float) $product->price, 2, '.', ''));
        $this->assertFalse((bool) $product->is_active);
        $this->assertCount(1, $product->images);
        $this->assertCount(2, $product->variants);
        $this->assertSame(['camera', 'gps', 'sos'], $product->functions);
        $this->assertTrue($product->variants->every(fn (ProductVariant $variant) => $variant->stock_sync_status === 'synced'));
    }

    public function test_cod_order_keeps_local_reservation_only_for_local_stock_items(): void
    {
        Event::fake();

        $city = City::query()->create(['name' => 'Tbilisi']);

        $localProduct = Product::query()->create([
            'name_en' => 'Local Watch',
            'name_ka' => 'Local Watch',
            'slug' => 'local-watch',
            'price' => 100,
            'currency' => 'GEL',
            'sim_support' => true,
            'gps_features' => true,
            'is_active' => true,
            'featured' => false,
            'fulfillment_mode' => 'local_stock',
        ]);

        $localVariant = ProductVariant::query()->create([
            'product_id' => $localProduct->id,
            'name' => 'Black',
            'quantity' => 5,
            'low_stock_threshold' => 1,
        ]);

        $dropshipProduct = Product::query()->create([
            'name_en' => 'Bridge Watch',
            'name_ka' => 'Bridge Watch',
            'slug' => 'bridge-watch',
            'price' => 120,
            'currency' => 'GEL',
            'sim_support' => true,
            'gps_features' => true,
            'is_active' => true,
            'featured' => false,
            'fulfillment_mode' => 'dropship_bridge',
            'bridge_product_id' => 'woo-55',
        ]);

        $dropshipVariant = ProductVariant::query()->create([
            'product_id' => $dropshipProduct->id,
            'name' => 'Blue',
            'quantity' => 0,
            'low_stock_threshold' => 1,
            'bridge_variation_id' => 'woo-var-9',
            'bridge_stock_quantity' => 7,
            'bridge_stock_status' => 'instock',
        ]);

        $response = $this
            ->withSession([
                'cart' => [
                    $localVariant->id => ['variant_id' => $localVariant->id, 'quantity' => 2],
                    $dropshipVariant->id => ['variant_id' => $dropshipVariant->id, 'quantity' => 3],
                ],
            ])
            ->postJson(route('payment.validate'), [
                'customer_name' => 'Nino Test',
                'customer_phone' => '555123456',
                'personal_number' => '12345678901',
                'city_id' => $city->id,
                'exact_address' => 'Rustaveli 1',
                'payment_type' => 2,
            ]);

        $response->assertOk()
            ->assertJsonPath('order_number', fn ($value) => is_string($value) && str_starts_with($value, 'ORD-'));

        $localVariant->refresh();
        $dropshipVariant->refresh();

        $this->assertSame(3, $localVariant->quantity);
        $this->assertSame(0, $dropshipVariant->quantity);

        $order = \App\Models\Order::query()->with('items')->latest('id')->first();

        $this->assertSame('mixed', $order->fulfillment_mode);
        $this->assertSame('pending_push', $order->bridge_sync_status);
        $this->assertSame('unfulfilled', $order->fulfillment_status);
        $this->assertSame('local_stock', $order->items->firstWhere('product_variant_id', $localVariant->id)?->fulfillment_mode);
        $this->assertSame('dropship_bridge', $order->items->firstWhere('product_variant_id', $dropshipVariant->id)?->fulfillment_mode);
    }

    public function test_admin_can_push_paid_bridge_order(): void
    {
        Http::fake([
            'https://bridge.example.test/wp-json/wc/v3/orders' => Http::response([
                'id' => 9001,
                'number' => 'WC-9001',
                'status' => 'processing',
            ], 201),
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $order = $this->makeBridgeReadyOrder(paymentType: 1, paymentStatus: 'completed', status: 'pending');

        $response = $this->actingAs($admin)->post(route('admin.orders.bridge.push', $order));

        $response->assertRedirect(route('admin.orders.show', $order));

        $order->refresh();
        $this->assertSame('9001', $order->bridge_order_id);
        $this->assertSame('WC-9001', $order->bridge_order_number);
        $this->assertSame('pushed', $order->bridge_sync_status);
    }

    public function test_cod_bridge_order_cannot_be_pushed_before_confirmation(): void
    {
        Http::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $order = $this->makeBridgeReadyOrder(paymentType: 2, paymentStatus: 'pending', status: 'pending');

        $response = $this->actingAs($admin)->post(route('admin.orders.bridge.push', $order));

        $response->assertSessionHas('error');
    }

    public function test_admin_can_refresh_bridge_order_tracking(): void
    {
        Http::fake([
            'https://bridge.example.test/wp-json/wc/v3/orders/9001' => Http::response([
                'id' => 9001,
                'number' => 'WC-9001',
                'status' => 'completed',
                'meta_data' => [
                    ['key' => 'tracking_number', 'value' => 'TRK123'],
                    ['key' => 'tracking_carrier', 'value' => 'DHL'],
                ],
            ], 200),
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $order = $this->makeBridgeReadyOrder(paymentType: 1, paymentStatus: 'completed', status: 'pending');
        $order->update([
            'bridge_order_id' => '9001',
            'bridge_order_number' => 'WC-9001',
            'bridge_sync_status' => 'pushed',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.orders.bridge.refresh', $order));

        $response->assertRedirect(route('admin.orders.show', $order));

        $order->refresh();
        $this->assertSame('tracking_received', $order->bridge_sync_status);
        $this->assertSame('TRK123', $order->tracking_number);
        $this->assertSame('DHL', $order->tracking_carrier);
    }

    public function test_admin_can_open_order_detail_with_bridge_controls(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $order = $this->makeBridgeReadyOrder(paymentType: 2, paymentStatus: 'pending', status: 'pending');

        $response = $this->actingAs($admin)->get(route('admin.orders.show', $order));

        $response->assertOk();
        $response->assertSee('Push to Bridge');
        $response->assertSee('dropship_bridge');
    }

    public function test_push_bridge_order_job_processes_eligible_orders(): void
    {
        Http::fake([
            'https://bridge.example.test/wp-json/wc/v3/orders' => Http::response([
                'id' => 9911,
                'number' => 'WC-9911',
                'status' => 'processing',
            ], 201),
        ]);

        $order = $this->makeBridgeReadyOrder(paymentType: 1, paymentStatus: 'completed', status: 'pending');

        app()->call([new PushBridgeOrderJob($order->id), 'handle']);

        $order->refresh();
        $this->assertSame('pushed', $order->bridge_sync_status);
        $this->assertSame('9911', $order->bridge_order_id);
    }

    public function test_pull_bridge_order_status_job_updates_tracking(): void
    {
        Http::fake([
            'https://bridge.example.test/wp-json/wc/v3/orders/8111' => Http::response([
                'id' => 8111,
                'number' => 'WC-8111',
                'status' => 'completed',
                'meta_data' => [
                    ['key' => 'tracking_number', 'value' => 'AUTO-TRACK'],
                    ['key' => 'tracking_carrier', 'value' => 'UPS'],
                ],
            ], 200),
        ]);

        $order = $this->makeBridgeReadyOrder(paymentType: 1, paymentStatus: 'completed', status: 'pending');
        $order->update([
            'bridge_order_id' => '8111',
            'bridge_order_number' => 'WC-8111',
            'bridge_sync_status' => 'pushed',
        ]);

        app()->call([new PullBridgeOrderStatusJob($order->id), 'handle']);

        $order->refresh();
        $this->assertSame('tracking_received', $order->bridge_sync_status);
        $this->assertSame('AUTO-TRACK', $order->tracking_number);
    }

    public function test_sync_bridge_inventory_job_refreshes_mapped_product_stock(): void
    {
        Http::fake([
            'https://bridge.example.test/wp-json/wc/v3/products/44' => Http::response([
                'id' => 44,
                'name' => 'Inventory Refresh Watch',
                'slug' => 'inventory-refresh-watch',
                'permalink' => 'https://bridge.example.test/product/44',
                'type' => 'simple',
                'status' => 'publish',
                'price' => '12.00',
                'stock_quantity' => 13,
                'stock_status' => 'instock',
                'images' => [],
            ], 200),
        ]);

        $product = Product::query()->create([
            'name_en' => 'Inventory Refresh Watch',
            'name_ka' => 'Inventory Refresh Watch',
            'slug' => 'inventory-refresh-watch-local',
            'price' => 10,
            'currency' => 'GEL',
            'sim_support' => true,
            'gps_features' => true,
            'is_active' => true,
            'featured' => false,
            'fulfillment_mode' => 'dropship_bridge',
            'bridge_product_id' => '44',
            'external_source' => 'woo_bridge',
            'external_product_id' => '44',
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Default',
            'quantity' => 0,
            'low_stock_threshold' => 1,
            'bridge_variation_id' => '44',
            'bridge_stock_quantity' => 1,
            'bridge_stock_status' => 'instock',
        ]);

        app()->call([new SyncBridgeInventoryJob(), 'handle']);

        $variant->refresh();
        $this->assertSame(13, $variant->bridge_stock_quantity);
        $this->assertSame('synced', $variant->stock_sync_status);
    }

    public function test_bridge_alert_service_reports_push_failures(): void
    {
        Http::fake([
            'https://bridge.example.test/wp-json/wp/v2/users/me' => Http::response(['message' => 'forbidden'], 401),
        ]);

        app(WooBridgeService::class)->refreshStatus();

        $order = $this->makeBridgeReadyOrder(paymentType: 2, paymentStatus: 'pending', status: 'pending');
        $order->update(['bridge_sync_status' => 'push_failed']);

        $alerts = app(BridgeAlertService::class)->alerts();

        $this->assertTrue($alerts->contains(fn (array $alert) => $alert['title'] === 'Bridge push failed'));
        $this->assertTrue($alerts->contains(fn (array $alert) => $alert['title'] === 'Bridge authentication failed'));
    }

    public function test_bridge_status_uses_auth_endpoint_without_fetching_rest_index(): void
    {
        Http::fake([
            'https://bridge.example.test/wp-json/wp/v2/users/me' => Http::response(['message' => 'forbidden'], 401),
        ]);

        $status = app(WooBridgeService::class)->status();

        $this->assertTrue($status['configured']);
        $this->assertTrue($status['reachable']);
        $this->assertFalse($status['authenticated']);
        $this->assertSame(0, $status['product_count']);
        $this->assertSame('Bridge authentication failed. WordPress rejected the provided credentials.', $status['error']);
    }

    private function makeBridgeReadyOrder(int $paymentType, string $paymentStatus, string $status): Order
    {
        $product = Product::query()->create([
            'name_en' => 'Bridge Push Watch',
            'name_ka' => 'Bridge Push Watch',
            'slug' => 'bridge-push-watch-' . uniqid(),
            'price' => 150,
            'currency' => 'GEL',
            'sim_support' => true,
            'gps_features' => true,
            'is_active' => true,
            'featured' => false,
            'fulfillment_mode' => 'dropship_bridge',
            'bridge_product_id' => '44',
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Green',
            'quantity' => 0,
            'low_stock_threshold' => 1,
            'bridge_variation_id' => '55',
            'bridge_stock_quantity' => 5,
            'bridge_stock_status' => 'instock',
        ]);

        $order = Order::query()->create([
            'order_number' => Order::generateOrderNumber(),
            'customer_name' => 'Bridge Customer',
            'customer_phone' => '995555123456',
            'personal_number' => '12345678901',
            'delivery_address' => 'Address 1',
            'exact_address' => 'Address 1',
            'city' => 'Tbilisi',
            'order_source' => 'Direct',
            'payment_type' => $paymentType,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'total_amount' => 150,
            'currency' => 'GEL',
            'fulfillment_mode' => 'dropship_bridge',
            'bridge_sync_status' => $paymentType === 1 ? 'pending_payment' : 'pending_push',
            'fulfillment_status' => 'unfulfilled',
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name_en,
            'variant_name' => $variant->name,
            'quantity' => 1,
            'unit_price' => 150,
            'subtotal' => 150,
            'bridge_product_id' => '44',
            'bridge_variation_id' => '55',
            'fulfillment_mode' => 'dropship_bridge',
        ]);

        return $order;
    }
}
