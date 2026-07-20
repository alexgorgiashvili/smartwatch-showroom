<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AdminOrderQuickCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_courier_order_without_optional_customer_details(): void
    {
        Event::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::query()->create([
            'name_en' => 'Wonlex CT23 4G Kids Watch',
            'name_ka' => 'Wonlex CT23 4G Kids Watch',
            'model' => 'CT23',
            'slug' => 'wonlex-ct23-admin-quick-order',
            'price' => 149,
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
            'quantity' => 3,
            'low_stock_threshold' => 1,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.orders.store'), [
            'customer_name' => 'Nino Test',
            'customer_phone' => '555123456',
            'items' => [
                ['variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]);

        $order = Order::query()->firstOrFail();

        $response->assertRedirect(route('admin.orders.show', $order));
        $this->assertSame('Direct', $order->order_source);
        $this->assertSame(2, $order->payment_type);
        $this->assertNull($order->personal_number);
        $this->assertNull($order->city_id);
        $this->assertNull($order->exact_address);
        $this->assertSame('დასაზუსტებელია', $order->delivery_address);
        $this->assertSame(2, $variant->fresh()->quantity);
    }

    public function test_create_form_groups_variants_under_the_product_model(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::query()->create([
            'name_en' => 'Wonlex CT23 4G Kids Watch',
            'name_ka' => 'Wonlex CT23 4G Kids Watch',
            'model' => 'CT23',
            'slug' => 'wonlex-ct23-select-label',
            'price' => 149,
            'currency' => 'GEL',
            'sim_support' => true,
            'gps_features' => true,
            'is_active' => true,
            'featured' => false,
        ]);
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Blue',
            'quantity' => 3,
            'low_stock_threshold' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.orders.create'))
            ->assertOk()
            ->assertSee('CT23')
            ->assertSee('Wonlex CT23 4G Kids Watch');
    }

    public function test_admin_can_replace_an_order_item_and_stock_is_adjusted(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::query()->create([
            'name_en' => 'Wonlex CT23 4G Kids Watch',
            'name_ka' => 'Wonlex CT23 4G Kids Watch',
            'model' => 'CT23',
            'slug' => 'wonlex-ct23-admin-replace-order',
            'price' => 149,
            'currency' => 'GEL',
            'sim_support' => true,
            'gps_features' => true,
            'is_active' => true,
            'featured' => false,
            'fulfillment_mode' => 'local_stock',
        ]);
        $oldVariant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Blue',
            'quantity' => 2,
            'low_stock_threshold' => 1,
        ]);
        $replacementVariant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Pink',
            'quantity' => 4,
            'low_stock_threshold' => 1,
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
            'total_amount' => 149,
            'currency' => 'GEL',
            'fulfillment_mode' => 'local_stock',
            'bridge_sync_status' => 'not_required',
            'fulfillment_status' => 'unfulfilled',
        ]);
        $order->items()->create([
            'product_variant_id' => $oldVariant->id,
            'product_name' => $product->name_en,
            'variant_name' => $oldVariant->name,
            'quantity' => 1,
            'unit_price' => 149,
            'subtotal' => 149,
            'fulfillment_mode' => 'local_stock',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.orders.edit', $order))
            ->assertOk()
            ->assertSee('CT23');

        $response = $this->actingAs($admin)->patch(route('admin.orders.update', $order), [
            'customer_name' => 'Nino Test',
            'customer_phone' => '555123456',
            'items' => [
                ['variant_id' => $replacementVariant->id, 'quantity' => 2],
            ],
        ]);

        $response->assertRedirect(route('admin.orders.show', $order));
        $this->assertSame(3, $oldVariant->fresh()->quantity);
        $this->assertSame(2, $replacementVariant->fresh()->quantity);
        $this->assertSame($replacementVariant->id, $order->fresh()->items->sole()->product_variant_id);
        $this->assertSame(2, $order->fresh()->items->sole()->quantity);
    }
}
