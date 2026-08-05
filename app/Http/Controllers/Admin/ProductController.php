<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\Chatbot\ChatbotContentSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::query()
            ->with(['primaryImage', 'images', 'variants'])
            ->withCount([
                'variants',
                'variants as listed_variants_count' => fn ($query) => $query->where('is_listed_separately', true),
            ])
            ->orderByDesc('updated_at')
            ->get();

        $view = view('admin.products.index', [
            'products' => $products,
        ]);

        return $this->renderPjaxView($request, $view);
    }

    public function create(Request $request)
    {
        $view = view('admin.products.create', [
            'product' => new Product(),
        ]);

        return $this->renderPjaxView($request, $view);
    }

    public function store(Request $request, ChatbotContentSyncService $contentSync): RedirectResponse|JsonResponse
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
                $thumbnailPath = $this->createThumbnailForUpload($upload, $path);

                $product->images()->create([
                    'path' => 'storage/' . $path,
                    'thumbnail_path' => $thumbnailPath ? 'storage/' . $thumbnailPath : null,
                    'alt_en' => $imageData['alt_en'] ?? null,
                    'alt_ka' => $imageData['alt_ka'] ?? null,
                    'sort_order' => $product->images()->count() + $index,
                    'is_primary' => $product->images()->count() === 0 && $index === 0,
                ]);
            }
        }

        $contentSync->syncProduct($product->fresh('variants'));

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

    public function edit(Request $request, Product $product)
    {
        $product->load(['images', 'variants.images']);

        $view = view('admin.products.edit', [
            'product' => $product,
        ]);

        return $this->renderPjaxView($request, $view);
    }

    public function update(Request $request, Product $product, ChatbotContentSyncService $contentSync): RedirectResponse|JsonResponse
    {
        $data = $this->validateProduct($request, $product->id);
        $data['slug'] = $this->ensureSlug($data['slug'] ?? null, $data['name_en'], $product->id);

        $product->update($data);

        $contentSync->syncProduct($product->fresh('variants'));

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Product updated.',
                'product_id' => $product->id,
            ]);
        }

        return redirect()->route('admin.products.edit', $product)
            ->with('status', 'Product updated.');
    }

    public function destroy(Request $request, Product $product, ChatbotContentSyncService $contentSync): RedirectResponse|JsonResponse
    {
        if ($this->productHasOrderHistory($product)) {
            $message = 'This product cannot be deleted because it is already used in one or more orders.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 409);
            }

            return redirect()->route('admin.products.edit', $product)
                ->with('status', $message);
        }

        $imagePaths = $product->images()
            ->get(['path', 'thumbnail_path'])
            ->map(function ($image): array {
                return [
                    'path' => $image->path,
                    'thumbnail_path' => $image->thumbnail_path,
                ];
            })
            ->all();

        $contentSync->deactivateProduct($product);

        DB::transaction(function () use ($product): void {
            $product->delete();
        });

        $this->deleteProductMediaFiles($imagePaths);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Product deleted.',
                'redirect' => route('admin.products.index'),
            ]);
        }

        return redirect()->route('admin.products.index')
            ->with('status', 'Product deleted.');
    }

    public function storeVariant(
        Request $request,
        Product $product,
        ChatbotContentSyncService $contentSync
    ): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'name_en' => ['required', 'string', 'max:160'],
            'color_name' => ['nullable', 'string', 'max:50'],
            'color_name_en' => ['nullable', 'string', 'max:50'],
            'color_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_listed_separately' => ['nullable', 'boolean'],
            'quantity' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
            'bridge_variation_id' => ['nullable', 'string', 'max:120'],
            'bridge_sku' => ['nullable', 'string', 'max:120'],
            'bridge_stock_quantity' => ['nullable', 'integer', 'min:0'],
            'bridge_stock_status' => ['nullable', 'in:instock,outofstock,onbackorder'],
            'stock_sync_status' => ['nullable', 'in:pending_review,synced,stale,sync_failed'],
        ]);

        $data['color_hex'] = isset($data['color_hex']) ? strtoupper($data['color_hex']) : null;
        $data['is_listed_separately'] = $request->boolean('is_listed_separately');

        $data['product_id'] = $product->id;

        $variant = ProductVariant::create($data);

        $contentSync->syncProduct($product->fresh('variants'));

        return response()->json([
            'success' => true,
            'message' => 'Variant added successfully.',
            'variant' => $this->buildVariantPayload($variant),
        ]);
    }

    public function updateVariant(
        Request $request,
        ProductVariant $variant,
        ChatbotContentSyncService $contentSync
    ): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'name_en' => ['required', 'string', 'max:160'],
            'color_name' => ['nullable', 'string', 'max:50'],
            'color_name_en' => ['nullable', 'string', 'max:50'],
            'color_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_listed_separately' => ['nullable', 'boolean'],
            'quantity' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
            'bridge_variation_id' => ['nullable', 'string', 'max:120'],
            'bridge_sku' => ['nullable', 'string', 'max:120'],
            'bridge_stock_quantity' => ['nullable', 'integer', 'min:0'],
            'bridge_stock_status' => ['nullable', 'in:instock,outofstock,onbackorder'],
            'stock_sync_status' => ['nullable', 'in:pending_review,synced,stale,sync_failed'],
        ]);

        $data['color_hex'] = isset($data['color_hex']) ? strtoupper($data['color_hex']) : null;
    $data['is_listed_separately'] = $request->boolean('is_listed_separately');

        $variant->update($data);

        $product = $variant->product()->with('variants')->first();
        if ($product) {
            $contentSync->syncProduct($product);
        }

        return response()->json([
            'success' => true,
            'message' => 'Variant updated successfully.',
            'variant' => $this->buildVariantPayload($variant),
        ]);
    }

    public function toggleVariantListing(
        Request $request,
        ProductVariant $variant,
        ChatbotContentSyncService $contentSync
    ): JsonResponse
    {
        $data = $request->validate([
            'is_listed_separately' => ['required', 'boolean'],
        ]);

        $variant->update([
            'is_listed_separately' => (bool) $data['is_listed_separately'],
        ]);

        $product = $variant->product()->with('variants')->first();
        if ($product) {
            $contentSync->syncProduct($product);
        }

        return response()->json([
            'success' => true,
            'message' => 'Catalog listing visibility updated.',
            'variant' => $this->buildVariantPayload($variant),
        ]);
    }

    public function syncVariantImages(Request $request, ProductVariant $variant): JsonResponse
    {
        $data = $request->validate([
            'image_ids' => ['nullable', 'array'],
            'image_ids.*' => ['integer', 'distinct', 'exists:product_images,id'],
        ]);

        $imageIds = collect($data['image_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($imageIds->isNotEmpty()) {
            $validIds = ProductImage::query()
                ->where('product_id', $variant->product_id)
                ->whereIn('id', $imageIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values();

            if ($validIds->count() !== $imageIds->count()) {
                return response()->json([
                    'message' => 'All selected images must belong to the same product as the variant.',
                ], 422);
            }
        }

        $syncPayload = [];
        foreach ($imageIds as $index => $imageId) {
            $syncPayload[$imageId] = ['sort_order' => $index];
        }

        $variant->images()->sync($syncPayload);

        return response()->json([
            'success' => true,
            'message' => 'Variant image mapping saved.',
            'variant' => $this->buildVariantPayload($variant->fresh()),
        ]);
    }

    public function deleteVariant(Request $request, ProductVariant $variant, ChatbotContentSyncService $contentSync): JsonResponse
    {
        if ($variant->orderItems()->exists()) {
            return response()->json([
                'message' => 'This variant cannot be deleted because it is already used in one or more orders.',
            ], 409);
        }

        $product = $variant->product()->with('variants')->first();
        $variant->delete();

        if ($product) {
            $contentSync->syncProduct($product->fresh('variants'));
        }

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
            'meta_title_ka' => ['nullable', 'string', 'max:160'],
            'meta_title_en' => ['nullable', 'string', 'max:160'],
            'meta_description_ka' => ['nullable', 'string', 'max:160'],
            'meta_description_en' => ['nullable', 'string', 'max:160'],
            'description_en' => ['nullable', 'string'],
            'description_ka' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:3'],
            'sim_support' => ['nullable', 'boolean'],
            'gps_features' => ['nullable', 'boolean'],
            'water_resistant' => ['nullable', 'string', 'max:50'],
            'battery_life_range' => ['nullable', 'string', 'max:20'],
            'battery_life_hours' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'warranty_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'operating_system' => ['nullable', 'string', 'max:100'],
            'screen_size' => ['nullable', 'string', 'max:100'],
            'display_type' => ['nullable', 'string', 'max:100'],
            'screen_resolution' => ['nullable', 'string', 'max:100'],
            'battery_capacity_mah' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'charging_time_hours' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'case_material' => ['nullable', 'string', 'max:100'],
            'band_material' => ['nullable', 'string', 'max:100'],
            'camera' => ['nullable', 'string', 'max:100'],
            'functions' => ['nullable'],
            'fulfillment_mode' => ['nullable', 'in:local_stock,dropship_bridge'],
            'bridge_product_id' => ['nullable', 'string', 'max:120'],
            'bridge_product_permalink' => ['nullable', 'url', 'max:2000'],
            'product_sync_status' => ['nullable', 'in:pending_review,synced,stale,sync_failed'],
            'is_active' => ['nullable', 'boolean'],
            'featured' => ['nullable', 'boolean'],
            'home_sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'gift_builder_enabled' => ['nullable', 'boolean'],
            'gift_builder_role' => ['nullable', 'in:none,main,addon,both'],
            'gift_recipient_tags' => ['nullable'],
            'gift_occasion_tags' => ['nullable'],
            'gift_budget_band' => ['nullable', 'in:' . implode(',', array_keys((array) config('gift_builder.budget_bands', [])))],
            'gift_compatibility_tags' => ['nullable'],
            'gift_capacity_units' => ['nullable', 'integer', 'min:1', 'max:20'],
            'gift_badge_ka' => ['nullable', 'string', 'max:80'],
            'gift_badge_en' => ['nullable', 'string', 'max:80'],
            'gift_builder_note_ka' => ['nullable', 'string', 'max:255'],
            'gift_builder_note_en' => ['nullable', 'string', 'max:255'],
            'gift_sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $data['sim_support'] = $request->boolean('sim_support');
        $data['gps_features'] = $request->boolean('gps_features');
        $data['is_active'] = $request->boolean('is_active');
        $data['featured'] = $request->boolean('featured');
        $data['home_sort_order'] = max(0, (int) ($request->input('home_sort_order') ?: 0));
        $data['currency'] = 'GEL';
        $data['battery_life_range'] = $this->normalizeBatteryLifeRange($request->input('battery_life_range'));
        $data['functions'] = $this->normalizeFunctions($request->input('functions'));
        $data['fulfillment_mode'] = $request->input('fulfillment_mode', $productId ? 'local_stock' : 'local_stock');
        $data['gift_builder_enabled'] = $request->boolean('gift_builder_enabled');
        $data['gift_builder_role'] = $request->input('gift_builder_role', 'none') ?: 'none';
        $data['gift_recipient_tags'] = $this->normalizeTags($request->input('gift_recipient_tags'));
        $data['gift_occasion_tags'] = $this->normalizeTags($request->input('gift_occasion_tags'));
        $data['gift_budget_band'] = $request->input('gift_budget_band') ?: 'all';
        $data['gift_compatibility_tags'] = $this->normalizeTags($request->input('gift_compatibility_tags'));
        $data['gift_capacity_units'] = max(1, (int) ($request->input('gift_capacity_units') ?: 1));
        $data['gift_sort_order'] = max(0, (int) ($request->input('gift_sort_order') ?: 0));

        if (! Schema::hasColumn('products', 'battery_life_range')) {
            unset($data['battery_life_range']);
        }
        if (! Schema::hasColumn('products', 'home_sort_order')) {
            unset($data['home_sort_order']);
        }

        return $data;
    }

    private function normalizeTags(mixed $value): ?array
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $text = trim((string) ($value ?? ''));
            if ($text === '') {
                return null;
            }

            $items = preg_split('/[,\n]+/', $text) ?: [];
        }

        $normalized = [];
        foreach ($items as $item) {
            $clean = Str::slug(trim((string) $item), '_');
            if ($clean !== '') {
                $normalized[] = Str::limit($clean, 80, '');
            }
        }

        $normalized = array_values(array_unique($normalized));

        return $normalized === [] ? null : $normalized;
    }

    private function normalizeFunctions(mixed $value): ?array
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $text = trim((string) ($value ?? ''));
            if ($text === '') {
                return null;
            }

            $items = preg_split('/[,\n]+/', $text) ?: [];
        }

        $normalized = [];
        foreach ($items as $item) {
            $clean = trim((string) $item);
            if ($clean !== '') {
                $normalized[] = Str::limit($clean, 100, '');
            }
        }

        $normalized = array_values(array_unique($normalized));

        return $normalized === [] ? null : $normalized;
    }

    private function normalizeBatteryLifeRange(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }

        if (! preg_match('/\d+(?:\s*[-–]\s*\d+)?/', $text, $matches)) {
            return null;
        }

        return preg_replace('/\s*[-–]\s*/', '-', $matches[0]);
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

    private function productHasOrderHistory(Product $product): bool
    {
        return $product->variants()
            ->whereHas('orderItems')
            ->exists();
    }

    private function deleteProductMediaFiles(array $imagePaths): void
    {
        foreach ($imagePaths as $image) {
            $path = $image['path'] ?? null;
            if (is_string($path) && $path !== '' && str_starts_with($path, 'storage/')) {
                Storage::disk('public')->delete(str_replace('storage/', '', $path));
            }

            $thumbnailPath = $image['thumbnail_path'] ?? null;
            if (is_string($thumbnailPath) && $thumbnailPath !== '' && str_starts_with($thumbnailPath, 'storage/')) {
                Storage::disk('public')->delete(str_replace('storage/', '', $thumbnailPath));
            }
        }
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

    private function buildVariantPayload(ProductVariant $variant): array
    {
        $variant->loadMissing(['images']);

        return [
            'id' => (int) $variant->id,
            'product_id' => (int) $variant->product_id,
            'name' => (string) $variant->name,
            'name_en' => (string) $variant->name_en,
            'color_name' => $variant->color_name,
            'color_name_en' => $variant->color_name_en,
            'color_hex' => $variant->color_hex ? strtoupper((string) $variant->color_hex) : null,
            'quantity' => (int) $variant->quantity,
            'low_stock_threshold' => (int) $variant->low_stock_threshold,
            'available_quantity' => (int) $variant->available_quantity,
            'bridge_stock_quantity' => $variant->bridge_stock_quantity,
            'stock_sync_status' => $variant->stock_sync_status,
            'bridge_variation_id' => $variant->bridge_variation_id,
            'is_listed_separately' => (bool) $variant->is_listed_separately,
            'mapped_images_count' => $variant->images->count(),
            'mapped_image_ids' => $variant->images->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'toggle_listing_url' => route('admin.products.variants.toggle-listing', $variant),
            'sync_images_url' => route('admin.products.variants.images.sync', $variant),
            'delete_url' => route('admin.products.variants.delete', $variant),
        ];
    }
}
