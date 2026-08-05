<?php

namespace App\Services\GiftBuilder;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GiftBuilderPricingService
{
    public function price(array $payload): array
    {
        $payload = $this->validateShape($payload);
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

        foreach ($items as $index => $item) {
            $variant = $variants->get($item['variant_id']);
            if (! $variant || ! $variant->product) {
                throw ValidationException::withMessages([
                    'items' => __('storefront.gift_builder.product_unavailable'),
                ]);
            }

            $product = $variant->product;
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

            $subtotal = $unitPrice * $item['quantity'];
            $itemsSubtotal += $subtotal;
            $capacityUnits += max(1, (int) ($product->gift_capacity_units ?: 1)) * $item['quantity'];

            $pricedItems[] = [
                'variant_id' => (int) $variant->id,
                'product_id' => (int) $product->id,
                'role' => $item['role'],
                'quantity' => 1,
                'sort_order' => $index + 1,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'product_name' => $product->name,
                'variant_name' => $variant->localizedName(),
                'image' => $product->primaryImage?->url ?? asset('storage/images/home/smart-watch3.jpg'),
                'compatibility_tags' => array_values((array) ($product->gift_compatibility_tags ?? [])),
                'capacity_units' => max(1, (int) ($product->gift_capacity_units ?: 1)),
                'fulfillment_mode' => $product->fulfillment_mode,
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
        $packaging = (array) config("gift_builder.packaging.{$packagingSlug}");
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

        $packagingAmount = (float) ($packaging['price'] ?? 0);
        $discountAmount = $this->discountAmount($itemsSubtotal, count($pricedItems));
        $total = max(0, $itemsSubtotal + $packagingAmount - $discountAmount);
        $message = $this->sanitizeMessage((string) ($payload['message'] ?? ''));

        $warnings = $this->warnings((string) $payload['budget_band'], $total);

        return [
            'recipient_type' => (string) $payload['recipient_type'],
            'occasion' => (string) $payload['occasion'],
            'budget_band' => (string) $payload['budget_band'],
            'packaging_slug' => $packagingSlug,
            'packaging_label' => $this->localizedLabel($packaging, $packagingSlug),
            'packaging_amount' => $packagingAmount,
            'discount_amount' => $discountAmount,
            'message' => $message,
            'items' => $pricedItems,
            'items_subtotal' => $itemsSubtotal,
            'capacity_units' => $capacityUnits,
            'total' => $total,
            'total_formatted' => number_format($total, 2) . ' ₾',
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
                'discount_amount' => $discountAmount,
                'total' => $total,
            ],
        ];
    }

    private function validateShape(array $payload): array
    {
        $recipientKeys = array_keys((array) config('gift_builder.recipients', []));
        $occasionKeys = array_keys((array) config('gift_builder.occasions', []));
        $budgetKeys = array_keys((array) config('gift_builder.budget_bands', []));
        $packagingKeys = array_keys((array) config('gift_builder.packaging', []));

        return Validator::make($payload, [
            'recipient_type' => ['required', 'in:' . implode(',', $recipientKeys)],
            'occasion' => ['required', 'in:' . implode(',', $occasionKeys)],
            'budget_band' => ['required', 'in:' . implode(',', $budgetKeys)],
            'packaging_slug' => ['required', 'in:' . implode(',', $packagingKeys)],
            'message' => ['nullable', 'string', 'max:' . (int) config('gift_builder.message_max_length', 300)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['nullable', 'integer', 'in:1'],
            'items.*.role' => ['required', 'in:main,addon'],
        ])->validate();
    }

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

    private function discountAmount(float $itemsSubtotal, int $itemsCount): float
    {
        $config = (array) config('gift_builder.discount', []);
        $minItems = (int) ($config['min_items'] ?? 2);
        $amount = (float) ($config['amount'] ?? 0);

        if ($amount <= 0 || $itemsCount < $minItems) {
            return 0.0;
        }

        if (($config['type'] ?? 'fixed') === 'percent') {
            return round($itemsSubtotal * min($amount, 100) / 100, 2);
        }

        return min($itemsSubtotal, $amount);
    }

    private function warnings(string $budgetBand, float $total): array
    {
        $band = (array) config("gift_builder.budget_bands.{$budgetBand}", []);
        $max = Arr::get($band, 'max');

        if ($max !== null && $total > (float) $max) {
            return [
                [
                    'code' => 'budget_overage',
                    'message' => __('storefront.gift_builder.budget_overage', [
                        'amount' => number_format($total - (float) $max, 2) . ' ₾',
                    ]),
                ],
            ];
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
        $locale = app()->getLocale();

        return $locale === 'ka'
            ? ($config['label_ka'] ?? $config['label_en'] ?? $fallback)
            : ($config['label_en'] ?? Str::headline($fallback));
    }
}
