<?php

namespace App\Services\Product;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ProductImageProcessor
{
    public function storeUpload(UploadedFile $upload, string $directory = 'images/products'): array
    {
        return $this->storeFile($upload->getRealPath(), $directory);
    }

    public function storeFile(string $sourcePath, string $directory = 'images/products'): array
    {
        if (! extension_loaded('gd') || ! function_exists('imagewebp')) {
            throw new RuntimeException('GD with WebP support is required for product images.');
        }

        $dimensions = @getimagesize($sourcePath);
        if ($dimensions === false || $dimensions[0] * $dimensions[1] > 30000000) {
            throw new RuntimeException('Product image exceeds the supported pixel dimensions.');
        }

        $source = @imagecreatefromstring((string) @file_get_contents($sourcePath));
        if ($source === false) {
            throw new RuntimeException('Could not decode the uploaded product image.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        if ($width < 1 || $height < 1) {
            imagedestroy($source);
            throw new RuntimeException('Invalid product image dimensions.');
        }

        $base = trim($directory, '/').'/'.Str::uuid();
        $mainPath = $base.'.webp';
        $thumbnailPath = $base.'_thumb.webp';

        try {
            $main = $this->fitInside($source, $width, $height, 1200);
            $thumbnail = $this->fitInside($source, $width, $height, 320);

            $mainBinary = $this->encodeWebp($main, 80);
            $thumbnailBinary = $this->encodeWebp($thumbnail, 78);

            imagedestroy($main);
            imagedestroy($thumbnail);

            if (! Storage::disk('public')->put($mainPath, $mainBinary)) {
                throw new RuntimeException('Could not store product image.');
            }
            if (! Storage::disk('public')->put($thumbnailPath, $thumbnailBinary)) {
                throw new RuntimeException('Could not store product thumbnail.');
            }

            return [$mainPath, $thumbnailPath];
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete([$mainPath, $thumbnailPath]);
            throw $exception;
        } finally {
            imagedestroy($source);
        }
    }

    private function fitInside(\GdImage $source, int $width, int $height, int $limit): \GdImage
    {
        $scale = min(1, $limit / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);

        if (! imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height)) {
            imagedestroy($target);
            throw new RuntimeException('Could not resize product image.');
        }

        return $target;
    }

    private function encodeWebp(\GdImage $image, int $quality): string
    {
        ob_start();
        $success = imagewebp($image, null, $quality);
        $binary = ob_get_clean();

        if (! $success || ! is_string($binary) || $binary === '') {
            throw new RuntimeException('Could not encode product image as WebP.');
        }

        return $binary;
    }
}
