<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\GiftBuilder\GiftBuilderPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GiftBuilderPricingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_requires_exactly_one_main_item(): void
    {
        $addon = $this->giftVariant('Addon', 'addon', 45);

        $this->expectException(ValidationException::class);

        app(GiftBuilderPricingService::class)->price($this->payload([
            ['variant_id' => $addon->id, 'role' => 'addon'],
        ]));
    }

    public function test_it_rejects_out_of_stock_items(): void
    {
        $main = $this->giftVariant('Main', 'main', 120, quantity: 0);

        $this->expectException(ValidationException::class);

        app(GiftBuilderPricingService::class)->price($this->payload([
            ['variant_id' => $main->id, 'role' => 'main'],
        ]));
    }

    public function test_it_rejects_incompatible_addons(): void
    {
        $main = $this->giftVariant('Main', 'main', 120, compatibilityTags: ['gps']);
        $addon = $this->giftVariant('Addon', 'addon', 35, compatibilityTags: ['camera']);

        $this->expectException(ValidationException::class);

        app(GiftBuilderPricingService::class)->price($this->payload([
            ['variant_id' => $main->id, 'role' => 'main'],
            ['variant_id' => $addon->id, 'role' => 'addon'],
        ]));
    }

    public function test_it_blocks_packaging_capacity_overflow(): void
    {
        config()->set('gift_builder.packaging.standard.capacity_units', 1);
        $main = $this->giftVariant('Main', 'main', 120, capacityUnits: 2);

        $this->expectException(ValidationException::class);

        app(GiftBuilderPricingService::class)->price($this->payload([
            ['variant_id' => $main->id, 'role' => 'main'],
        ]));
    }

    public function test_it_returns_totals_budget_warning_and_sanitized_message(): void
    {
        config()->set('gift_builder.packaging.premium.price', 12);
        $main = $this->giftVariant('Main', 'main', 180, compatibilityTags: ['starter']);
        $addon = $this->giftVariant('Addon', 'addon', 35, compatibilityTags: []);

        $result = app(GiftBuilderPricingService::class)->price($this->payload([
            ['variant_id' => $main->id, 'role' => 'main'],
            ['variant_id' => $addon->id, 'role' => 'addon'],
        ], [
            'budget_band' => 'under_150',
            'packaging_slug' => 'premium',
            'message' => '<b>Happy</b>    birthday',
        ]));

        $this->assertSame('Happy birthday', $result['message']);
        $this->assertSame(215.0, $result['items_subtotal']);
        $this->assertSame(12.0, $result['packaging_amount']);
        $this->assertSame(227.0, $result['total']);
        $this->assertSame('budget_overage', $result['warnings'][0]['code']);
    }

    private function payload(array $items, array $overrides = []): array
    {
        return array_replace([
            'recipient_type' => 'child_8_12',
            'occasion' => 'birthday',
            'budget_band' => 'all',
            'packaging_slug' => 'standard',
            'message' => null,
            'items' => $items,
        ], $overrides);
    }

    private function giftVariant(
        string $name,
        string $role,
        float $price,
        int $quantity = 3,
        array $compatibilityTags = [],
        int $capacityUnits = 1
    ): ProductVariant {
        $product = Product::query()->create([
            'name_en' => $name,
            'name_ka' => $name,
            'slug' => str()->slug($name . '-' . uniqid()),
            'price' => $price,
            'currency' => 'GEL',
            'is_active' => true,
            'featured' => false,
            'fulfillment_mode' => 'local_stock',
            'gift_builder_enabled' => true,
            'gift_builder_role' => $role,
            'gift_recipient_tags' => null,
            'gift_occasion_tags' => null,
            'gift_budget_band' => 'all',
            'gift_compatibility_tags' => $compatibilityTags,
            'gift_capacity_units' => $capacityUnits,
            'gift_sort_order' => 0,
        ]);

        return ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Default',
            'quantity' => $quantity,
            'low_stock_threshold' => 1,
        ]);
    }
}
