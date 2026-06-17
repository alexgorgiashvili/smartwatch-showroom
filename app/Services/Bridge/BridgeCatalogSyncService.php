<?php

namespace App\Services\Bridge;

use App\Models\Product;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class BridgeCatalogSyncService
{
    public function __construct(
        private readonly WooBridgeService $bridge
    ) {
    }

    public function syncProduct(int $remoteProductId): Product
    {
        $remoteProduct = $this->bridge->getProduct($remoteProductId);
        $remoteVariations = [];

        if (($remoteProduct['type'] ?? 'simple') === 'variable') {
            $remoteVariations = $this->bridge->getProductVariations($remoteProductId);
        }

        $product = Product::query()->firstOrNew([
            'external_source' => 'woo_bridge',
            'external_product_id' => (string) $remoteProductId,
        ]);

        $name = trim((string) ($remoteProduct['name'] ?? ('Bridge Product ' . $remoteProductId)));
        $description = (string) ($remoteProduct['description'] ?? '');
        $shortDescription = $this->summarizeText(
            (string) ($remoteProduct['short_description'] ?? strip_tags($description)),
            255
        );

        $priceUsd = $this->toFloat($remoteProduct['price'] ?? null);
        $salePriceUsd = $this->toFloat($remoteProduct['sale_price'] ?? null);

        $product->fill([
            'name_en' => $name,
            'name_ka' => $product->exists && filled($product->name_ka) ? $product->name_ka : $name,
            'slug' => $this->uniqueSlug($product, (string) ($remoteProduct['slug'] ?? $name)),
            'external_source' => 'woo_bridge',
            'external_source_url' => $remoteProduct['permalink'] ?? null,
            'external_product_id' => (string) $remoteProductId,
            'fulfillment_mode' => 'dropship_bridge',
            'bridge_product_id' => (string) $remoteProductId,
            'bridge_product_permalink' => $remoteProduct['permalink'] ?? null,
            'product_sync_status' => 'synced',
            'product_synced_at' => now(),
            'short_description_en' => $shortDescription,
            'short_description_ka' => $product->exists && filled($product->short_description_ka)
                ? $product->short_description_ka
                : $shortDescription,
            'description_en' => $description,
            'description_ka' => $product->exists && filled($product->description_ka)
                ? $product->description_ka
                : $description,
            'price' => $this->convertUsdToGel($priceUsd),
            'sale_price' => $salePriceUsd ? $this->convertUsdToGel($salePriceUsd) : null,
            'currency' => 'GEL',
            'sim_support' => $this->containsAny($description, ['sim', 'gsm']),
            'gps_features' => $this->containsAny($description, ['gps', 'location tracker', 'lbs']),
            'water_resistant' => $this->extractValue($description, 'Water Resistance Depth'),
            'brand' => $this->normalizeBrand($this->extractValue($description, 'Brand Name')),
            'model' => Arr::get($remoteProduct, 'sku') ?: ('bridge-' . $remoteProductId),
            'camera' => $this->extractValue($description, 'Camera resolution'),
            'functions' => $this->extractFunctions($description),
            'featured' => false,
            'is_active' => false,
        ]);

        $product->save();

        $this->syncImages($product, $remoteProduct);
        $this->syncVariants($product, $remoteProduct, $remoteVariations);

        return $product->fresh(['images', 'variants']);
    }

    public function syncProducts(array $remoteProductIds): int
    {
        $count = 0;

        foreach ($remoteProductIds as $remoteProductId) {
            $this->syncProduct((int) $remoteProductId);
            $count++;
        }

        return $count;
    }

    private function syncImages(Product $product, array $remoteProduct): void
    {
        $product->images()->delete();

        foreach (array_slice($remoteProduct['images'] ?? [], 0, 8) as $index => $image) {
            $src = trim((string) ($image['src'] ?? ''));
            if ($src === '') {
                continue;
            }

            $product->images()->create([
                'path' => $src,
                'thumbnail_path' => $image['thumbnail'] ?? $src,
                'alt_en' => $image['alt'] ?: $product->name_en,
                'alt_ka' => $image['alt'] ?: ($product->name_ka ?: $product->name_en),
                'sort_order' => $index,
                'is_primary' => $index === 0,
            ]);
        }
    }

    private function syncVariants(Product $product, array $remoteProduct, array $remoteVariations): void
    {
        $existingVariantIds = [];

        if ($remoteVariations !== []) {
            foreach ($remoteVariations as $variation) {
                $name = collect($variation['attributes'] ?? [])
                    ->map(fn (array $attribute) => trim((string) ($attribute['option'] ?? '')))
                    ->filter()
                    ->join(' / ');

                $variant = $product->variants()->updateOrCreate([
                    'bridge_variation_id' => (string) ($variation['id'] ?? ''),
                ], [
                    'name' => $name !== '' ? $name : ('Variant ' . ($variation['id'] ?? '')),
                    'color_name' => null,
                    'color_hex' => null,
                    'quantity' => (int) ($variation['stock_quantity'] ?? 0),
                    'low_stock_threshold' => 2,
                    'bridge_sku' => $variation['sku'] ?? null,
                    'bridge_stock_quantity' => (int) ($variation['stock_quantity'] ?? 0),
                    'bridge_stock_status' => $variation['stock_status'] ?? ($this->toFloat($variation['stock_quantity'] ?? null) > 0 ? 'instock' : 'outofstock'),
                    'stock_sync_status' => 'synced',
                    'stock_synced_at' => now(),
                ]);

                $existingVariantIds[] = $variant->id;
            }

            $product->variants()
                ->whereNotIn('id', $existingVariantIds)
                ->whereNotNull('bridge_variation_id')
                ->delete();

            return;
        }

        $variant = $product->variants()->firstOrNew([
            'bridge_variation_id' => (string) $remoteProduct['id'],
        ]);

        $variant->fill([
            'name' => 'Default',
            'color_name' => null,
            'color_hex' => null,
            'quantity' => (int) ($remoteProduct['stock_quantity'] ?? 0),
            'low_stock_threshold' => 2,
            'bridge_sku' => $remoteProduct['sku'] ?? null,
            'bridge_stock_quantity' => (int) ($remoteProduct['stock_quantity'] ?? 0),
            'bridge_stock_status' => $remoteProduct['stock_status'] ?? ($this->toFloat($remoteProduct['stock_quantity'] ?? null) > 0 ? 'instock' : 'outofstock'),
            'stock_sync_status' => 'synced',
            'stock_synced_at' => now(),
        ]);
        $variant->save();

        $product->variants()
            ->where('id', '!=', $variant->id)
            ->whereNotNull('bridge_variation_id')
            ->delete();
    }

    private function uniqueSlug(Product $product, string $value): string
    {
        $baseSlug = Str::slug($value !== '' ? $value : $product->name_en);
        if ($baseSlug === '') {
            $baseSlug = 'bridge-product-' . ($product->external_product_id ?: 'item');
        }

        $candidate = $baseSlug;
        $counter = 1;

        while (Product::query()
            ->where('slug', $candidate)
            ->when($product->exists, fn ($query) => $query->where('id', '!=', $product->id))
            ->exists()) {
            $candidate = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $candidate;
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function convertUsdToGel(?float $value): ?float
    {
        if ($value === null) {
            return null;
        }

        return round($value * (float) config('services.bridge.usd_to_gel', 2.75), 2);
    }

    private function summarizeText(string $text, int $limit): ?string
    {
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');

        if ($plain === '') {
            return null;
        }

        return Str::limit($plain, $limit, '');
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        $normalized = Str::lower(strip_tags($haystack));

        foreach ($needles as $needle) {
            if (Str::contains($normalized, Str::lower($needle))) {
                return true;
            }
        }

        return false;
    }

    private function extractValue(string $html, string $label): ?string
    {
        $pattern = '/' . preg_quote($label, '/') . '\s*:\s*([^<\n\r]+)/i';
        if (! preg_match($pattern, $html, $matches)) {
            return null;
        }

        $value = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $value !== '' ? Str::limit($value, 100, '') : null;
    }

    private function normalizeBrand(?string $brand): ?string
    {
        if (! $brand || strtoupper($brand) === 'NONE') {
            return null;
        }

        return $brand;
    }

    private function extractFunctions(string $html): ?array
    {
        if (! preg_match('/Main functions\s*:\s*([^<]+)/i', $html, $matches)) {
            return null;
        }

        $items = preg_split('/[,;]+/', $matches[1]) ?: [];
        $functions = collect($items)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->take(10)
            ->values()
            ->all();

        return $functions !== [] ? $functions : null;
    }
}
