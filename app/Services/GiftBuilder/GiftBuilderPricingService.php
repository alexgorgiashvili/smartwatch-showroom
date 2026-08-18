<?php

namespace App\Services\GiftBuilder;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReadyGiftBox;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GiftBuilderPricingService
{
    public function __construct(private readonly GiftBuilderDiscountService $discounts) {}

    /** @return array<string, mixed> */
    public function price(array $payload): array
    {
        $payload = $this->validateShape($this->normalizeContext($payload));
        $items = $this->normalizeItems($payload['items'] ?? []);

        $mainItems = array_values(array_filter($items, fn (array $item): bool => $item['role'] === 'main'));
        if (count($mainItems) !== 1) {
            throw ValidationException::withMessages([
                'items' => __('storefront.gift_builder.main_required'),
            ]);
        }

        if (count($items) > (int) config('gift_builder.max_items', 4)) {
            throw ValidationException::withMessages([
                'items' => __('storefront.gift_builder.max_products', ['count' => config('gift_builder.max_items', 4)]),
            ]);
        }

        $variantIds = collect($items)->pluck('variant_id')->all();
        if (count($variantIds) !== count(array_unique($variantIds))) {
            throw ValidationException::withMessages([
                'items' => __('storefront.gift_builder.duplicate_variant'),
            ]);
        }

        $variants = ProductVariant::query()
            ->with(['product.primaryImage'])
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');

        $pricedItems = [];
        $mainProduct = null;
        $mainCompatibilityTags = [];
        $itemsSubtotal = 0.0;
        $capacityUnits = 0;
        $productIds = [];

        foreach ($items as $index => $item) {
            $variant = $variants->get($item['variant_id']);
            if (! $variant || ! $variant->product) {
                throw ValidationException::withMessages([
                    'items' => __('storefront.gift_builder.product_unavailable'),
                ]);
            }

            $product = $variant->product;
            if (in_array((int) $product->id, $productIds, true)) {
                throw ValidationException::withMessages([
                    'items' => __('storefront.gift_builder.duplicate_variant'),
                ]);
            }
            $productIds[] = (int) $product->id;

            $this->assertProductEligible($product, $variant, $item['role']);

            if ($item['role'] === 'main') {
                $mainProduct = $product;
                $mainCompatibilityTags = array_values((array) ($product->gift_compatibility_tags ?? []));
            }

            $unitPrice = (float) ($product->sale_price ?? $product->price ?? 0);
            if ($unitPrice <= 0) {
                throw ValidationException::withMessages([
                    'items' => __('storefront.gift_builder.invalid_price'),
                ]);
            }

            $itemsSubtotal += $unitPrice;
            $capacityUnits += max(1, (int) ($product->gift_capacity_units ?: 1));

            $pricedItems[] = [
                'variant_id' => (int) $variant->id,
                'product_id' => (int) $product->id,
                'role' => $item['role'],
                'quantity' => 1,
                'sort_order' => $index + 1,
                'unit_price' => $unitPrice,
                'subtotal' => $unitPrice,
                'product_name' => $product->name,
                'variant_name' => $variant->localizedName(),
                'image' => $product->primaryImage?->url ?? asset('storage/images/home/smart-watch3.jpg'),
                'compatibility_tags' => array_values((array) ($product->gift_compatibility_tags ?? [])),
                'capacity_units' => max(1, (int) ($product->gift_capacity_units ?: 1)),
                'fulfillment_mode' => $product->fulfillment_mode,
                'currency' => $product->currency ?: 'GEL',
            ];
        }

        if ($mainProduct) {
            foreach ($pricedItems as $pricedItem) {
                if ($pricedItem['role'] !== 'addon') {
                    continue;
                }

                $addonTags = $pricedItem['compatibility_tags'];
                if ($addonTags !== [] && array_intersect($mainCompatibilityTags, $addonTags) === []) {
                    throw ValidationException::withMessages([
                        'items' => __('storefront.gift_builder.incompatible_addon'),
                    ]);
                }
            }
        }

        $packagingSlug = (string) ($payload['packaging_slug'] ?: 'standard');
        $packaging = (array) config("gift_builder.packaging.{$packagingSlug}", []);
        if ($packaging === []) {
            throw ValidationException::withMessages([
                'packaging_slug' => __('storefront.gift_builder.packaging_unavailable'),
            ]);
        }

        $packagingCapacity = (int) ($packaging['capacity_units'] ?? 0);
        if ($capacityUnits > $packagingCapacity) {
            throw ValidationException::withMessages([
                'packaging_slug' => __('storefront.gift_builder.larger_box_required'),
            ]);
        }

        $readyBox = $this->readyBox($payload['ready_box_slug'] ?? null);
        if (collect($pricedItems)->pluck('currency')->unique()->count() > 1) {
            throw ValidationException::withMessages([
                'items' => __('storefront.gift_builder.invalid_price'),
            ]);
        }
        $currency = (string) (collect($pricedItems)->first()['currency'] ?? 'GEL');
        $discount = $this->discounts->resolve($readyBox, $packagingSlug, $pricedItems, $itemsSubtotal);
        $packagingAmount = max(0, (float) ($packaging['price'] ?? 0));
        $totalBeforeDiscount = $itemsSubtotal + $packagingAmount;
        $total = max(0, $totalBeforeDiscount - $discount['amount']);
        $message = $this->sanitizeMessage((string) ($payload['message'] ?? ''));

        $mainPricedItem = collect($pricedItems)->firstWhere('role', 'main');
        $mainPrice = (float) ($mainPricedItem['unit_price'] ?? 0);
        $warnings = $this->warnings((string) $payload['budget_band'], $mainPrice);
        if ($discount['removed']) {
            $warnings[] = [
                'code' => 'preset_discount_removed',
                'message' => app()->getLocale() === 'en'
                    ? 'The ready-box discount was removed because its products or packaging changed.'
                    : 'მზა ყუთის ფასდაკლება მოიხსნა, რადგან პროდუქტი ან შეფუთვა შეიცვალა.',
            ];
        }

        $readyBoxMetadata = $readyBox ? [
            'id' => (int) $readyBox->id,
            'slug' => $readyBox->slug,
            'title' => $readyBox->title,
            'discount_type' => $readyBox->discount_type,
            'discount_value' => (float) $readyBox->discount_value,
            'discount_retained' => (bool) $discount['retained'],
        ] : null;

        return [
            'recipient_type' => (string) $payload['recipient_type'],
            'occasion' => (string) $payload['occasion'],
            'budget_band' => (string) $payload['budget_band'],
            'packaging_slug' => $packagingSlug,
            'packaging_label' => $this->localizedLabel($packaging, $packagingSlug),
            'packaging_amount' => $packagingAmount,
            'discount_amount' => (float) $discount['amount'],
            'discount_source' => $discount['source'],
            'discount_type' => $discount['type'],
            'discount_value' => (float) $discount['value'],
            'discount_retained' => (bool) $discount['retained'],
            'preset_discount_removed' => (bool) $discount['removed'],
            'ready_box' => $readyBoxMetadata,
            'ready_box_slug' => $readyBox?->slug,
            'message' => $message,
            'items' => $pricedItems,
            'items_subtotal' => $itemsSubtotal,
            'capacity_units' => $capacityUnits,
            'original_total' => $totalBeforeDiscount,
            'total' => $total,
            'currency' => $currency,
            'total_formatted' => number_format($total, 2).' ₾',
            'warnings' => $warnings,
            'gift_groups_metadata' => [
                'recipient_type' => (string) $payload['recipient_type'],
                'occasion' => (string) $payload['occasion'],
                'budget_band' => (string) $payload['budget_band'],
                'packaging_slug' => $packagingSlug,
                'packaging_label' => $this->localizedLabel($packaging, $packagingSlug),
                'message' => $message,
                'items_count' => count($pricedItems),
                'items_subtotal' => $itemsSubtotal,
                'packaging_amount' => $packagingAmount,
                'discount_amount' => (float) $discount['amount'],
                'discount_source' => $discount['source'],
                'discount_type' => $discount['type'],
                'discount_value' => (float) $discount['value'],
                'discount_retained' => (bool) $discount['retained'],
                'ready_box' => $readyBoxMetadata,
                'total' => $total,
                'currency' => $currency,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function normalizeContext(array $payload): array
    {
        $readyBox = $payload['ready_box_slug'] ?? $payload['ready_box'] ?? null;
        if (is_array($readyBox)) {
            $readyBox = $readyBox['slug'] ?? null;
        }

        $payload['ready_box_slug'] = is_string($readyBox) && trim($readyBox) !== '' ? trim($readyBox) : null;
        $payload['recipient_type'] = $payload['recipient_type'] ?? 'other';
        $payload['occasion'] = $payload['occasion'] ?? 'just_because';
        $payload['budget_band'] = $payload['budget_band'] ?? 'all';
        $payload['packaging_slug'] = $payload['packaging_slug'] ?? 'standard';

        return $payload;
    }

    /** @return array<string, mixed> */
    private function validateShape(array $payload): array
    {
        $recipientKeys = array_unique(array_merge(array_keys((array) config('gift_builder.recipients', [])), ['other']));
        $occasionKeys = array_unique(array_merge(array_keys((array) config('gift_builder.occasions', [])), ['just_because']));
        $budgetKeys = array_unique(array_merge(array_keys((array) config('gift_builder.budget_bands', [])), ['all']));
        $packagingKeys = array_keys((array) config('gift_builder.packaging', []));

        return Validator::make($payload, [
            'recipient_type' => ['required', 'in:'.implode(',', $recipientKeys)],
            'occasion' => ['required', 'in:'.implode(',', $occasionKeys)],
            'budget_band' => ['required', 'in:'.implode(',', $budgetKeys)],
            'packaging_slug' => ['required', 'in:'.implode(',', $packagingKeys)],
            'ready_box_slug' => ['nullable', 'string', 'max:160'],
            'message' => ['nullable', 'string', 'max:'.(int) config('gift_builder.message_max_length', 300)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['nullable', 'integer', 'in:1'],
            'items.*.role' => ['required', 'in:main,addon'],
        ])->validate();
    }

    /** @return array<int, array{variant_id: int, quantity: int, role: string}> */
    private function normalizeItems(array $items): array
    {
        return array_values(array_map(fn (array $item): array => [
            'variant_id' => (int) $item['variant_id'],
            'quantity' => 1,
            'role' => (string) $item['role'],
        ], $items));
    }

    private function assertProductEligible(Product $product, ProductVariant $variant, string $role): void
    {
        $productRole = $product->gift_builder_role ?: 'none';
        $roleAllowed = $role === 'main'
            ? in_array($productRole, ['main', 'both'], true)
            : in_array($productRole, ['addon', 'both'], true);

        if (! $product->is_active || ! $product->gift_builder_enabled || $product->fulfillment_mode !== 'local_stock' || ! $roleAllowed) {
            throw ValidationException::withMessages([
                'items' => __('storefront.gift_builder.product_ineligible'),
            ]);
        }

        if (! $variant->canFulfillQuantity(1)) {
            throw ValidationException::withMessages([
                'items' => __('storefront.gift_builder.out_of_stock'),
            ]);
        }
    }

    private function readyBox(?string $slug): ?ReadyGiftBox
    {
        if (! $slug) {
            return null;
        }

        $box = ReadyGiftBox::query()->active()->with('items')->where('slug', $slug)->first();
        if (! $box) {
            throw ValidationException::withMessages([
                'ready_box_slug' => app()->getLocale() === 'en'
                    ? 'The selected ready gift box is unavailable.'
                    : 'არჩეული მზა სასაჩუქრე ყუთი ხელმისაწვდომი აღარ არის.',
            ]);
        }

        return $box;
    }

    /** @return array<int, array{code: string, message: string}> */
    private function warnings(string $budgetBand, float $mainPrice): array
    {
        $band = (array) config("gift_builder.budget_bands.{$budgetBand}", []);
        $max = Arr::get($band, 'max');

        if ($max !== null && $mainPrice > (float) $max) {
            return [[
                'code' => 'budget_overage',
                'message' => __('storefront.gift_builder.budget_overage', [
                    'amount' => number_format($mainPrice - (float) $max, 2).' ₾',
                ]),
            ]];
        }

        return [];
    }

    private function sanitizeMessage(string $message): string
    {
        $message = trim(strip_tags($message));
        $message = preg_replace('/\s+/u', ' ', $message) ?: '';

        return Str::limit($message, (int) config('gift_builder.message_max_length', 300), '');
    }

    private function localizedLabel(array $config, string $fallback): string
    {
        return app()->getLocale() === 'ka'
            ? ($config['label_ka'] ?? $config['label_en'] ?? $fallback)
            : ($config['label_en'] ?? Str::headline($fallback));
    }
}
