<?php

namespace App\Services\Cart;

use App\Models\ProductVariant;
use App\Models\ReadyGiftBox;
use App\Services\GiftBuilder\GiftBuilderDiscountService;
use App\Services\GiftBuilder\GiftBuilderPricingService;
use App\Services\GiftBuilder\GiftDeliveryPricingService;
use App\Services\Product\VariantImageResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use RuntimeException;

class CartSnapshotService
{
    public function __construct(
        private readonly VariantImageResolver $variantImageResolver,
        private readonly GiftBuilderDiscountService $giftDiscounts,
        private readonly GiftBuilderPricingService $giftPricing,
        private readonly GiftDeliveryPricingService $giftDelivery,
    ) {}

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
            ->whereIn('id', $variantIds)
            ->orderBy('id');

        if ($lockForUpdate) {
            $variantQuery->lockForUpdate();
        }

        $variants = $variantIds->isEmpty()
            ? collect()
            : $variantQuery->get()->keyBy('id');

        [$standardItems, $normalizedCart] = $this->standardItems($standardCart, $variants, $enforceStock);
        [$giftGroups, $normalizedGiftGroups] = $this->giftGroups($giftGroupsSession, $variants, $enforceStock);
        $allItems = $standardItems
            ->merge($giftGroups->flatMap(fn (array $group) => $group['items']))
            ->values();

        if ($enforceStock) {
            $this->assertAggregateStock($allItems);
        }

        if ($normalizeSession) {
            $request->session()->put('cart', $normalizedCart);
            $request->session()->put('gift_cart_groups', $normalizedGiftGroups);
        }

        $standardTotal = (float) $standardItems->sum('subtotal');
        $giftTotal = (float) $giftGroups->sum('total');
        $count = (int) $standardItems->sum('quantity') + (int) $giftGroups->sum(fn (array $group) => collect($group['items'])->sum('quantity'));
        $goodsTotalMinor = (int) round(($standardTotal + $giftTotal) * 100, 0, PHP_ROUND_HALF_UP);
        $delivery = (bool) config('gift_builder.pricing_v2_enabled', false)
            ? $this->giftDelivery->quote($giftGroups->isNotEmpty(), $goodsTotalMinor, $count > 0)
            : ['delivery_amount' => 0.0, 'delivery_amount_minor' => 0, 'free_delivery_remaining' => 0.0, 'free_delivery_remaining_minor' => 0];
        $quoteVersion = $this->cartQuoteVersion($standardItems, $giftGroups, $delivery);

        return [
            'standard_items' => $standardItems,
            'gift_groups' => $giftGroups,
            'all_items' => $allItems,
            'summary' => [
                'count' => $count,
                'standard_total' => $standardTotal,
                'gift_total' => $giftTotal,
                'total' => $standardTotal + $giftTotal,
                'packaging_total' => (float) $giftGroups->sum('packaging_amount'),
                'discount_total' => (float) $giftGroups->sum('discount_amount'),
                'delivery_total' => $delivery['delivery_amount'],
                'free_delivery_remaining' => $delivery['free_delivery_remaining'],
                'payable_total' => $standardTotal + $giftTotal + $delivery['delivery_amount'],
                'quote_version' => $quoteVersion,
            ],
        ];
    }

    private function assertAggregateStock(Collection $items): void
    {
        $requiredByVariant = $items
            ->groupBy(fn (array $item): int => (int) $item['variant']->id)
            ->map(fn (Collection $lines): int => (int) $lines->sum('quantity'));

        foreach ($requiredByVariant as $variantId => $requiredQuantity) {
            $variant = $items->first(
                fn (array $item): bool => (int) $item['variant']->id === (int) $variantId
            )['variant'] ?? null;

            if (! $variant || ! $variant->canFulfillQuantity($requiredQuantity)) {
                throw new RuntimeException('Insufficient stock for the combined cart quantity.');
            }
        }
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
                throw new RuntimeException('Insufficient stock for: '.$variant->name);
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
        if ((bool) config('gift_builder.pricing_v2_enabled', false)) {
            return $this->giftGroupsV2($groups, $variants, $enforceStock);
        }

        $normalizedGroups = [];
        $giftGroups = collect();

        foreach ($groups as $groupId => $group) {
            $groupItems = collect();
            $normalizedItems = [];
            $itemsSubtotal = 0.0;
            $itemsCount = 0;
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
                    throw new RuntimeException('Insufficient stock for: '.$variant->name);
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
            $packaging = (array) config("gift_builder.packaging.{$packagingSlug}", []);
            if ($packaging === []) {
                if ($enforceStock) {
                    throw new RuntimeException('Selected gift packaging is no longer available.');
                }

                $packagingSlug = 'standard';
                $packaging = (array) config('gift_builder.packaging.standard', []);
            }
            $packagingLabel = $this->localizedLabel($packaging, $packagingSlug);
            if ($enforceStock) {
                foreach ($addonCompatibilityTags as $addonTags) {
                    if ($addonTags !== [] && array_intersect($mainCompatibilityTags, $addonTags) === []) {
                        throw new RuntimeException('One or more gift add-ons are no longer compatible.');
                    }
                }
            }

            $packagingAmount = max(0, (float) ($packaging['price'] ?? 0));
            $originSlug = trim((string) ($group['ready_box']['slug'] ?? $group['ready_box_slug'] ?? ''));
            $originBox = $originSlug !== ''
                ? ReadyGiftBox::query()->active()->with('items')->where('slug', $originSlug)->first()
                : null;
            $discountItems = $groupItems->map(fn (array $line): array => [
                'product_id' => (int) $line['product']->id,
                'role' => (string) $line['gift_role'],
            ])->values()->all();
            if ($originSlug !== '' && ! $originBox) {
                $discount = [
                    'amount' => 0.0,
                    'source' => 'none',
                    'type' => null,
                    'value' => 0.0,
                    'retained' => false,
                    'removed' => true,
                ];
            } elseif ($originBox) {
                $discount = $this->giftDiscounts->resolve($originBox, $packagingSlug, $discountItems, $itemsSubtotal);
            } elseif (array_key_exists('discount_source', $group)) {
                $discountType = in_array(($group['discount_type'] ?? null), ['fixed', 'percent'], true)
                    ? (string) $group['discount_type']
                    : null;
                $discountValue = max(0, (float) ($group['discount_value'] ?? 0));
                $discountSource = (string) ($group['discount_source'] ?? 'none');
                $discount = [
                    'amount' => $discountSource === 'legacy'
                        ? max(0, min($itemsSubtotal, (float) ($group['discount_amount'] ?? 0)))
                        : ($discountSource === 'builder' && $discountType
                            ? $this->giftDiscounts->calculate($discountType, $discountValue, $itemsSubtotal)
                            : 0.0),
                    'source' => in_array($discountSource, ['builder', 'legacy'], true) ? $discountSource : 'none',
                    'type' => $discountSource === 'builder' ? $discountType : null,
                    'value' => $discountSource === 'builder' ? $discountValue : 0.0,
                    'retained' => false,
                    'removed' => false,
                ];
            } else {
                // Backward compatibility for encrypted, server-authored gift groups created
                // before discount rule metadata was introduced.
                $discount = [
                    'amount' => max(0, min($itemsSubtotal, (float) ($group['discount_amount'] ?? 0))),
                    'source' => 'legacy',
                    'type' => null,
                    'value' => 0.0,
                    'retained' => false,
                    'removed' => false,
                ];
            }
            $discountAmount = max(0, min($itemsSubtotal, (float) $discount['amount']));
            $total = max(0, $itemsSubtotal + $packagingAmount - $discountAmount);

            $readyBoxMetadata = $originBox ? [
                'id' => (int) $originBox->id,
                'slug' => $originBox->slug,
                'title' => $originBox->title,
                'discount_type' => $originBox->discount_type,
                'discount_value' => (float) $originBox->discount_value,
                'discount_retained' => (bool) $discount['retained'],
            ] : ($originSlug !== '' ? [
                'id' => isset($group['ready_box']['id']) ? (int) $group['ready_box']['id'] : null,
                'slug' => $originSlug,
                'title' => (string) ($group['ready_box']['title'] ?? ''),
                'discount_type' => null,
                'discount_value' => 0.0,
                'discount_retained' => false,
            ] : null);

            $campaign = collect((array) ($group['campaign'] ?? []))
                ->only(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'])
                ->filter(fn ($value): bool => is_scalar($value))
                ->map(fn ($value): string => mb_substr(trim((string) $value), 0, 200))
                ->filter()
                ->all();

            $normalizedGroups[(string) $groupId] = [
                'recipient_type' => $group['recipient_type'] ?? 'other',
                'occasion' => $group['occasion'] ?? 'just_because',
                'budget_band' => $group['budget_band'] ?? 'all',
                'packaging_slug' => $packagingSlug,
                'packaging_label' => $packagingLabel,
                'packaging_amount' => $packagingAmount,
                'discount_amount' => $discountAmount,
                'discount_source' => $discount['source'],
                'discount_type' => $discount['type'],
                'discount_value' => (float) $discount['value'],
                'discount_retained' => (bool) $discount['retained'],
                'ready_box' => $readyBoxMetadata,
                'campaign' => $campaign,
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
                'discount_source' => $discount['source'],
                'discount_type' => $discount['type'],
                'discount_value' => (float) $discount['value'],
                'discount_retained' => (bool) $discount['retained'],
                'preset_discount_removed' => (bool) $discount['removed'],
                'ready_box' => $readyBoxMetadata,
                'campaign' => $campaign,
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

    private function giftGroupsV2(Collection $groups, Collection $variants, bool $enforceStock): array
    {
        $normalizedGroups = [];
        $giftGroups = collect();
        foreach ($groups as $groupId => $group) {
            $rawItems = collect($group['items'] ?? [])->values();
            $valid = [];
            $linesByVariant = [];
            $errors = [];
            foreach ($rawItems as $index => $item) {
                $variant = $variants->get((int) ($item['variant_id'] ?? 0));
                if (! $variant || ! $variant->product) {
                    $errors[] = app()->getLocale() === 'en' ? 'One or more selected products are unavailable.' : 'არჩეული პროდუქტებიდან ერთ-ერთი ხელმისაწვდომი აღარ არის.';
                    continue;
                }
                $variant->product->variants->each(fn ($candidate) => $candidate->setRelation('product', $variant->product));
                $lineRole = $index === 0 ? 'main' : 'addon';
                $valid[] = [
                    'variant_id' => (int) $variant->id,
                    'quantity' => 1,
                    'role' => $lineRole,
                ];
                $linesByVariant[(int) $variant->id] = [
                    'variant' => $variant,
                    'role' => $lineRole,
                    'sort_order' => (int) ($item['sort_order'] ?? $index + 1),
                ];
            }

            $pricing = null;
            if ($errors === []) {
                try {
                    $pricing = $this->giftPricing->price([
                        'recipient_type' => $group['recipient_type'] ?? 'other',
                        'occasion' => $group['occasion'] ?? 'just_because',
                        'budget_band' => $group['budget_band'] ?? 'all',
                        'packaging_slug' => $group['packaging_slug'] ?? 'standard',
                        'ready_box_slug' => data_get($group, 'ready_box.slug') ?? $group['ready_box_slug'] ?? null,
                        'message' => $group['message'] ?? '',
                        'items' => $valid,
                    ], $variants);
                } catch (\Illuminate\Validation\ValidationException $exception) {
                    $errors = collect($exception->errors())->flatten()->map(fn ($error): string => (string) $error)->all();
                }
            }

            if ($errors !== [] && $enforceStock) {
                throw new RuntimeException(implode(' ', $errors));
            }

            $groupItems = collect();
            if ($pricing) {
                foreach ($pricing['items'] as $pricedItem) {
                    $source = $linesByVariant[(int) $pricedItem['variant_id']] ?? null;
                    if (! $source) {
                        continue;
                    }
                    $line = $this->lineItem($source['variant'], 1, (string) $groupId, $source['role'], $source['sort_order']);
                    if ($line) {
                        $line['unit_price'] = (float) $pricedItem['unit_price'];
                        $line['subtotal'] = (float) $pricedItem['subtotal'];
                        $line['retail_unit_price'] = (float) $pricedItem['retail_unit_price'];
                        $groupItems->push($line);
                    }
                }
            } else {
                foreach ($linesByVariant as $variantId => $source) {
                    $line = $this->lineItem($source['variant'], 1, (string) $groupId, $source['role'], $source['sort_order']);
                    if ($line) {
                        $groupItems->push($line);
                    }
                }
            }

            $packageSlug = (string) ($pricing['packaging_slug'] ?? $group['packaging_slug'] ?? 'standard');
            $packageLabel = (string) ($pricing['packaging_label'] ?? $group['packaging_label'] ?? $packageSlug);
            $packageAmount = (float) ($pricing['packaging_amount'] ?? 0);
            $discountAmount = 0.0;
            $total = (float) ($pricing['total'] ?? $groupItems->sum('subtotal'));
            $campaign = collect((array) ($group['campaign'] ?? []))
                ->only(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'])
                ->filter(fn ($value): bool => is_scalar($value))
                ->map(fn ($value): string => mb_substr(trim((string) $value), 0, 200))
                ->filter()->all();
            $normalizedItems = $rawItems->map(function ($item, $index): array {
                return [
                    'variant_id' => (int) ($item['variant_id'] ?? 0),
                    'quantity' => 1,
                    'role' => $index === 0 ? 'main' : 'addon',
                    'sort_order' => (int) ($item['sort_order'] ?? $index + 1),
                ];
            })->all();
            $normalizedGroups[(string) $groupId] = [
                'recipient_type' => $group['recipient_type'] ?? 'other',
                'occasion' => $group['occasion'] ?? 'just_because',
                'budget_band' => $group['budget_band'] ?? 'all',
                'packaging_slug' => $packageSlug,
                'message' => (string) ($group['message'] ?? ''),
                'ready_box' => $group['ready_box'] ?? null,
                'campaign' => $campaign,
                'items' => $normalizedItems,
            ];
            $giftGroups->push([
                'id' => (string) $groupId,
                'recipient_type' => $group['recipient_type'] ?? 'other',
                'occasion' => $group['occasion'] ?? 'just_because',
                'budget_band' => $group['budget_band'] ?? 'all',
                'packaging_slug' => $packageSlug,
                'packaging_label' => $packageLabel,
                'packaging_amount' => $packageAmount,
                'discount_amount' => $discountAmount,
                'discount_source' => 'none',
                'discount_type' => null,
                'discount_value' => 0.0,
                'discount_retained' => false,
                'preset_discount_removed' => false,
                'ready_box' => $group['ready_box'] ?? null,
                'campaign' => $campaign,
                'message' => (string) ($group['message'] ?? ''),
                'items' => $groupItems->sortBy('gift_sort_order')->values(),
                'items_count' => $rawItems->count(),
                'items_subtotal' => (float) ($pricing['items_subtotal'] ?? $groupItems->sum('subtotal')),
                'retail_reference_total' => (float) ($pricing['retail_reference_total'] ?? 0),
                'savings' => (float) ($pricing['savings'] ?? 0),
                'pricing_version' => 2,
                'quote_version' => $pricing['quote_version'] ?? null,
                'validation_errors' => $errors,
                'total' => $total,
                'currency' => 'GEL',
            ]);
        }

        return [$giftGroups, $normalizedGroups];
    }

    private function cartQuoteVersion(Collection $standardItems, Collection $giftGroups, array $delivery): ?string
    {
        if (! (bool) config('gift_builder.pricing_v2_enabled', false)) {
            return null;
        }
        if ($giftGroups->contains(fn (array $group): bool => ! empty($group['validation_errors']) || empty($group['quote_version']))) {
            return null;
        }
        $items = $standardItems->map(fn (array $item): array => [
            'variant_id' => (int) $item['variant']->id,
            'quantity' => (int) $item['quantity'],
            'unit_price_minor' => (int) round((float) $item['unit_price'] * 100),
        ])->all();
        $payload = [
            'standard' => $items,
            'gifts' => $giftGroups->map(fn (array $group): array => [
                'quote_version' => $group['quote_version'],
                'id' => $group['id'],
            ])->all(),
            'delivery_minor' => $delivery['delivery_amount_minor'],
        ];
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
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
            'color_name' => $variant->localizedColorName(),
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
        $name = trim($variant->localizedName());
        $colorName = trim($variant->localizedColorName());

        if ($name !== '' && $colorName !== '') {
            return str_contains(mb_strtolower($name), mb_strtolower($colorName))
                ? $name
                : "{$name} • {$colorName}";
        }

        return $colorName !== '' ? $colorName : ($name !== '' ? $name : __('storefront.common.color_variant'));
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
            : ($config['label_en'] ?? str($fallback)->headline()->toString());
    }
}
