<?php

namespace Tests\Unit\Product;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\Product\VariantImageResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VariantImageResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        app()->setLocale('en');
    }

    public function test_duplicate_gallery_paths_do_not_shift_variant_image_indices(): void
    {
        Storage::disk('public')->put('products/red.jpg', 'red');
        Storage::disk('public')->put('products/blue.jpg', 'blue');

        $product = Product::query()->create([
            'name_en' => 'Resolver Watch',
            'name_ka' => 'Resolver Watch',
            'slug' => 'resolver-watch',
            'price' => 199,
            'currency' => 'GEL',
            'is_active' => true,
            'featured' => false,
        ]);

        ProductImage::query()->create([
            'product_id' => $product->id,
            'path' => 'products/red.jpg',
            'alt_en' => 'Red hero',
            'alt_ka' => 'Red hero',
            'sort_order' => 1,
            'is_primary' => true,
        ]);

        ProductImage::query()->create([
            'product_id' => $product->id,
            'path' => 'products/red.jpg',
            'alt_en' => 'Red duplicate',
            'alt_ka' => 'Red duplicate',
            'sort_order' => 2,
            'is_primary' => false,
        ]);

        ProductImage::query()->create([
            'product_id' => $product->id,
            'path' => 'products/blue.jpg',
            'alt_en' => 'Blue hero',
            'alt_ka' => 'Blue hero',
            'sort_order' => 3,
            'is_primary' => false,
        ]);

        $redVariant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Red Edition',
            'color_name' => 'Red',
            'color_hex' => '#FF0000',
            'quantity' => 5,
            'low_stock_threshold' => 1,
        ]);

        $blueVariant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Blue Edition',
            'color_name' => 'Blue',
            'color_hex' => '#0000FF',
            'quantity' => 5,
            'low_stock_threshold' => 1,
        ]);

        $resolved = app(VariantImageResolver::class)->resolve($product->fresh());

        $this->assertSame(2, $resolved['image_count']);
        $this->assertSame(0, $resolved['variant_images'][$redVariant->id]['index']);
        $this->assertSame(1, $resolved['variant_images'][$blueVariant->id]['index']);
        $this->assertSame('explicit', $resolved['variant_images'][$blueVariant->id]['strategy']);
    }

    public function test_explicit_variant_image_mapping_has_highest_priority(): void
    {
        Storage::disk('public')->put('products/generic-a.jpg', 'a');
        Storage::disk('public')->put('products/generic-b.jpg', 'b');

        $product = Product::query()->create([
            'name_en' => 'Mapped Watch',
            'name_ka' => 'Mapped Watch',
            'slug' => 'mapped-watch',
            'price' => 199,
            'currency' => 'GEL',
            'is_active' => true,
            'featured' => false,
        ]);

        $imageA = ProductImage::query()->create([
            'product_id' => $product->id,
            'path' => 'products/generic-a.jpg',
            'alt_en' => 'Generic A',
            'alt_ka' => 'Generic A',
            'sort_order' => 1,
            'is_primary' => true,
        ]);

        $imageB = ProductImage::query()->create([
            'product_id' => $product->id,
            'path' => 'products/generic-b.jpg',
            'alt_en' => 'Generic B',
            'alt_ka' => 'Generic B',
            'sort_order' => 2,
            'is_primary' => false,
        ]);

        $redVariant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Red Edition',
            'color_name' => 'Red',
            'color_hex' => '#FF0000',
            'quantity' => 5,
            'low_stock_threshold' => 1,
        ]);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Blue Edition',
            'color_name' => 'Blue',
            'color_hex' => '#0000FF',
            'quantity' => 5,
            'low_stock_threshold' => 1,
        ]);

        // Force a manual mapping that intentionally does not match by alias.
        $redVariant->images()->attach($imageB->id, ['sort_order' => 0]);

        $resolved = app(VariantImageResolver::class)->resolve($product->fresh());

        $this->assertSame($imageB->id, $resolved['variant_images'][$redVariant->id]['id']);
        $this->assertSame('mapped', $resolved['variant_images'][$redVariant->id]['strategy']);
        $this->assertNotSame($imageA->id, $resolved['variant_images'][$redVariant->id]['id']);
    }
}
