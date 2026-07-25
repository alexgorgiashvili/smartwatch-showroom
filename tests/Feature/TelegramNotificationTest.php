<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Events\OrderCreated;
use App\Listeners\SendOrderTelegramNotification;
use App\Services\TelegramOrderNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inquiry_with_a_link_is_not_saved_or_sent_to_telegram(): void
    {
        Http::fake();

        $response = $this->from('/')
            ->post(route('inquiries.store'), [
                'name' => 'Spam Sender',
                'phone' => '555123456',
                'message' => 'Claim your prize https://example.test/win',
            ]);

        $response->assertRedirect('/');
        $this->assertDatabaseCount('inquiries', 0);
        Http::assertNothingSent();
    }

    public function test_order_telegram_notification_is_sent_only_once(): void
    {
        config([
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.chat_id' => '12345',
        ]);
        Cache::flush();
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $order = Order::query()->create([
            'order_number' => Order::generateOrderNumber(),
            'customer_name' => 'Nino Test',
            'customer_phone' => '995555123456',
            'delivery_address' => 'Tbilisi',
            'exact_address' => 'Tbilisi',
            'order_source' => 'Direct',
            'status' => 'pending',
            'payment_type' => 2,
            'payment_status' => 'pending',
            'total_amount' => 59,
            'currency' => 'GEL',
            'fulfillment_mode' => 'local_stock',
            'bridge_sync_status' => 'not_required',
            'fulfillment_status' => 'unfulfilled',
        ]);
        $product = Product::query()->create([
            'name_en' => 'CT23',
            'name_ka' => 'CT23',
            'slug' => 'telegram-order-test-product',
            'price' => 59,
            'currency' => 'GEL',
            'sim_support' => true,
            'gps_features' => true,
            'is_active' => true,
            'featured' => false,
            'fulfillment_mode' => 'local_stock',
        ]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Blue',
            'quantity' => 1,
            'low_stock_threshold' => 1,
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'CT23',
            'variant_name' => 'Blue',
            'quantity' => 1,
            'unit_price' => 59,
            'subtotal' => 59,
            'fulfillment_mode' => 'local_stock',
        ]);

        $notifier = app(TelegramOrderNotifier::class);
        $notifier->send($order);
        $notifier->send($order);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains((string) $request['text'], 'ORD-'));
    }

    public function test_queued_order_listener_resolves_the_notifier_from_the_container(): void
    {
        $notifier = $this->mock(TelegramOrderNotifier::class);
        $notifier->shouldReceive('send')->once();

        app(SendOrderTelegramNotification::class)->handle(new OrderCreated(new Order(), true));
    }
}
