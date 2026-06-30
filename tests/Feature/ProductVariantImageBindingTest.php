<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductVariantImageBindingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        app()->setLocale('en');
    }

    public function test_quick_review_payload_includes_variant_image_binding(): void
    {
        $product = $this->productWithVariantImages();

        $response = $this->getJson(route('products.quick-review', $product));

        $response->assertOk();
        $responseVariants = collect($response->json('variants'));
        $this->assertCount(2, $responseVariants);
        $response->assertJsonPath('product.name', 'Resolver Watch');
        $response->assertJsonPath('variants.0.image_url', Storage::url('products/red.jpg'));
        $response->assertJsonPath('variants.0.image_alt', 'Red hero');
        $response->assertJsonPath('variants.0.image_index', 0);
        $response->assertJsonPath('variants.1.image_url', Storage::url('products/blue.jpg'));
        $response->assertJsonPath('variants.1.image_alt', 'Blue hero');
        $response->assertJsonPath('variants.1.image_index', 1);
    }

    public function test_product_page_renders_swatches_with_image_metadata(): void
    {
        $product = $this->productWithVariantImages();

        $response = $this->get(route('products.show', $product));

        $response->assertOk();
        $response->assertSee('product-color-swatch', false);
        $response->assertSee('data-image-index="0"', false);
        $response->assertSee('data-image-index="1"', false);
        $response->assertSee('data-image-url="' . e(Storage::url('products/red.jpg')), false);
        $response->assertSee('data-image-url="' . e(Storage::url('products/blue.jpg')), false);
        $response->assertSee('data-variant-id="' . $product->variants->first()->id . '"', false);
    }

    public function test_quick_review_prefers_explicit_variant_mapping_over_alias_matching(): void
    {
        $product = $this->productWithVariantImages();
        $variants = $product->variants->values();
        $images = $product->images->values();

        $firstVariant = $variants->first();
        $secondImage = $images->get(1);

        $this->assertNotNull($firstVariant);
        $this->assertNotNull($secondImage);

        $firstVariant->images()->attach($secondImage->id, ['sort_order' => 0]);

        $response = $this->getJson(route('products.quick-review', $product));

        $response->assertOk();

        $variantPayload = collect($response->json('variants'))
            ->firstWhere('id', $firstVariant->id);

        $this->assertNotNull($variantPayload);
        $this->assertSame(Storage::url('products/blue.jpg'), $variantPayload['image_url']);
        $this->assertSame('Blue hero', $variantPayload['image_alt']);
    }

    private function productWithVariantImages(): Product
    {
        Storage::disk('public')->put('products/red.jpg', 'red');
        Storage::disk('public')->put('products/blue.jpg', 'blue');

        $product = Product::query()->create([
            'name_en' => 'Resolver Watch',
            'name_ka' => 'Resolver Watch',
            'slug' => 'resolver-watch-' . uniqid(),
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
            'path' => 'products/blue.jpg',
            'alt_en' => 'Blue hero',
            'alt_ka' => 'Blue hero',
            'sort_order' => 2,
            'is_primary' => false,
        ]);

        ProductVariant::query()->create([
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

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Green Edition',
            'color_name' => 'Green',
            'color_hex' => '#00FF00',
            'quantity' => 0,
            'low_stock_threshold' => 1,
        ]);

        return $product->fresh(['images', 'variants', 'primaryImage']);
    }
}
