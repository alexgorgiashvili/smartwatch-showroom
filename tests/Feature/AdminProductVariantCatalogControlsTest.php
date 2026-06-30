<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProductVariantCatalogControlsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_admin_can_toggle_variant_listing_visibility(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = $this->createProduct('toggle-watch', 'Toggle Watch');

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Black',
            'color_name' => 'Black',
            'color_hex' => '#000000',
            'is_listed_separately' => false,
            'quantity' => 5,
            'low_stock_threshold' => 1,
        ]);

        $response = $this->actingAs($admin)
            ->patchJson(route('admin.products.variants.toggle-listing', $variant), [
                'is_listed_separately' => true,
            ]);

        $response->assertOk();
        $response->assertJsonPath('variant.id', $variant->id);
        $response->assertJsonPath('variant.is_listed_separately', true);

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'is_listed_separately' => 1,
        ]);
    }

    public function test_admin_can_sync_variant_images_and_reject_images_from_other_products(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $product = $this->createProduct('mapped-watch', 'Mapped Watch');
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Red',
            'color_name' => 'Red',
            'color_hex' => '#FF0000',
            'is_listed_separately' => true,
            'quantity' => 5,
            'low_stock_threshold' => 1,
        ]);

        $firstImage = ProductImage::query()->create([
            'product_id' => $product->id,
            'path' => 'products/first.jpg',
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        $secondImage = ProductImage::query()->create([
            'product_id' => $product->id,
            'path' => 'products/second.jpg',
            'sort_order' => 1,
            'is_primary' => false,
        ]);

        $otherProduct = $this->createProduct('foreign-watch', 'Foreign Watch');
        $foreignImage = ProductImage::query()->create([
            'product_id' => $otherProduct->id,
            'path' => 'products/foreign.jpg',
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        $this->actingAs($admin)
            ->putJson(route('admin.products.variants.images.sync', $variant), [
                'image_ids' => [$firstImage->id, $foreignImage->id],
            ])
            ->assertStatus(422);

        $response = $this->actingAs($admin)
            ->putJson(route('admin.products.variants.images.sync', $variant), [
                'image_ids' => [$secondImage->id, $firstImage->id],
            ]);

        $response->assertOk();
        $response->assertJsonPath('variant.mapped_images_count', 2);
        $response->assertJsonPath('variant.mapped_image_ids.0', $secondImage->id);
        $response->assertJsonPath('variant.mapped_image_ids.1', $firstImage->id);

        $this->assertDatabaseHas('product_variant_images', [
            'product_variant_id' => $variant->id,
            'product_image_id' => $secondImage->id,
            'sort_order' => 0,
        ]);

        $this->assertDatabaseHas('product_variant_images', [
            'product_variant_id' => $variant->id,
            'product_image_id' => $firstImage->id,
            'sort_order' => 1,
        ]);
    }

    private function createProduct(string $slug, string $name): Product
    {
        return Product::query()->create([
            'name_en' => $name,
            'name_ka' => $name,
            'slug' => $slug,
            'price' => 150,
            'currency' => 'GEL',
            'sim_support' => true,
            'gps_features' => true,
            'is_active' => true,
            'featured' => false,
        ]);
    }
}
