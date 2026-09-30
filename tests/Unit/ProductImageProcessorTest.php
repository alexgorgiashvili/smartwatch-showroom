<?php

namespace Tests\Unit;

use App\Services\AlibabaScraperService;
use App\Services\Product\ProductImageProcessor;
use Illuminate\Support\Facades\Http;
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

    public function test_alibaba_import_uses_the_same_optimized_images(): void
    {
        if (! extension_loaded('gd') || ! function_exists('imagewebp')) {
            $this->markTestSkipped('GD with WebP support is required.');
        }

        Storage::fake('public');
        $source = imagecreatetruecolor(1800, 900);
        ob_start();
        imagepng($source);
        $binary = ob_get_clean();
        imagedestroy($source);

        Http::fake(['https://example.com/watch.png' => Http::response($binary, 200, ['Content-Type' => 'image/png'])]);
        $images = app(AlibabaScraperService::class)->downloadImages(['https://example.com/watch.png'], 'watch-test');

        $this->assertCount(1, $images);
        $this->assertStringEndsWith('.webp', $images[0]['path']);
        Storage::disk('public')->assertExists([$images[0]['path'], $images[0]['thumbnail_path']]);
        $this->assertSame([1200, 600], array_slice(getimagesize(Storage::disk('public')->path($images[0]['path'])), 0, 2));
    }
}
