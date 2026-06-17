<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CardPaymentStockHoldTest extends TestCase
{
    use RefreshDatabase;

    public function test_card_payment_decrements_local_stock_when_payment_starts(): void
    {
        Event::fake();

        $city = City::query()->create(['name' => 'Tbilisi']);

        $product = Product::query()->create([
            'name_en' => 'Local Watch',
            'name_ka' => 'Local Watch',
            'slug' => 'local-watch-card',
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
            'quantity' => 5,
            'low_stock_threshold' => 1,
        ]);

        Http::fake([
            'https://oauth2.bog.ge/*' => Http::response([
                'access_token' => 'test-token',
            ], 200),
            'https://api.bog.ge/payments/v1/ecommerce/orders' => Http::response([
                'id' => 'bog-order-123',
                '_links' => [
                    'redirect' => [
                        'href' => 'https://payment.bog.ge/?order_id=bog-order-123',
                    ],
                ],
            ], 200),
        ]);

        $response = $this
            ->withSession([
                'cart' => [
                    $variant->id => ['variant_id' => $variant->id, 'quantity' => 2],
                ],
            ])
            ->postJson(route('payment.validate'), [
                'customer_name' => 'Nino Test',
                'customer_phone' => '555123456',
                'personal_number' => '12345678901',
                'city_id' => $city->id,
                'exact_address' => 'Rustaveli 1',
                'payment_type' => 1,
            ]);

        $response->assertOk()
            ->assertJsonPath('order_number', fn ($value) => is_string($value) && str_starts_with($value, 'ORD-'));

        $this->assertSame(3, $variant->fresh()->quantity);
    }
}
