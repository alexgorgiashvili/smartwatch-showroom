<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutPaymentRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_online_checkout_does_not_require_a_separate_terms_checkbox(): void
    {
        [$variant, $city] = $this->checkoutFixtures('თბილისი');

        $this->withSession($this->cartSession($variant))
            ->postJson(route('payment.validate'), $this->checkoutPayload($city, 1))
            ->assertOk();
    }

    public function test_courier_payment_is_rejected_outside_tbilisi(): void
    {
        [$variant, $city] = $this->checkoutFixtures('ბათუმი');

        $this->withSession($this->cartSession($variant))
            ->postJson(route('payment.validate'), [
                ...$this->checkoutPayload($city, 2),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'კურიერთან გადახდა ხელმისაწვდომია მხოლოდ თბილისის შეკვეთებისთვის.');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_courier_payment_is_allowed_for_tbilisi(): void
    {
        [$variant, $city] = $this->checkoutFixtures('თბილისი');

        $this->withSession($this->cartSession($variant))
            ->postJson(route('payment.validate'), [
                ...$this->checkoutPayload($city, 2),
            ])
            ->assertOk()
            ->assertJsonStructure(['redirect_url', 'order_number']);

        $this->assertDatabaseHas('orders', [
            'city_id' => $city->id,
            'payment_type' => 2,
        ]);
    }

    public function test_courier_payment_is_allowed_for_tbilisi_suburb(): void
    {
        [$variant, $city] = $this->checkoutFixtures('თბილისი > კოჯორი');

        $this->withSession($this->cartSession($variant))
            ->postJson(route('payment.validate'), $this->checkoutPayload($city, 2))
            ->assertOk();

        $this->assertDatabaseHas('orders', [
            'city_id' => $city->id,
            'payment_type' => 2,
        ]);
    }

    private function checkoutFixtures(string $cityName): array
    {
        $city = City::query()->create(['name' => $cityName]);
        $product = Product::query()->create([
            'name_en' => 'Checkout test',
            'name_ka' => 'შეკვეთის ტესტი',
            'slug' => 'checkout-' . $city->id,
            'price' => 100,
            'currency' => 'GEL',
            'fulfillment_mode' => 'local_stock',
            'sim_support' => false,
            'gps_features' => false,
            'is_active' => true,
            'featured' => false,
        ]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Default',
            'quantity' => 5,
            'low_stock_threshold' => 1,
        ]);

        return [$variant, $city];
    }

    private function cartSession(ProductVariant $variant): array
    {
        return ['cart' => [$variant->id => ['variant_id' => $variant->id, 'quantity' => 1]]];
    }

    private function checkoutPayload(City $city, int $paymentType): array
    {
        return [
            'customer_name' => 'Test User',
            'customer_phone' => '995555123456',
            'personal_number' => '01001010101',
            'city_id' => $city->id,
            'exact_address' => 'Test address',
            'payment_type' => $paymentType,
        ];
    }
}
