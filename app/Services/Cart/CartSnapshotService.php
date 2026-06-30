<?php

namespace App\Services\Cart;

use App\Models\ProductVariant;
use App\Services\Product\VariantImageResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use RuntimeException;

class CartSnapshotService
{
    public function __construct(private readonly VariantImageResolver $variantImageResolver)
    {
    }

    public function build(Request $request, array $options = []): array
    {
        $normalizeSession = (bool) ($options['normalize_session'] ?? true);
        $lockForUpdate = (bool) ($options['lock_for_update'] ?? false);
        $enforceStock = (bool) ($options['enforce_stock'] ?? false);

        $standardCart = collect($request->session()->get('cart', []));
        $giftGroupsSession = collect($request->session()->get('gift_cart_groups', []));

        $variantIds = $standardCart->keys()
            ->merge($giftGroupsSession->flatMap(fn ($group) => collect($group['items'] ?? [])->pluck('variant_id')))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $variantQuery = ProductVariant::query()
            ->with(['product.primaryImage', 'product.variants'])
            ->whereIn('id', $variantIds);

        if ($lockForUpdate) {
            $variantQuery->lockForUpdate();
        }

        $variants = $variantIds->isEmpty()
            ? collect()
            : $variantQuery->get()->keyBy('id');

        [$standardItems, $normalizedCart] = $this->standardItems($standardCart, $variants, $enforceStock);
        [$giftGroups, $normalizedGiftGroups] = $this->giftGroups($giftGroupsSession, $variants, $enforceStock);

        if ($normalizeSession) {
            $request->session()->put('cart', $normalizedCart);
            $request->session()->put('gift_cart_groups', $normalizedGiftGroups);
        }

        $standardTotal = (float) $standardItems->sum('subtotal');
        $giftTotal = (float) $giftGroups->sum('total');
        $count = (int) $standardItems->sum('quantity') + (int) $giftGroups->sum(fn (array $group) => collect($group['items'])->sum('quantity'));

        return [
            'standard_items' => $standardItems,
            'gift_groups' => $giftGroups,
            'all_items' => $standardItems->merge($giftGroups->flatMap(fn (array $group) => $group['items'])->values()),
            'summary' => [
                'count' => $count,
                'standard_total' => $standardTotal,
                'gift_total' => $giftTotal,
                'total' => $standardTotal + $giftTotal,
                'packaging_total' => (float) $giftGroups->sum('packaging_amount'),
                'discount_total' => (float) $giftGroups->sum('discount_amount'),
            ],
        ];
    }

    public function roughCount(Request $request): int
    {
        $standardCount = collect($request->session()->get('cart', []))
            ->sum(fn ($item): int => (int) ($item['quantity'] ?? 0));

        $giftCount = collect($request->session()->get('gift_cart_groups', []))
            ->sum(fn ($group): int => collect($group['items'] ?? [])->sum(fn ($item): int => (int) ($item['quantity'] ?? 1)));

        return (int) $standardCount + (int) $giftCount;
    }

    private function standardItems(Collection $cart, Collection $variants, bool $enforceStock): array
    {
        $items = collect();
        $normalized = [];

        foreach ($cart as $variantId => $item) {
            $variant = $variants->get((int) $variantId);
            if (! $variant || ! $variant->product || ! $variant->product->is_active) {
                if ($enforceStock) {
                    throw new RuntimeException('One or more products are no longer available.');
                }
                continue;
            }

            $quantity = max(1, min((int) ($item['quantity'] ?? 1), 10));
            if ($enforceStock && ! $variant->canFulfillQuantity($quantity)) {
                throw new RuntimeException('Insufficient stock for: ' . $variant->name);
            }

            $line = $this->lineItem($variant, $quantity, null, null, null);
            if (! $line) {
                if ($enforceStock) {
                    throw new RuntimeException('Invalid product price detected.');
                }
                continue;
            }

            $normalized[(int) $variant->id] = [
                'variant_id' => (int) $variant->id,
                'quantity' => $quantity,
            ];

            $items->push($line);
        }

        return [$items, $normalized];
    }

    private function giftGroups(Collection $groups, Collection $variants, bool $enforceStock): array
    {
        $normalizedGroups = [];
        $giftGroups = collect();

        foreach ($groups as $groupId => $group) {
            $groupItems = collect();
            $normalizedItems = [];
            $itemsSubtotal = 0.0;
            $itemsCount = 0;
            $capacityUnits = 0;
            $mainCount = 0;
            $mainCompatibilityTags = [];
            $addonCompatibilityTags = [];

            foreach (($group['items'] ?? []) as $index => $item) {
                $variant = $variants->get((int) ($item['variant_id'] ?? 0));
                if (! $variant || ! $variant->product || ! $variant->product->is_active) {
                    if ($enforceStock) {
                        throw new RuntimeException('One or more gift products are no longer available.');
                    }
                    continue;
                }

                $quantity = 1;
                if ($enforceStock && ! $variant->canFulfillQuantity($quantity)) {
                    throw new RuntimeException('Insufficient stock for: ' . $variant->name);
                }

                $role = in_array(($item['role'] ?? ''), ['main', 'addon'], true) ? $item['role'] : 'addon';
                if ($enforceStock && ! $this->giftRoleAllowed($variant->product, $role)) {
                    throw new RuntimeException('One or more gift products are no longer eligible for gift boxes.');
                }

                if ($role === 'main') {
                    $mainCount++;
                    $mainCompatibilityTags = array_values((array) ($variant->product->gift_compatibility_tags ?? []));
                } else {
                    $addonCompatibilityTags[] = array_values((array) ($variant->product->gift_compatibility_tags ?? []));
                }

                $capacityUnits += max(1, (int) ($variant->product->gift_capacity_units ?: 1));
                $sortOrder = (int) ($item['sort_order'] ?? ($index + 1));
                $line = $this->lineItem($variant, $quantity, (string) $groupId, $role, $sortOrder);
                if (! $line) {
                    if ($enforceStock) {
                        throw new RuntimeException('Invalid gift product price detected.');
                    }
                    continue;
                }

                $itemsSubtotal += (float) $line['subtotal'];
                $itemsCount += $quantity;
                $groupItems->push($line);
                $normalizedItems[] = [
                    'variant_id' => (int) $variant->id,
                    'quantity' => 1,
                    'role' => $role,
                    'sort_order' => $sortOrder,
                ];
            }

            if ($groupItems->isEmpty()) {
                continue;
            }

            if ($enforceStock && $mainCount !== 1) {
                throw new RuntimeException('Gift box needs exactly one main product.');
            }

            $packagingSlug = (string) ($group['packaging_slug'] ?? 'standard');
            $packaging = (array) config("gift_builder.packaging.{$packagingSlug}", config('gift_builder.packaging.standard', []));
            $packagingLabel = $group['packaging_label'] ?? $this->localizedLabel($packaging, $packagingSlug);
            $packagingCapacity = (int) ($packaging['capacity_units'] ?? 0);
            if ($enforceStock && $packagingCapacity > 0 && $capacityUnits > $packagingCapacity) {
                throw new RuntimeException('Selected gift packaging can no longer fit the gift box.');
            }

            if ($enforceStock) {
                foreach ($addonCompatibilityTags as $addonTags) {
                    if ($addonTags !== [] && array_intersect($mainCompatibilityTags, $addonTags) === []) {
                        throw new RuntimeException('One or more gift add-ons are no longer compatible.');
                    }
                }
            }

            $packagingAmount = max(0, (float) ($group['packaging_amount'] ?? ($packaging['price'] ?? 0)));
            $discountAmount = max(0, min($itemsSubtotal + $packagingAmount, (float) ($group['discount_amount'] ?? 0)));
            $total = max(0, $itemsSubtotal + $packagingAmount - $discountAmount);

            $normalizedGroups[(string) $groupId] = [
                'recipient_type' => $group['recipient_type'] ?? 'other',
                'occasion' => $group['occasion'] ?? 'just_because',
                'budget_band' => $group['budget_band'] ?? 'all',
                'packaging_slug' => $packagingSlug,
                'packaging_label' => $packagingLabel,
                'packaging_amount' => $packagingAmount,
                'discount_amount' => $discountAmount,
                'message' => (string) ($group['message'] ?? ''),
                'items' => $normalizedItems,
            ];

            $giftGroups->push([
                'id' => (string) $groupId,
                'recipient_type' => $group['recipient_type'] ?? 'other',
                'occasion' => $group['occasion'] ?? 'just_because',
                'budget_band' => $group['budget_band'] ?? 'all',
                'packaging_slug' => $packagingSlug,
                'packaging_label' => $packagingLabel,
                'packaging_amount' => $packagingAmount,
                'discount_amount' => $discountAmount,
                'message' => (string) ($group['message'] ?? ''),
                'items' => $groupItems->sortBy('gift_sort_order')->values(),
                'items_count' => $itemsCount,
                'items_subtotal' => $itemsSubtotal,
                'total' => $total,
                'currency' => $groupItems->first()['currency'] ?? 'GEL',
            ]);
        }

        return [$giftGroups, $normalizedGroups];
    }

    private function lineItem(ProductVariant $variant, int $quantity, ?string $giftGroupId, ?string $giftRole, ?int $giftSortOrder): ?array
    {
        $product = $variant->product;
        $unitPrice = (float) ($product->sale_price ?? $product->price ?? 0);
        $image = $this->variantImageResolver->imageForVariant($product, $variant);

        if ($unitPrice <= 0) {
            return null;
        }

        return [
            'variant' => $variant,
            'product' => $product,
            'variant_label' => $this->variantLabel($variant),
            'color_name' => $variant->color_name,
            'color_hex' => $variant->color_hex,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $unitPrice * $quantity,
            'currency' => $product->currency,
            'image' => $image['thumbnail_url'] ?? $image['url'] ?? $product->primaryImage?->url ?? asset('storage/images/home/smart-watch3.jpg'),
            'fulfillment_mode' => $product->fulfillment_mode,
            'fulfillment_label' => $product->fulfillmentLabel(),
            'gift_group_id' => $giftGroupId,
            'gift_role' => $giftRole,
            'gift_sort_order' => $giftSortOrder,
        ];
    }

    private function variantLabel(ProductVariant $variant): string
    {
        $name = trim((string) $variant->name);
        $colorName = trim((string) $variant->color_name);

        if ($name !== '' && $colorName !== '') {
            return str_contains(mb_strtolower($name), mb_strtolower($colorName))
                ? $name
                : "{$name} • {$colorName}";
        }

        return $colorName !== '' ? $colorName : ($name !== '' ? $name : 'Variant');
    }

    private function giftRoleAllowed($product, string $role): bool
    {
        $productRole = $product->gift_builder_role ?: 'none';
        $roleAllowed = $role === 'main'
            ? in_array($productRole, ['main', 'both'], true)
            : in_array($productRole, ['addon', 'both'], true);

        return (bool) $product->gift_builder_enabled
            && $product->fulfillment_mode === 'local_stock'
            && $roleAllowed;
    }

    private function localizedLabel(array $config, string $fallback): string
    {
        $locale = app()->getLocale();

        return $locale === 'ka'
            ? ($config['label_ka'] ?? $config['label_en'] ?? $fallback)
            : ($config['label_en'] ?? $config['label_ka'] ?? $fallback);
    }
}
