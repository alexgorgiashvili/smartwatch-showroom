<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        $products = Product::query()
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

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateProduct($request);
        $data['slug'] = $this->ensureSlug($data['slug'] ?? null, $data['name_en']);

        $product = Product::create($data);

        return redirect()->route('admin.products.edit', $product)
            ->with('status', 'Product created.');
    }

    public function edit(Product $product): View
    {
        $product->load('images');

        return view('admin.products.edit', [
            'product' => $product,
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validateProduct($request, $product->id);
        $data['slug'] = $this->ensureSlug($data['slug'] ?? null, $data['name_en'], $product->id);

        $product->update($data);

        return redirect()->route('admin.products.edit', $product)
            ->with('status', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('admin.products.index')
            ->with('status', 'Product deleted.');
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
