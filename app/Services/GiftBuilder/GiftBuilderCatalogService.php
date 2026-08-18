<?php

namespace App\Services\GiftBuilder;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReadyGiftBox;
use App\Services\Product\VariantImageResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class GiftBuilderCatalogService
{
    public function __construct(
        private readonly ReadyGiftBoxAvailabilityService $availability,
        private readonly GiftBuilderDiscountService $discounts,
        private readonly VariantImageResolver $variantImages,
    ) {}

    public function builderConfig(Request $request): array
    {
        $products = $this->products([
            'role' => 'all',
            'budget_band' => 'all',
        ]);

        $preselected = $this->preselectedProduct($request);
        $readyBox = $preselected ? null : $this->readyBoxSelection($request);

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
                'ready_box' => $readyBox['ready_box'] ?? null,
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

    /** @return array<int, array<string, mixed>> */
    public function readyBoxes(): array
    {
        return ReadyGiftBox::query()
            ->active()
            ->with($this->readyBoxRelations())
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (ReadyGiftBox $box): bool => $this->availability->report($box)['available'])
            ->map(fn (ReadyGiftBox $box): array => $this->serializeReadyBox($box))
            ->values()
            ->all();
    }

    public function findPublicReadyBox(string $slug): ?ReadyGiftBox
    {
        $box = ReadyGiftBox::query()
            ->active()
            ->with($this->readyBoxRelations())
            ->where('slug', $slug)
            ->first();

        if (! $box || ! $this->availability->report($box)['available']) {
            return null;
        }

        return $box;
    }

    /** @return array<string, mixed> */
    public function serializeReadyBox(ReadyGiftBox $box): array
    {
        $box->loadMissing($this->readyBoxRelations());
        $packaging = (array) config("gift_builder.packaging.{$box->packaging_slug}", []);
        $packagingAmount = max(0, (float) ($packaging['price'] ?? 0));

        $items = $box->items->map(function ($boxItem): array {
            $product = $boxItem->product;
            $serialized = $this->serializeProduct($product);
            $availableVariants = collect($serialized['variants']);
            $selectedVariant = $availableVariants->firstWhere('id', (int) $boxItem->default_variant_id)
                ?? $availableVariants->first();

            return array_merge($serialized, [
                'item_id' => (int) $boxItem->id,
                'product_id' => (int) $product->id,
                'role' => $boxItem->role,
                'sort_order' => (int) $boxItem->sort_order,
                'default_variant_id' => $selectedVariant ? (int) $selectedVariant['id'] : null,
                'selected_variant_id' => $selectedVariant ? (int) $selectedVariant['id'] : null,
                'has_multiple_variants' => $availableVariants->count() > 1,
                'product' => $serialized,
            ]);
        })->values();

        $itemsSubtotal = (float) $items->sum(fn (array $item): float => (float) $item['price']);
        $discount = $this->discounts->resolve(
            $box,
            $box->packaging_slug,
            $items->map(fn (array $item): array => [
                'product_id' => (int) $item['product_id'],
                'role' => $item['role'],
            ])->all(),
            $itemsSubtotal,
        );
        $originalTotal = $itemsSubtotal + $packagingAmount;
        $total = max(0, $originalTotal - $discount['amount']);
        $coverImage = $box->cover_image_url ?: ($items->firstWhere('role', 'main')['image'] ?? null);

        return [
            'id' => (int) $box->id,
            'slug' => $box->slug,
            'title' => $box->title,
            'label' => $box->title,
            'description' => $box->short_description,
            'badge' => $box->badge,
            'cover_image' => $coverImage,
            'hero_image_url' => $coverImage,
            'theme_key' => $box->theme_key,
            'is_featured' => (bool) $box->is_featured,
            'packaging_slug' => $box->packaging_slug,
            'packaging_label' => $this->localizedLabel($packaging, $box->packaging_slug),
            'packaging_amount' => $packagingAmount,
            'discount' => [
                'type' => $box->discount_type,
                'value' => (float) $box->discount_value,
                'amount' => (float) $discount['amount'],
                'label' => $this->discountLabel($box),
            ],
            'discount_amount' => (float) $discount['amount'],
            'discount_type' => $box->discount_type,
            'discount_value' => (float) $box->discount_value,
            'discount_retained' => true,
            'items' => $items->all(),
            'items_subtotal' => $itemsSubtotal,
            'original_total' => $originalTotal,
            'old_price' => $originalTotal,
            'total' => $total,
            'price' => $total,
            'currency' => 'GEL',
            'total_formatted' => number_format($total, 2).' ₾',
            'message_max_length' => (int) config('gift_builder.message_max_length', 300),
            'cart_url' => route('cart.index'),
            'builder_url' => route('gift-builder.show', ['box' => $box->slug]),
            'options_url' => route('gift-boxes.options', $box),
            'add_to_cart_url' => route('gift-boxes.add-to-cart', $box),
        ];
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
            ->whereRaw('COALESCE(sale_price, price, 0) > 0')
            ->with(['primaryImage', 'images', 'variants.images'])
            ->orderBy('gift_sort_order')
            ->orderByDesc('featured')
            ->orderByRaw('COALESCE(sale_price, price) ASC')
            ->get()
            ->filter(fn (Product $product): bool => $this->matchesRole($product, $role))
            ->filter(fn (Product $product): bool => $this->matchesTags($product->gift_recipient_tags, $recipient))
            ->filter(fn (Product $product): bool => $this->matchesTags($product->gift_occasion_tags, $occasion))
            ->filter(fn (Product $product): bool => $this->matchesBudget($product, $budget))
            ->filter(fn (Product $product): bool => $product->variants->contains(
                fn (ProductVariant $variant): bool => (int) $variant->quantity > 0
            ))
            ->map(fn (Product $product): array => $this->serializeProduct($product))
            ->values();
    }

    /** @return array<string, mixed> */
    public function serializeProduct(Product $product): array
    {
        $locale = app()->getLocale();
        $price = (float) ($product->sale_price ?? $product->price ?? 0);
        $image = $product->primaryImage?->url ?? asset('storage/images/home/smart-watch3.jpg');
        $product->variants->each(fn (ProductVariant $variant) => $variant->setRelation('product', $product));
        $resolvedVariantImages = $this->variantImages->resolve($product);

        return [
            'id' => (int) $product->id,
            'slug' => $product->slug,
            'name' => $product->name,
            'short_description' => $product->short_description,
            'price' => $price,
            'price_formatted' => number_format($price, 2).' ₾',
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
                ->filter(fn (ProductVariant $variant): bool => (int) $variant->quantity > 0)
                ->map(function (ProductVariant $variant) use ($locale, $resolvedVariantImages, $image): array {
                    $variantImage = $resolvedVariantImages['variant_images'][(int) $variant->id]
                        ?? $resolvedVariantImages['default_image']
                        ?? null;

                    return [
                        'id' => (int) $variant->id,
                        'name' => $variant->localizedName($locale),
                        'color_name' => $variant->localizedColorName($locale),
                        'color_hex' => $variant->color_hex,
                        'available_quantity' => max(0, (int) $variant->quantity),
                        'image' => $variantImage['url'] ?? $image,
                        'thumbnail_image' => $variantImage['thumbnail_url'] ?? $variantImage['url'] ?? $image,
                    ];
                })
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
            ->whereRaw('COALESCE(sale_price, price, 0) > 0')
            ->with(['primaryImage', 'images', 'variants.images'])
            ->first();

        if (! $product || ! $this->matchesRole($product, 'main')) {
            return null;
        }

        $requestedVariantId = (int) $request->query('variant_id');
        $variant = $product->variants
            ->first(fn (ProductVariant $item): bool => $requestedVariantId > 0 && (int) $item->id === $requestedVariantId && (int) $item->quantity > 0)
            ?? $product->variants->first(fn (ProductVariant $item): bool => (int) $item->quantity > 0);

        if (! $variant) {
            return null;
        }

        return [
            'selected_variant_id' => (int) $variant->id,
            'budget_band' => $product->gift_budget_band ?: $this->budgetBandForPrice((float) ($product->sale_price ?? $product->price ?? 0)),
        ];
    }

    /** @return array<string, mixed>|null */
    private function readyBoxSelection(Request $request): ?array
    {
        $slug = $request->query('box');
        if (! is_string($slug) || $slug === '') {
            return null;
        }

        $box = $this->findPublicReadyBox($slug);
        if (! $box) {
            return null;
        }

        $serialized = $this->serializeReadyBox($box);
        $main = collect($serialized['items'])->firstWhere('role', 'main');
        $addons = collect($serialized['items'])->where('role', 'addon');

        return [
            'selected_variant_id' => $main['selected_variant_id'] ?? null,
            'addon_variant_ids' => $addons->pluck('selected_variant_id')->filter()->map(fn ($id): int => (int) $id)->values()->all(),
            'budget_band' => $main['budget_band'] ?? 'under_250',
            'packaging_slug' => $box->packaging_slug,
            'ready_box' => [
                'id' => (int) $box->id,
                'slug' => $box->slug,
                'title' => $box->title,
                'packaging_slug' => $box->packaging_slug,
                'discount_type' => $box->discount_type,
                'discount_value' => (float) $box->discount_value,
                'discount_retained' => true,
                'items' => $serialized['items'],
                'original_total' => $serialized['original_total'],
                'total' => $serialized['total'],
            ],
        ];
    }

    private function readyBoxRelations(): array
    {
        return [
            'items.product.primaryImage',
            'items.product.images',
            'items.product.variants.images',
            'items.defaultVariant',
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

        return ! (($min !== null && $price < (float) $min) || ($max !== null && $price > (float) $max));
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

    private function localizedLabel(array $config, string $fallback): string
    {
        return app()->getLocale() === 'ka'
            ? ($config['label_ka'] ?? $config['label_en'] ?? $fallback)
            : ($config['label_en'] ?? str($fallback)->headline()->toString());
    }

    private function discountLabel(ReadyGiftBox $box): string
    {
        $value = (float) $box->discount_value;
        if ($value <= 0) {
            return '';
        }

        return $box->discount_type === 'percent'
            ? '-'.rtrim(rtrim(number_format($value, 2), '0'), '.').'%'
            : '-'.number_format($value, 2).' ₾';
    }
}
