<?php

namespace App\Services\GiftBuilder;

use App\Models\Product;
use App\Models\ReadyGiftBox;
use App\Models\ReadyGiftBoxItem;

class ReadyGiftBoxAvailabilityService
{
    /** @return array{available: bool, reasons: array<int, array{code: string, message: string}>} */
    public function report(ReadyGiftBox $box): array
    {
        $box->loadMissing([
            'items.product.primaryImage',
            'items.product.variants',
            'items.defaultVariant',
        ]);

        $reasons = [];
        $items = $box->items;
        $mainItems = $items->where('role', 'main');
        $maxItems = (int) config('gift_builder.max_items', 4);

        if ($mainItems->count() !== 1) {
            $reasons[] = $this->reason('main_count', 'ყუთს ზუსტად ერთი მთავარი საჩუქარი უნდა ჰქონდეს.');
        }

        if ($items->isEmpty() || $items->count() > $maxItems) {
            $reasons[] = $this->reason('items_count', "ყუთში უნდა იყოს 1-{$maxItems} პროდუქტი.");
        }

        if ($items->pluck('product_id')->unique()->count() !== $items->count()) {
            $reasons[] = $this->reason('duplicate_product', 'ერთი და იგივე პროდუქტი ყუთში ორჯერ ვერ დაემატება.');
        }

        $packaging = (array) config("gift_builder.packaging.{$box->packaging_slug}", []);
        if ($packaging === []) {
            $reasons[] = $this->reason('packaging_missing', 'არჩეული შეფუთვა აღარ არსებობს.');
        }

        $capacityUnits = 0;
        foreach ($items as $item) {
            $product = $item->product;
            if (! $product) {
                $reasons[] = $this->reason('product_missing', 'ყუთის ერთ-ერთი პროდუქტი წაშლილია.');

                continue;
            }

            $capacityUnits += max(1, (int) ($product->gift_capacity_units ?: 1));
            $this->appendProductReasons($reasons, $item, $product);
        }

        $packagingCapacity = (int) ($packaging['capacity_units'] ?? 0);
        if ($packaging !== [] && $packagingCapacity > 0 && $capacityUnits > $packagingCapacity) {
            $reasons[] = $this->reason('packaging_capacity', 'არჩეულ შეფუთვაში პროდუქტები ვერ ეტევა.');
        }

        $mainProduct = $mainItems->first()?->product;
        if ($mainProduct) {
            $mainTags = array_values((array) ($mainProduct->gift_compatibility_tags ?? []));
            foreach ($items->where('role', 'addon') as $addonItem) {
                $addonTags = array_values((array) ($addonItem->product?->gift_compatibility_tags ?? []));
                if ($addonTags !== [] && array_intersect($mainTags, $addonTags) === []) {
                    $reasons[] = $this->reason(
                        'incompatible_addon',
                        "დამატება „{$addonItem->product?->name}“ მთავარ საჩუქართან შეუთავსებელია."
                    );
                }
            }
        }

        $reasons = collect($reasons)->unique('code')->values()->all();

        return [
            'available' => $reasons === [],
            'reasons' => $reasons,
        ];
    }

    /** @param array<int, array{code: string, message: string}> $reasons */
    private function appendProductReasons(array &$reasons, ReadyGiftBoxItem $item, Product $product): void
    {
        $prefix = trim((string) $product->name);
        $product->variants->each(fn ($variant) => $variant->setRelation('product', $product));
        $productRole = $product->gift_builder_role ?: 'none';
        $roleAllowed = $item->role === 'main'
            ? in_array($productRole, ['main', 'both'], true)
            : in_array($productRole, ['addon', 'both'], true);

        if (! $product->is_active) {
            $reasons[] = $this->reason('inactive_product_'.$product->id, "„{$prefix}“ არააქტიურია.");
        }

        if (! $product->gift_builder_enabled || ! $roleAllowed || $product->fulfillment_mode !== 'local_stock') {
            $reasons[] = $this->reason('ineligible_product_'.$product->id, "„{$prefix}“ Gift Builder-ისთვის ხელმისაწვდომი აღარ არის.");
        }

        if ((float) ($product->sale_price ?? $product->price ?? 0) <= 0) {
            $reasons[] = $this->reason('invalid_price_'.$product->id, "„{$prefix}“-ს სწორი ფასი არ აქვს.");
        }

        if (! $product->variants->contains(fn ($variant): bool => $variant->canFulfillQuantity(1))) {
            $reasons[] = $this->reason('out_of_stock_'.$product->id, "„{$prefix}“ მარაგში აღარ არის.");
        }

        if ($item->default_variant_id && (int) $item->defaultVariant?->product_id !== (int) $product->id) {
            $reasons[] = $this->reason('invalid_default_variant_'.$product->id, "„{$prefix}“-ის ნაგულისხმევი ფერი არასწორია.");
        }
    }

    /** @return array{code: string, message: string} */
    private function reason(string $code, string $message): array
    {
        return compact('code', 'message');
    }
}
