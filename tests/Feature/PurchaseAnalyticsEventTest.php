<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseAnalyticsEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_success_page_emits_a_valid_purchase_payload(): void
    {
        $product = Product::query()->create([
            'name_en' => 'Test Watch',
            'name_ka' => 'Test Watch',
            'slug' => 'analytics-test-watch',
            'price' => 99.95,
        ]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Black',
        ]);
        $order = Order::query()->create([
            'order_number' => 'ORD-ANALYTICS-1',
            'customer_name' => 'Analytics Test',
            'customer_phone' => '555000000',
            'delivery_address' => 'Test Address',
            'payment_type' => 1,
            'status' => 'confirmed',
            'payment_status' => 'completed',
            'total_amount' => 199.90,
            'currency' => 'gel',
        ]);

        $item = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Test Watch',
            'variant_name' => 'Black',
            'quantity' => 2,
            'unit_price' => 99.95,
            'subtotal' => 199.90,
        ]);

        $response = $this->get(route('payment.success', [
            'order' => $order->order_number,
            'method' => 'card',
        ]));

        $response->assertOk();
        $response->assertViewHas('purchaseEvent', function (array $event) use ($variant): bool {
            return $event['value'] === 199.9
                && $event['currency'] === 'GEL'
                && $event['content_ids'] === [(string) $variant->id]
                && $event['contents'][0]['id'] === (string) $variant->id
                && $event['items'][0]['item_id'] === (string) $variant->id;
        });
    }

    public function test_success_page_does_not_emit_purchase_with_zero_value(): void
    {
        $order = Order::query()->create([
            'order_number' => 'ORD-ANALYTICS-0',
            'customer_name' => 'Analytics Test',
            'customer_phone' => '555000000',
            'delivery_address' => 'Test Address',
            'payment_type' => 1,
            'status' => 'confirmed',
            'payment_status' => 'completed',
            'total_amount' => 0,
            'currency' => 'GEL',
        ]);

        $response = $this->get(route('payment.success', [
            'order' => $order->order_number,
            'method' => 'card',
        ]));

        $response->assertOk();
        $response->assertViewHas('purchaseEvent', null);
        $response->assertDontSee("storefrontAnalytics.track('Purchase'", false);
    }
}
