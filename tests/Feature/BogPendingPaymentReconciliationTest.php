<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BogPendingPaymentReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_restores_stock_for_old_rejected_pending_payment(): void
    {
        Event::fake();

        $product = Product::query()->create([
            'name_en' => 'Local Watch',
            'name_ka' => 'Local Watch',
            'slug' => 'local-watch-reconcile',
            'price' => 100,
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

        $order = Order::create([
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
            'bog_order_id' => 'bog-order-old',
            'bog_external_order_id' => 'IPAY-OLD-123',
            'fulfillment_mode' => 'local_stock',
            'bridge_sync_status' => 'not_required',
            'fulfillment_status' => 'unfulfilled',
            'total_amount' => 200,
            'currency' => 'GEL',
            'notes' => null,
            'is_gift_order' => false,
            'gift_groups' => null,
            'gift_packaging_amount' => 0,
            'gift_discount_amount' => 0,
        ]);

        $order->timestamps = false;
        $order->forceFill([
            'created_at' => now()->subMinutes(45),
            'updated_at' => now()->subMinutes(45),
        ])->save();

        OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name_en,
            'variant_name' => $variant->name,
            'quantity' => 2,
            'unit_price' => 100,
            'subtotal' => 200,
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

        Artisan::call('payments:reconcile-bog-pending', [
            '--minutes' => 30,
            '--limit' => 10,
        ]);

        $this->assertSame('rejected', $order->fresh()->payment_status);
        $this->assertSame(5, $variant->fresh()->quantity);
        $this->assertDatabaseHas('payment_logs', [
            'order_id' => $order->id,
            'status' => 'REJECTED',
        ]);
    }
}
