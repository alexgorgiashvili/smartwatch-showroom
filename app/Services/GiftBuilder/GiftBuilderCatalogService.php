<?php

namespace App\Services\GiftBuilder;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class GiftBuilderCatalogService
{
    public function builderConfig(Request $request): array
    {
        $products = $this->products([
            'role' => 'all',
            'budget_band' => 'all',
        ]);

        $preselected = $this->preselectedProduct($request);
        $readyBox = $preselected ? null : $this->readyBoxSelection($request, $products);

        return [
            'maxItems' => (int) config('gift_builder.max_items', 4),
            'messageMaxLength' => (int) config('gift_builder.message_max_length', 300),
            'recipients' => $this->localizedConfig('gift_builder.recipients'),
            'occasions' => $this->localizedConfig('gift_builder.occasions'),
            'budgetBands' => $this->localizedConfig('gift_builder.budget_bands'),
            'packaging' => $this->localizedConfig('gift_builder.packaging'),
            'presets' => $this->localizedConfig('gift_builder.presets'),
            'products' => $products->values()->all(),
            'initial' => [
                'recipient_type' => $preselected['recipient_type'] ?? null,
                'occasion' => $preselected['occasion'] ?? null,
                'budget_band' => $preselected['budget_band'] ?? $readyBox['budget_band'] ?? 'under_250',
                'packaging_slug' => $preselected['packaging_slug'] ?? $readyBox['packaging_slug'] ?? 'standard',
                'selected_variant_id' => $preselected['selected_variant_id'] ?? $readyBox['selected_variant_id'] ?? null,
                'addon_variant_ids' => $readyBox['addon_variant_ids'] ?? [],
                'ready_box' => $readyBox['slug'] ?? null,
                'template' => $request->query('template'),
            ],
            'routes' => [
                'boxes' => route('gift-builder.boxes'),
                'products' => route('gift-builder.products'),
                'price' => route('gift-builder.price'),
                'addToCart' => route('gift-builder.add-to-cart'),
                'cart' => route('cart.index'),
            ],
        ];
    }

    public function readyBoxes(): array
    {
        $products = $this->products([
            'role' => 'all',
            'budget_band' => 'all',
        ])->keyBy('slug');
        $packaging = collect($this->localizedConfig('gift_builder.packaging'))->keyBy('slug');

        return collect($this->localizedConfig('gift_builder.ready_boxes'))
            ->map(function (array $box) use ($products, $packaging): ?array {
                $main = $products->get($box['main_product'] ?? '');
                $addonSlugs = array_values((array) ($box['addon_products'] ?? []));
                $addons = collect($addonSlugs)->map(fn (string $slug) => $products->get($slug))->filter()->values();

                if (
                    ! $main
                    || ! in_array($main['role'], ['main', 'both'], true)
                    || count($addonSlugs) !== $addons->count()
                    || $addons->contains(fn (array $product): bool => ! in_array($product['role'], ['addon', 'both'], true))
                ) {
                    return null;
                }

                $package = $packaging->get($box['packaging_slug'] ?? 'standard');
                $items = collect([$main])->concat($addons)->values();

                return array_merge($box, [
                    'items' => $items->all(),
                    'total' => $items->sum(fn (array $product): float => (float) $product['price']) + (float) ($package['price'] ?? 0),
                    'packaging_label' => $package['label'] ?? '',
                    'builder_url' => route('gift-builder.show', ['box' => $box['slug']]),
                ]);
            })
            ->filter()
            ->values()
            ->all();
    }

    public function products(array $filters = []): Collection
    {
        $role = (string) ($filters['role'] ?? 'all');
        $recipient = $filters['recipient_type'] ?? null;
        $occasion = $filters['occasion'] ?? null;
        $budget = (string) ($filters['budget_band'] ?? 'all');

        return Product::query()
            ->active()
            ->where('gift_builder_enabled', true)
            ->where('fulfillment_mode', 'local_stock')
            ->with(['primaryImage', 'variants'])
            ->orderBy('gift_sort_order')
            ->orderByDesc('featured')
            ->orderByRaw('COALESCE(sale_price, price) ASC')
            ->get()
            ->filter(fn (Product $product): bool => $this->matchesRole($product, $role))
            ->filter(fn (Product $product): bool => $this->matchesTags($product->gift_recipient_tags, $recipient))
            ->filter(fn (Product $product): bool => $this->matchesTags($product->gift_occasion_tags, $occasion))
            ->filter(fn (Product $product): bool => $this->matchesBudget($product, $budget))
            ->map(fn (Product $product): array => $this->serializeProduct($product))
            ->values();
    }

    public function serializeProduct(Product $product): array
    {
        $locale = app()->getLocale();
        $price = (float) ($product->sale_price ?? $product->price ?? 0);
        $image = $product->primaryImage?->url ?? asset('storage/images/home/smart-watch3.jpg');

        return [
            'id' => (int) $product->id,
            'slug' => $product->slug,
            'name' => $product->name,
            'short_description' => $product->short_description,
            'price' => $price,
            'price_formatted' => number_format($price, 2) . ' ₾',
            'image' => $image,
            'role' => $product->gift_builder_role ?: 'none',
            'recipient_tags' => array_values((array) ($product->gift_recipient_tags ?? [])),
            'occasion_tags' => array_values((array) ($product->gift_occasion_tags ?? [])),
            'budget_band' => $product->gift_budget_band ?: $this->budgetBandForPrice($price),
            'compatibility_tags' => array_values((array) ($product->gift_compatibility_tags ?? [])),
            'capacity_units' => max(1, (int) ($product->gift_capacity_units ?: 1)),
            'badge' => $locale === 'ka' ? ($product->gift_badge_ka ?: $product->gift_badge_en) : $product->gift_badge_en,
            'note' => $locale === 'ka' ? ($product->gift_builder_note_ka ?: $product->gift_builder_note_en) : $product->gift_builder_note_en,
            'variants' => $product->variants
                ->filter(fn (ProductVariant $variant): bool => $variant->available_quantity > 0)
                ->map(fn (ProductVariant $variant): array => [
                    'id' => (int) $variant->id,
                    'name' => $variant->localizedName($locale),
                    'color_name' => $variant->localizedColorName($locale),
                    'color_hex' => $variant->color_hex,
                    'available_quantity' => (int) $variant->available_quantity,
                ])
                ->values()
                ->all(),
        ];
    }

    private function preselectedProduct(Request $request): ?array
    {
        $slug = $request->query('product');
        if (! is_string($slug) || $slug === '') {
            return null;
        }

        $product = Product::query()
            ->active()
            ->where('slug', $slug)
            ->where('gift_builder_enabled', true)
            ->where('fulfillment_mode', 'local_stock')
            ->with(['primaryImage', 'variants'])
            ->first();

        if (! $product || ! $this->matchesRole($product, 'main')) {
            return null;
        }

        $requestedVariantId = (int) $request->query('variant_id');
        $variant = $product->variants
            ->first(fn (ProductVariant $item): bool => $requestedVariantId > 0 && (int) $item->id === $requestedVariantId && $item->available_quantity > 0)
            ?? $product->variants->first(fn (ProductVariant $item): bool => $item->available_quantity > 0);

        if (! $variant) {
            return null;
        }

        return [
            'selected_variant_id' => (int) $variant->id,
            'budget_band' => $product->gift_budget_band ?: $this->budgetBandForPrice((float) ($product->sale_price ?? $product->price ?? 0)),
        ];
    }

    private function readyBoxSelection(Request $request, Collection $products): ?array
    {
        $slug = $request->query('box');
        if (! is_string($slug) || $slug === '') {
            return null;
        }

        $box = (array) config("gift_builder.ready_boxes.{$slug}", []);
        if ($box === []) {
            return null;
        }

        $main = $products->firstWhere('slug', $box['main_product'] ?? null);
        if (! $main || ! in_array($main['role'], ['main', 'both'], true)) {
            return null;
        }

        $mainVariant = $main['variants'][0] ?? null;
        if (! $mainVariant) {
            return null;
        }

        $addonSlugs = array_values((array) ($box['addon_products'] ?? []));
        $addonVariantIds = collect($addonSlugs)
            ->map(function (string $productSlug) use ($products): ?int {
                $product = $products->firstWhere('slug', $productSlug);
                if (! $product || ! in_array($product['role'], ['addon', 'both'], true)) {
                    return null;
                }

                return isset($product['variants'][0]['id']) ? (int) $product['variants'][0]['id'] : null;
            })
            ->filter()
            ->values()
            ->all();

        if (count($addonVariantIds) !== count($addonSlugs)) {
            return null;
        }

        return [
            'slug' => $slug,
            'selected_variant_id' => (int) $mainVariant['id'],
            'addon_variant_ids' => $addonVariantIds,
            'budget_band' => $box['budget_band'] ?? 'under_250',
            'packaging_slug' => $box['packaging_slug'] ?? 'standard',
        ];
    }

    private function localizedConfig(string $key): array
    {
        $locale = app()->getLocale();

        return collect((array) config($key, []))
            ->map(function (array $item, string $slug) use ($locale): array {
                $item['slug'] = $slug;
                $item['label'] = $locale === 'ka'
                    ? ($item['label_ka'] ?? $item['label_en'] ?? $slug)
                    : ($item['label_en'] ?? str($slug)->headline()->toString());
                if (isset($item['description_ka']) || isset($item['description_en'])) {
                    $item['description'] = $locale === 'ka'
                        ? ($item['description_ka'] ?? $item['description_en'] ?? '')
                        : ($item['description_en'] ?? '');
                }

                return $item;
            })
            ->values()
            ->all();
    }

    private function matchesRole(Product $product, string $role): bool
    {
        $productRole = $product->gift_builder_role ?: 'none';
        if ($role === 'all') {
            return in_array($productRole, ['main', 'addon', 'both'], true);
        }

        if ($role === 'main') {
            return in_array($productRole, ['main', 'both'], true);
        }

        if ($role === 'addon') {
            return in_array($productRole, ['addon', 'both'], true);
        }

        return false;
    }

    private function matchesTags(null|array|string $tags, mixed $selected): bool
    {
        if (! is_string($selected) || $selected === '') {
            return true;
        }

        $normalized = array_values(array_filter((array) $tags));
        if ($normalized === []) {
            return true;
        }

        return in_array($selected, $normalized, true);
    }

    private function matchesBudget(Product $product, string $budget): bool
    {
        if ($budget === '' || $budget === 'all') {
            return true;
        }

        $price = (float) ($product->sale_price ?? $product->price ?? 0);
        $band = (array) config("gift_builder.budget_bands.{$budget}", []);

        $min = $band['min'] ?? null;
        $max = $band['max'] ?? null;

        if ($min !== null && $price < (float) $min) {
            return false;
        }

        if ($max !== null && $price > (float) $max) {
            return false;
        }

        return true;
    }

    private function budgetBandForPrice(float $price): string
    {
        if ($price <= 50) {
            return 'under_50';
        }

        if ($price <= 100) {
            return 'under_100';
        }

        return 'under_250';
    }
}
