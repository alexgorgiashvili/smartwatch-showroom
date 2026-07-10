<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductPrimaryImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_image_casts_foreign_key_to_integer(): void
    {
        $image = ProductImage::newFromBuilder([
            'id' => 1,
            'product_id' => '18',
            'path' => 'images/products/example.jpg',
        ]);

        $this->assertSame(18, $image->product_id);
    }

    public function test_admin_can_set_an_existing_product_image_as_primary(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::query()->create([
            'name_en' => 'Primary Image Watch',
            'name_ka' => 'Primary Image Watch',
            'slug' => 'primary-image-watch',
            'price' => 150,
            'currency' => 'GEL',
            'sim_support' => true,
            'gps_features' => true,
            'is_active' => true,
            'featured' => false,
        ]);

        $oldPrimary = ProductImage::query()->create([
            'product_id' => $product->id,
            'path' => 'images/products/old-primary.jpg',
            'sort_order' => 0,
            'is_primary' => true,
        ]);
        $newPrimary = ProductImage::query()->create([
            'product_id' => $product->id,
            'path' => 'images/products/new-primary.jpg',
            'sort_order' => 1,
            'is_primary' => false,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.products.images.primary', [$product, $newPrimary]))
            ->assertOk()
            ->assertJsonPath('message', 'Primary image updated.')
            ->assertJsonPath('images.1.id', $newPrimary->id)
            ->assertJsonPath('images.1.is_primary', true);

        $this->assertDatabaseHas('product_images', [
            'id' => $oldPrimary->id,
            'is_primary' => false,
        ]);
        $this->assertDatabaseHas('product_images', [
            'id' => $newPrimary->id,
            'is_primary' => true,
        ]);
    }
}
