<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        $products = Product::query()
            ->with(['primaryImage', 'images'])
            ->orderByDesc('updated_at')
            ->get();

        return view('admin.products.index', [
            'products' => $products,
        ]);
    }

    public function create(): View
    {
        return view('admin.products.create', [
            'product' => new Product(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->validateProduct($request);
        $data['slug'] = $this->ensureSlug($data['slug'] ?? null, $data['name_en']);

        $imageData = $request->validate([
            'images' => ['nullable', 'array', 'max:8'],
            'images.*' => ['file', 'image', 'max:4096'],
            'alt_en' => ['nullable', 'string', 'max:160'],
            'alt_ka' => ['nullable', 'string', 'max:160'],
        ]);

        $product = Product::create($data);

        if (!empty($imageData['images'])) {
            foreach ($imageData['images'] as $index => $upload) {
                $path = $upload->store('images/products', 'public');

                $product->images()->create([
                    'path' => 'storage/' . $path,
                    'alt_en' => $imageData['alt_en'] ?? null,
                    'alt_ka' => $imageData['alt_ka'] ?? null,
                    'sort_order' => $product->images()->count() + $index,
                    'is_primary' => $product->images()->count() === 0 && $index === 0,
                ]);
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Product created.',
                'redirect' => route('admin.products.edit', $product),
                'product_id' => $product->id,
            ]);
        }

        return redirect()->route('admin.products.edit', $product)
            ->with('status', 'Product created.');
    }

    public function edit(Product $product): View
    {
        $product->load(['images', 'variants']);

        return view('admin.products.edit', [
            'product' => $product,
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $data = $this->validateProduct($request, $product->id);
        $data['slug'] = $this->ensureSlug($data['slug'] ?? null, $data['name_en'], $product->id);

        $product->update($data);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Product updated.',
                'product_id' => $product->id,
            ]);
        }

        return redirect()->route('admin.products.edit', $product)
            ->with('status', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('admin.products.index')
            ->with('status', 'Product deleted.');
    }

    public function storeVariant(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'quantity' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
        ]);

        $data['product_id'] = $product->id;

        $variant = ProductVariant::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Variant added successfully.',
            'variant' => $variant,
        ]);
    }

    public function updateVariant(Request $request, ProductVariant $variant): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'quantity' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
        ]);

        $variant->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Variant updated successfully.',
            'variant' => $variant,
        ]);
    }

    public function deleteVariant(ProductVariant $variant): JsonResponse
    {
        $variant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Variant deleted successfully.',
        ]);
    }

    private function validateProduct(Request $request, ?int $productId = null): array
    {
        $data = $request->validate([
            'name_en' => ['required', 'string', 'max:160'],
            'name_ka' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:200'],
            'short_description_en' => ['nullable', 'string', 'max:255'],
            'short_description_ka' => ['nullable', 'string', 'max:255'],
            'description_en' => ['nullable', 'string'],
            'description_ka' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:3'],
            'sim_support' => ['nullable', 'boolean'],
            'gps_features' => ['nullable', 'boolean'],
            'water_resistant' => ['nullable', 'string', 'max:50'],
            'battery_life_hours' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'warranty_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
            'featured' => ['nullable', 'boolean'],
        ]);

        $data['sim_support'] = $request->boolean('sim_support');
        $data['gps_features'] = $request->boolean('gps_features');
        $data['is_active'] = $request->boolean('is_active');
        $data['featured'] = $request->boolean('featured');
        $data['currency'] = $data['currency'] ?: 'GEL';

        return $data;
    }

    private function ensureSlug(?string $slug, string $name, ?int $productId = null): string
    {
        $baseSlug = $slug ? Str::slug($slug) : Str::slug($name);
        $candidate = $baseSlug;
        $counter = 1;

        while ($this->slugExists($candidate, $productId)) {
            $candidate = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $candidate;
    }

    private function slugExists(string $slug, ?int $productId = null): bool
    {
        $query = Product::where('slug', $slug);

        if ($productId) {
            $query->where('id', '!=', $productId);
        }

        return $query->exists();
    }
}
