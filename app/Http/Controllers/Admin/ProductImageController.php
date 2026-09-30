<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Product\ProductImageProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    public function store(Request $request, Product $product, ProductImageProcessor $imageProcessor): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'images' => ['required', 'array', 'max:8'],
            'images.*' => ['file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
            'alt_en' => ['nullable', 'string', 'max:160'],
            'alt_ka' => ['nullable', 'string', 'max:160'],
        ]);

        foreach ($data['images'] as $index => $upload) {
            [$path, $thumbnailPath] = $imageProcessor->storeUpload($upload);

            $product->images()->create([
                'path' => 'storage/' . $path,
                'thumbnail_path' => 'storage/' . $thumbnailPath,
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

    /**
     * Get images for a specific product as JSON (used by Image Manager)
     */
    public function getImagesJson(Product $product): JsonResponse
    {
        return response()->json([
            'images' => $this->imagePayload($product)
        ]);
    }

    /**
     * Get all images across all products with filtering options (used by Image Manager)
     */
    public function getAllImagesJson(Request $request): JsonResponse
    {
        $query = ProductImage::with('product');

        // Filter by specific product if requested
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Filter by time (recent)
        if ($request->filled('time_filter')) {
            switch ($request->time_filter) {
                case 'today':
                    $query->where('created_at', '>=', now()->startOfDay());
                    break;
                case 'week':
                    $query->where('created_at', '>=', now()->subWeek());
                    break;
                case 'month':
                    $query->where('created_at', '>=', now()->subMonth());
                    break;
            }
        }

        $images = $query->orderByDesc('created_at')->paginate(50);

        $payload = $images->map(function (ProductImage $image) {
            return [
                'id' => $image->id,
                'product_id' => $image->product_id,
                'product_name' => $image->product ? ($image->product->name_en ?: $image->product->name_ka) : 'Unknown',
                'url' => $image->url,
                'thumbnail_url' => $image->thumbnail_url,
                'created_at' => $image->created_at->diffForHumans(),
            ];
        });

        return response()->json([
            'images' => $payload,
            'current_page' => $images->currentPage(),
            'last_page' => $images->lastPage(),
            'total' => $images->total()
        ]);
    }

    /**
     * Upload a standalone image (e.g. from Cropper)
     */
    public function uploadStandalone(Request $request, ProductImageProcessor $imageProcessor): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
        ]);

        $upload = $request->file('image');
        [$path, $thumbnailPath] = $imageProcessor->storeUpload($upload, 'images/standalone');

        $url = asset('storage/' . $path);
        $thumbnailUrl = asset('storage/' . $thumbnailPath);

        // If a product_id is provided, attach it to the product
        if ($request->filled('product_id')) {
            $product = Product::find($request->product_id);
            if ($product) {
                $product->images()->create([
                    'path' => 'storage/' . $path,
                    'thumbnail_path' => 'storage/' . $thumbnailPath,
                    'sort_order' => $product->images()->count(),
                    'is_primary' => $product->images()->count() === 0,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'url' => $url,
            'thumbnail_url' => $thumbnailUrl,
            'path' => $path
        ]);
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


}
