<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ReadyGiftBox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackfillReadyGiftBoxesTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_is_strict_and_idempotent(): void
    {
        $main = $this->product('Mapped Main', 'mapped-main', 'main');
        $addon = $this->product('Mapped Add-on', 'mapped-addon', 'addon');
        config()->set('ready_gift_boxes_legacy', [
            'mapped-box' => [
                'title_ka' => 'Mapped Box',
                'title_en' => 'Mapped Box',
                'main_product' => $main->slug,
                'addon_products' => [$addon->slug],
                'packaging_slug' => 'standard',
                'theme_key' => 'grape',
                'sort_order' => 10,
            ],
        ]);

        $this->artisan('gift-boxes:backfill --strict')->assertSuccessful();
        $box = ReadyGiftBox::query()->where('slug', 'mapped-box')->with('items')->firstOrFail();
        $itemIds = $box->items->pluck('id')->all();

        $this->artisan('gift-boxes:backfill --strict')->assertSuccessful();

        $this->assertSame(1, ReadyGiftBox::query()->where('slug', 'mapped-box')->count());
        $this->assertSame($itemIds, $box->fresh('items')->items->pluck('id')->all());
    }

    public function test_strict_backfill_rolls_back_all_boxes_on_missing_product(): void
    {
        $main = $this->product('Valid Main', 'valid-main', 'main');
        config()->set('ready_gift_boxes_legacy', [
            'valid-box' => [
                'title_ka' => 'Valid Box',
                'main_product' => $main->slug,
                'addon_products' => [],
                'packaging_slug' => 'standard',
            ],
            'broken-box' => [
                'title_ka' => 'Broken Box',
                'main_product' => 'missing-product',
                'addon_products' => [],
                'packaging_slug' => 'standard',
            ],
        ]);

        $this->artisan('gift-boxes:backfill --strict')->assertFailed();
        $this->assertDatabaseCount('ready_gift_boxes', 0);
    }

    public function test_readiness_audit_requires_the_local_primary_image_file_to_exist(): void
    {
        Storage::fake('public');
        $main = $this->product('Audited Main', 'audited-main', 'main');
        $main->images()->create([
            'path' => 'products/audited-main.webp',
            'thumbnail_path' => 'products/audited-main.webp',
            'is_primary' => true,
            'sort_order' => 0,
        ]);
        $box = ReadyGiftBox::query()->create([
            'slug' => 'audited-box',
            'title_ka' => 'Audited Box',
            'theme_key' => 'grape',
            'packaging_slug' => 'standard',
            'discount_type' => 'none',
            'is_active' => true,
        ]);
        $box->items()->create([
            'product_id' => $main->id,
            'default_variant_id' => $main->variants->first()->id,
            'role' => 'main',
            'sort_order' => 0,
        ]);

        $this->artisan('gift-boxes:audit')
            ->expectsOutputToContain('missing_image_file')
            ->assertFailed();

        Storage::disk('public')->put('products/audited-main.webp', 'image-bytes');

        $this->artisan('gift-boxes:audit')->assertSuccessful();
    }

    private function product(string $name, string $slug, string $role): Product
    {
        $product = Product::query()->create([
            'name_en' => $name,
            'name_ka' => $name,
            'slug' => $slug,
            'price' => 100,
            'currency' => 'GEL',
            'is_active' => true,
            'featured' => false,
            'fulfillment_mode' => 'local_stock',
            'gift_builder_enabled' => true,
            'gift_builder_role' => $role,
            'gift_capacity_units' => 1,
        ]);
        $product->variants()->create([
            'name' => 'Default',
            'quantity' => 5,
            'low_stock_threshold' => 1,
        ]);

        return $product->fresh('variants');
    }
}
