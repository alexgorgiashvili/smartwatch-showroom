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

            $product->images()->create([
                'path' => 'storage/' . $path,
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
                'path' => $image->path,
                'alt_en' => $image->alt_en,
                'alt_ka' => $image->alt_ka,
                'is_primary' => $image->is_primary,
                'primary_url' => route('admin.products.images.primary', [$product, $image]),
                'delete_url' => route('admin.products.images.destroy', [$product, $image]),
            ];
        })->values()->all();
    }
}
