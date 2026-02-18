<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
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

        return redirect()->route('admin.products.edit', $product)
            ->with('status', 'Images uploaded.');
    }

    public function setPrimary(Product $product, ProductImage $image): RedirectResponse
    {
        if ($image->product_id !== $product->id) {
            abort(404);
        }

        $product->images()->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);

        return redirect()->route('admin.products.edit', $product)
            ->with('status', 'Primary image updated.');
    }

    public function destroy(Product $product, ProductImage $image): RedirectResponse
    {
        if ($image->product_id !== $product->id) {
            abort(404);
        }

        if (! empty($image->path) && str_starts_with($image->path, 'storage/')) {
            $storagePath = str_replace('storage/', '', $image->path);
            Storage::disk('public')->delete($storagePath);
        }

        $image->delete();

        return redirect()->route('admin.products.edit', $product)
            ->with('status', 'Image deleted.');
    }
}
