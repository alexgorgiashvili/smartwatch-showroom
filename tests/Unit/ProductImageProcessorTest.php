<?php

namespace Tests\Unit;

use App\Services\Product\ProductImageProcessor;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageProcessorTest extends TestCase
{
    public function test_it_saves_bounded_webp_images_and_preserves_the_source(): void
    {
        if (! extension_loaded('gd') || ! function_exists('imagewebp')) {
            $this->markTestSkipped('GD with WebP support is required.');
        }

        Storage::fake('public');
        $source = imagecreatetruecolor(2400, 1600);
        $color = imagecolorallocate($source, 50, 100, 150);
        imagefill($source, 0, 0, $color);
        $sourcePath = tempnam(sys_get_temp_dir(), 'product-image-');
        imagepng($source, $sourcePath);
        imagedestroy($source);

        try {
            [$main, $thumbnail] = app(ProductImageProcessor::class)->storeFile($sourcePath);

            $this->assertStringEndsWith('.webp', $main);
            $this->assertStringEndsWith('_thumb.webp', $thumbnail);
            Storage::disk('public')->assertExists([$main, $thumbnail]);
            $this->assertSame([1200, 800], array_slice(getimagesize(Storage::disk('public')->path($main)), 0, 2));
            $this->assertSame([320, 213], array_slice(getimagesize(Storage::disk('public')->path($thumbnail)), 0, 2));
            $this->assertSame([2400, 1600], array_slice(getimagesize($sourcePath), 0, 2));
            $this->assertLessThan(filesize($sourcePath), Storage::disk('public')->size($main));
        } finally {
            unlink($sourcePath);
        }
    }
}
