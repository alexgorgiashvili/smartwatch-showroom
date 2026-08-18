<?php

namespace App\Services\GiftBuilder;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReadyGiftBox;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReadyGiftBoxManager
{
    public function __construct(private readonly ReadyGiftBoxAvailabilityService $availability) {}

    public function create(array $data): ReadyGiftBox
    {
        return DB::transaction(function () use ($data): ReadyGiftBox {
            $box = ReadyGiftBox::query()->create($this->boxAttributes($data));
            $this->syncItems($box, $data);
            $this->assertCanActivate($box);

            return $box->fresh(['items.product.variants', 'items.defaultVariant']);
        });
    }

    public function update(ReadyGiftBox $box, array $data): ReadyGiftBox
    {
        return DB::transaction(function () use ($box, $data): ReadyGiftBox {
            $box->update($this->boxAttributes($data));
            $this->syncItems($box, $data);
            $this->assertCanActivate($box);

            return $box->fresh(['items.product.variants', 'items.defaultVariant']);
        });
    }

    public function setActive(ReadyGiftBox $box, bool $active): ReadyGiftBox
    {
        if ($active) {
            $box->load(['items.product.primaryImage', 'items.product.variants', 'items.defaultVariant']);
            $report = $this->availability->report($box);
            if (! $report['available']) {
                throw ValidationException::withMessages([
                    'is_active' => collect($report['reasons'])->pluck('message')->implode(' '),
                ]);
            }
        }

        $box->update(['is_active' => $active]);

        return $box;
    }

    /** @return array<string, mixed> */
    private function boxAttributes(array $data): array
    {
        $discountType = in_array(($data['discount_type'] ?? 'none'), ['none', 'fixed', 'percent'], true)
            ? $data['discount_type']
            : 'none';

        return [
            'slug' => trim((string) $data['slug']),
            'title_ka' => trim((string) $data['title_ka']),
            'title_en' => $this->nullableString($data['title_en'] ?? null),
            'short_description_ka' => $this->nullableString($data['short_description_ka'] ?? null),
            'short_description_en' => $this->nullableString($data['short_description_en'] ?? null),
            'badge_ka' => $this->nullableString($data['badge_ka'] ?? null),
            'badge_en' => $this->nullableString($data['badge_en'] ?? null),
            'cover_image_path' => $this->nullableString($data['cover_image_path'] ?? null),
            'theme_key' => $data['theme_key'] ?? 'grape',
            'packaging_slug' => $data['packaging_slug'] ?? 'standard',
            'discount_type' => $discountType,
            'discount_value' => $discountType === 'none' ? 0 : max(0, (float) ($data['discount_value'] ?? 0)),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
        ];
    }

    private function syncItems(ReadyGiftBox $box, array $data): void
    {
        $mainProductId = (int) $data['main_product_id'];
        $addons = collect((array) ($data['addons'] ?? []))
            ->filter(fn ($item): bool => is_array($item) && ! empty($item['product_id']))
            ->values();
        $productIds = collect([$mainProductId])->merge($addons->pluck('product_id')->map(fn ($id): int => (int) $id));

        if ($productIds->unique()->count() !== $productIds->count()) {
            throw ValidationException::withMessages([
                'addons' => 'ერთი პროდუქტის დამატება მხოლოდ ერთხელ შეიძლება.',
            ]);
        }

        if ($productIds->count() > (int) config('gift_builder.max_items', 4)) {
            throw ValidationException::withMessages([
                'addons' => 'ყუთში მაქსიმუმ '.config('gift_builder.max_items', 4).' პროდუქტი შეიძლება.',
            ]);
        }

        $products = Product::query()->whereIn('id', $productIds)->get()->keyBy('id');
        if ($products->count() !== $productIds->count()) {
            throw ValidationException::withMessages(['main_product_id' => 'არჩეული პროდუქტი ვერ მოიძებნა.']);
        }

        $rows = [[
            'product_id' => $mainProductId,
            'default_variant_id' => $this->validatedVariantId($products->get($mainProductId), $data['main_default_variant_id'] ?? null),
            'role' => 'main',
            'sort_order' => 0,
        ]];

        foreach ($addons as $index => $addon) {
            $productId = (int) $addon['product_id'];
            $rows[] = [
                'product_id' => $productId,
                'default_variant_id' => $this->validatedVariantId($products->get($productId), $addon['default_variant_id'] ?? null),
                'role' => 'addon',
                'sort_order' => $index + 1,
            ];
        }

        $desiredProductIds = collect($rows)->pluck('product_id')->map(fn ($id): int => (int) $id)->all();
        foreach ($rows as $row) {
            $box->items()->updateOrCreate(
                ['product_id' => $row['product_id']],
                $row,
            );
        }
        $box->items()->whereNotIn('product_id', $desiredProductIds)->delete();
        $box->unsetRelation('items');
    }

    private function validatedVariantId(Product $product, mixed $variantId): ?int
    {
        if (! $variantId) {
            return null;
        }

        $variant = ProductVariant::query()->whereKey((int) $variantId)->first();
        if (! $variant || (int) $variant->product_id !== (int) $product->id) {
            throw ValidationException::withMessages([
                'items' => "„{$product->name}“-ის ნაგულისხმევი ფერი არასწორია.",
            ]);
        }

        return (int) $variant->id;
    }

    private function assertCanActivate(ReadyGiftBox $box): void
    {
        if (! $box->is_active) {
            return;
        }

        $box->load(['items.product.primaryImage', 'items.product.variants', 'items.defaultVariant']);
        $report = $this->availability->report($box);
        if (! $report['available']) {
            throw ValidationException::withMessages([
                'is_active' => collect($report['reasons'])->pluck('message')->implode(' '),
            ]);
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
