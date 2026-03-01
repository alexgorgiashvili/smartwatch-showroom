<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'images' => ['required', 'array', 'max:8'],
            'images.*' => ['file', 'image', 'max:4096'],
            'alt_en' => ['nullable', 'string', 'max:160'],
            'alt_ka' => ['nullable', 'string', 'max:160'],
        ]);

        foreach ($data['images'] as $index => $upload) {
            $path = $upload->store('images/products', 'public');
            $thumbnailPath = $this->createThumbnailForUpload($upload, $path);

            $product->images()->create([
                'path' => 'storage/' . $path,
                'thumbnail_path' => $thumbnailPath ? 'storage/' . $thumbnailPath : null,
                'alt_en' => $data['alt_en'] ?? null,
                'alt_ka' => $data['alt_ka'] ?? null,
                'sort_order' => $product->images()->count() + $index,
                'is_primary' => $product->images()->count() === 0 && $index === 0,
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Images uploaded.',
                'images' => $this->imagePayload($product),
            ]);
        }

        return redirect()->route('admin.products.edit', $product)
            ->with('status', 'Images uploaded.');
    }

    public function setPrimary(Product $product, ProductImage $image): RedirectResponse|JsonResponse
    {
        if ($image->product_id !== $product->id) {
            abort(404);
        }

        $product->images()->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);

        if (request()->expectsJson()) {
            return response()->json([
                'message' => 'Primary image updated.',
                'images' => $this->imagePayload($product),
            ]);
        }

        return redirect()->route('admin.products.edit', $product)
            ->with('status', 'Primary image updated.');
    }

    public function destroy(Product $product, ProductImage $image): RedirectResponse|JsonResponse
    {
        if ($image->product_id !== $product->id) {
            abort(404);
        }

        if (! empty($image->path) && str_starts_with($image->path, 'storage/')) {
            $storagePath = str_replace('storage/', '', $image->path);
            Storage::disk('public')->delete($storagePath);
        }

        if (! empty($image->thumbnail_path) && str_starts_with($image->thumbnail_path, 'storage/')) {
            $thumbStoragePath = str_replace('storage/', '', $image->thumbnail_path);
            Storage::disk('public')->delete($thumbStoragePath);
        }

        $image->delete();

        if (request()->expectsJson()) {
            return response()->json([
                'message' => 'Image deleted.',
                'images' => $this->imagePayload($product),
            ]);
        }

        return redirect()->route('admin.products.edit', $product)
            ->with('status', 'Image deleted.');
    }

    private function imagePayload(Product $product): array
    {
        $product->load('images');

        return $product->images->map(function (ProductImage $image) use ($product) {
            return [
                'id' => $image->id,
                'url' => $image->url,
                'thumbnail_url' => $image->thumbnail_url,
                'path' => $image->path,
                'thumbnail_path' => $image->thumbnail_path,
                'alt_en' => $image->alt_en,
                'alt_ka' => $image->alt_ka,
                'is_primary' => $image->is_primary,
                'primary_url' => route('admin.products.images.primary', [$product, $image]),
                'delete_url' => route('admin.products.images.destroy', [$product, $image]),
            ];
        })->values()->all();
    }

    private function createThumbnailForUpload($upload, string $mainPath): ?string
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

        $binary = @file_get_contents($upload->getRealPath());
        if (!is_string($binary) || $binary === '') {
            return null;
        }

        $source = @imagecreatefromstring($binary);
        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        if ($width <= 0 || $height <= 0) {
            imagedestroy($source);
            return null;
        }

        $target = imagecreatetruecolor(320, 320);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        imagefilledrectangle($target, 0, 0, 320, 320, $transparent);

        $sourceRatio = $width / $height;
        if ($sourceRatio > 1) {
            $cropHeight = $height;
            $cropWidth = (int) round($height);
            $srcX = (int) round(($width - $cropWidth) / 2);
            $srcY = 0;
        } else {
            $cropWidth = $width;
            $cropHeight = (int) round($width);
            $srcX = 0;
            $srcY = (int) round(($height - $cropHeight) / 2);
        }

        imagecopyresampled($target, $source, 0, 0, $srcX, $srcY, 320, 320, $cropWidth, $cropHeight);

        $extension = strtolower(pathinfo($mainPath, PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $extension = 'jpg';
        }

        ob_start();
        if ($extension === 'png') {
            imagepng($target, null, 6);
        } elseif ($extension === 'webp' && function_exists('imagewebp')) {
            imagewebp($target, null, 80);
        } else {
            imagejpeg($target, null, 82);
            if ($extension === 'webp') {
                $extension = 'jpg';
            }
        }
        $thumbBinary = ob_get_clean();

        imagedestroy($target);
        imagedestroy($source);

        if (!is_string($thumbBinary) || $thumbBinary === '') {
            return null;
        }

        $thumbnailPath = preg_replace('/\.[^.]+$/', '', $mainPath) . '_thumb.' . $extension;
        Storage::disk('public')->put($thumbnailPath, $thumbBinary);

        return $thumbnailPath;
    }
}
