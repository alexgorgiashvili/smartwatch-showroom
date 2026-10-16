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
        if ((int) $box->pricing_version === 2) {
            return $this->reportV2($box);
        }

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

        foreach ($items as $item) {
            $product = $item->product;
            if (! $product) {
                $reasons[] = $this->reason('product_missing', 'ყუთის ერთ-ერთი პროდუქტი წაშლილია.');

                continue;
            }

            $this->appendProductReasons($reasons, $item, $product);
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

    /** @return array{available: bool, reasons: array<int, array{code: string, message: string}>} */
    private function reportV2(ReadyGiftBox $box): array
    {
        $box->loadMissing([
            'items.product.primaryImage',
            'items.product.variants',
            'items.defaultVariant',
        ]);
        $items = $box->items;
        $reasons = [];
        if ($items->count() < 2 || $items->count() > (int) config('gift_builder.max_items', 4)) {
            $reasons[] = $this->reason('items_count', 'ბოქსში უნდა იყოს 2–4 განსხვავებული პროდუქტი.');
        }
        if ($items->pluck('product_id')->unique()->count() !== $items->count()) {
            $reasons[] = $this->reason('duplicate_product', 'ერთი და იგივე პროდუქტი ბოქსში ორჯერ ვერ დაემატება.');
        }
        $packages = app(GiftBuilderSettingsService::class)->packagingOptions();
        if (! isset($packages[$box->packaging_slug]) || ! $packages[$box->packaging_slug]['active']) {
            $reasons[] = $this->reason('packaging_missing', 'არჩეული შეფუთვა აღარ არის აქტიური.');
        }

        $compatibility = [];
        foreach ($items as $item) {
            $product = $item->product;
            if (! $product) {
                $reasons[] = $this->reason('product_missing', 'ბოქსის ერთ-ერთი პროდუქტი წაშლილია.');
                continue;
            }
            $product->variants->each(fn ($variant) => $variant->setRelation('product', $product));
            $name = (string) $product->name;
            if (! $product->gift_builder_enabled || $product->fulfillment_mode !== 'local_stock') {
                $reasons[] = $this->reason('ineligible_product_'.$product->id, "„{$name}“ Gift Builder-ისთვის ხელმისაწვდომი აღარ არის.");
            }
            if ((float) $product->gift_box_price <= 0) {
                $reasons[] = $this->reason('missing_box_price_'.$product->id, "„{$name}“-ს ბოქსის ფასი არ აქვს.");
            }
            if (! $product->variants->contains(fn ($variant): bool => $variant->canFulfillQuantity(1))) {
                $reasons[] = $this->reason('out_of_stock_'.$product->id, "„{$name}“ მარაგში აღარ არის.");
            }
            if (! $item->default_variant_id) {
                $reasons[] = $this->reason('missing_default_variant_'.$product->id, "„{$name}“-ისთვის ნაგულისხმევი ფერი ჯერ არ არის არჩეული.");
            } elseif ((int) $item->defaultVariant?->product_id !== (int) $product->id) {
                $reasons[] = $this->reason('invalid_default_variant_'.$product->id, "„{$name}“-ის ნაგულისხმევი ფერი არასწორია.");
            } elseif (! $item->defaultVariant->canFulfillQuantity(1)) {
                $reasons[] = $this->reason('unavailable_default_variant_'.$product->id, "„{$name}“-ის ნაგულისხმევი ფერი მარაგში აღარ არის.");
            }
            $compatibility[] = [
                'id' => (int) $product->id,
                'requires' => (bool) $product->gift_requires_compatibility,
                'tags' => array_values((array) ($product->gift_compatibility_tags ?? [])),
                'name' => $name,
            ];
        }
        foreach ($compatibility as $selected) {
            if ($selected['requires'] && ! collect($compatibility)->contains(
                fn (array $peer): bool => $peer['id'] !== $selected['id']
                    && $selected['tags'] !== []
                    && array_intersect($selected['tags'], $peer['tags']) !== []
            )) {
                $reasons[] = $this->reason('incompatible_product_'.$selected['id'], "„{$selected['name']}“-ს შესაბამისი თავსებადი პროდუქტი სჭირდება.");
            }
        }

        $reasons = collect($reasons)->unique('code')->values()->all();
        return ['available' => $reasons === [], 'reasons' => $reasons];
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
        $visibilityAllowed = $product->is_active || ($item->role === 'addon' && $productRole === 'addon');

        if (! $visibilityAllowed) {
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
