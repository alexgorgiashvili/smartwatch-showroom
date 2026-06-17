<?php

namespace Tests\Feature;

use App\Events\PaymentCompleted;
use App\Jobs\PushBridgeOrderJob;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BogPaymentCallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_callback_does_not_trust_payload_without_verified_completed_status(): void
    {
        Queue::fake();
        Event::fake();

        $order = $this->makeBogOrder([
            'payment_status' => 'pending',
            'fulfillment_mode' => 'mixed',
            'bridge_sync_status' => 'pending_payment',
        ]);

        Http::fake([
            'https://oauth2.bog.ge/*' => Http::response([
                'access_token' => 'test-token',
            ], 200),
            'https://api.bog.ge/payments/v1/receipt/*' => Http::response([
                'order_id' => $order->bog_order_id,
                'external_order_id' => $order->bog_external_order_id,
                'order_status' => [
                    'key' => 'rejected',
                ],
            ], 200),
        ]);

        $response = $this->postJson(route('payment.bog.callback'), [
            'order_id' => $order->bog_order_id,
            'external_order_id' => $order->bog_external_order_id,
            'order_status' => [
                'key' => 'completed',
            ],
        ]);

        $response->assertOk();
        $this->assertSame('rejected', $order->fresh()->payment_status);
        $this->assertDatabaseHas('payment_logs', [
            'order_id' => $order->id,
            'status' => 'REJECTED',
        ]);
        Queue::assertNothingPushed();
        Event::assertNotDispatched(PaymentCompleted::class);
    }

    public function test_verified_completed_callback_marks_order_paid_and_dispatches_side_effects_once(): void
    {
        Queue::fake();
        Event::fake();

        $order = $this->makeBogOrder([
            'payment_status' => 'pending',
            'fulfillment_mode' => 'mixed',
            'bridge_sync_status' => 'pending_payment',
        ]);

        Http::fake([
            'https://oauth2.bog.ge/*' => Http::response([
                'access_token' => 'test-token',
            ], 200),
            'https://api.bog.ge/payments/v1/receipt/*' => Http::response([
                'order_id' => $order->bog_order_id,
                'external_order_id' => $order->bog_external_order_id,
                'order_status' => [
                    'key' => 'completed',
                ],
            ], 200),
        ]);

        $payload = [
            'order_id' => $order->bog_order_id,
            'external_order_id' => $order->bog_external_order_id,
            'order_status' => [
                'key' => 'completed',
            ],
        ];

        $this->postJson(route('payment.bog.callback'), $payload)->assertOk();
        $this->postJson(route('payment.bog.callback'), $payload)->assertOk();

        $this->assertSame('completed', $order->fresh()->payment_status);
        $this->assertDatabaseCount('payment_logs', 1);
        $this->assertDatabaseHas('payment_logs', [
            'order_id' => $order->id,
            'status' => 'PERFORMED',
        ]);
        Queue::assertPushed(PushBridgeOrderJob::class, 1);
        Event::assertDispatched(PaymentCompleted::class, 1);
    }

    public function test_verified_completed_callback_decrements_local_stock_only_once(): void
    {
        Queue::fake();
        Event::fake();

        $product = Product::query()->create([
            'name_en' => 'Local Watch',
            'name_ka' => 'Local Watch',
            'slug' => 'local-watch-paid',
            'price' => 99.99,
            'currency' => 'GEL',
            'sim_support' => true,
            'gps_features' => true,
            'is_active' => true,
            'featured' => false,
            'fulfillment_mode' => 'local_stock',
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Black',
            'quantity' => 5,
            'low_stock_threshold' => 1,
        ]);

        $order = $this->makeBogOrder([
            'payment_status' => 'pending',
            'fulfillment_mode' => 'local_stock',
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name_en,
            'variant_name' => $variant->name,
            'quantity' => 2,
            'unit_price' => 99.99,
            'subtotal' => 199.98,
            'fulfillment_mode' => 'local_stock',
        ]);

        Http::fake([
            'https://oauth2.bog.ge/*' => Http::response([
                'access_token' => 'test-token',
            ], 200),
            'https://api.bog.ge/payments/v1/receipt/*' => Http::response([
                'order_id' => $order->bog_order_id,
                'external_order_id' => $order->bog_external_order_id,
                'order_status' => [
                    'key' => 'completed',
                ],
            ], 200),
        ]);

        $payload = [
            'order_id' => $order->bog_order_id,
            'external_order_id' => $order->bog_external_order_id,
            'order_status' => [
                'key' => 'completed',
            ],
        ];

        $variant->decrement('quantity', 2);

        $this->postJson(route('payment.bog.callback'), $payload)->assertOk();
        $this->postJson(route('payment.bog.callback'), $payload)->assertOk();

        $this->assertSame(3, $variant->fresh()->quantity);
    }

    public function test_verified_rejected_callback_restores_local_stock_only_once(): void
    {
        Queue::fake();
        Event::fake();

        $product = Product::query()->create([
            'name_en' => 'Local Watch',
            'name_ka' => 'Local Watch',
            'slug' => 'local-watch-rejected',
            'price' => 99.99,
            'currency' => 'GEL',
            'sim_support' => true,
            'gps_features' => true,
            'is_active' => true,
            'featured' => false,
            'fulfillment_mode' => 'local_stock',
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Black',
            'quantity' => 3,
            'low_stock_threshold' => 1,
        ]);

        $order = $this->makeBogOrder([
            'payment_status' => 'pending',
            'fulfillment_mode' => 'local_stock',
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name_en,
            'variant_name' => $variant->name,
            'quantity' => 2,
            'unit_price' => 99.99,
            'subtotal' => 199.98,
            'fulfillment_mode' => 'local_stock',
        ]);

        Http::fake([
            'https://oauth2.bog.ge/*' => Http::response([
                'access_token' => 'test-token',
            ], 200),
            'https://api.bog.ge/payments/v1/receipt/*' => Http::response([
                'order_id' => $order->bog_order_id,
                'external_order_id' => $order->bog_external_order_id,
                'order_status' => [
                    'key' => 'rejected',
                ],
            ], 200),
        ]);

        $payload = [
            'order_id' => $order->bog_order_id,
            'external_order_id' => $order->bog_external_order_id,
            'order_status' => [
                'key' => 'rejected',
            ],
        ];

        $this->postJson(route('payment.bog.callback'), $payload)->assertOk();
        $this->postJson(route('payment.bog.callback'), $payload)->assertOk();

        $this->assertSame(5, $variant->fresh()->quantity);
    }

    private function makeBogOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => Order::generateOrderNumber(),
            'customer_name' => 'Test Customer',
            'customer_phone' => '995599123456',
            'personal_number' => '12345678901',
            'customer_email' => null,
            'delivery_address' => 'Tbilisi',
            'exact_address' => 'Tbilisi',
            'city' => 'Tbilisi',
            'city_id' => null,
            'postal_code' => null,
            'order_source' => 'Direct',
            'status' => 'pending',
            'payment_type' => 1,
            'payment_status' => 'pending',
            'bog_order_id' => 'bog-order-123',
            'bog_external_order_id' => 'IPAY-ORDER-123',
            'fulfillment_mode' => 'local_stock',
            'bridge_sync_status' => 'not_required',
            'fulfillment_status' => 'unfulfilled',
            'total_amount' => 99.99,
            'currency' => 'GEL',
            'notes' => null,
            'is_gift_order' => false,
            'gift_groups' => null,
            'gift_packaging_amount' => 0,
            'gift_discount_amount' => 0,
        ], $overrides));
    }
}
