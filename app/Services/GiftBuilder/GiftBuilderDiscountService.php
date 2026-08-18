<?php

namespace App\Services\GiftBuilder;

use App\Models\ReadyGiftBox;

class GiftBuilderDiscountService
{
    /**
     * @param  array<int, array{product_id: int, role: string}>  $pricedItems
     * @return array{amount: float, source: string, type: ?string, value: float, retained: bool, removed: bool}
     */
    public function resolve(?ReadyGiftBox $box, string $packagingSlug, array $pricedItems, float $itemsSubtotal): array
    {
        if ($box) {
            $retained = $box->is_active && $this->matchesBox($box, $packagingSlug, $pricedItems);

            return [
                'amount' => $retained ? $this->calculate($box->discount_type, (float) $box->discount_value, $itemsSubtotal) : 0.0,
                'source' => $retained ? 'ready_gift_box' : 'none',
                'type' => $retained ? $box->discount_type : null,
                'value' => $retained ? (float) $box->discount_value : 0.0,
                'retained' => $retained,
                'removed' => ! $retained,
            ];
        }

        $config = (array) config('gift_builder.discount', []);
        $minItems = (int) ($config['min_items'] ?? 2);
        $value = (float) ($config['amount'] ?? 0);
        $type = (string) ($config['type'] ?? 'fixed');
        $eligible = $value > 0 && count($pricedItems) >= $minItems;

        return [
            'amount' => $eligible ? $this->calculate($type, $value, $itemsSubtotal) : 0.0,
            'source' => $eligible ? 'builder' : 'none',
            'type' => $eligible ? $type : null,
            'value' => $eligible ? $value : 0.0,
            'retained' => false,
            'removed' => false,
        ];
    }

    /** @param array<int, array{product_id: int, role: string}> $pricedItems */
    public function matchesBox(ReadyGiftBox $box, string $packagingSlug, array $pricedItems): bool
    {
        if ($packagingSlug !== $box->packaging_slug) {
            return false;
        }

        $box->loadMissing('items');

        $expected = $box->items
            ->map(fn ($item): string => (int) $item->product_id.':'.$item->role)
            ->sort()
            ->values()
            ->all();
        $actual = collect($pricedItems)
            ->map(fn (array $item): string => (int) $item['product_id'].':'.$item['role'])
            ->sort()
            ->values()
            ->all();

        return $expected === $actual;
    }

    public function calculate(?string $type, float $value, float $itemsSubtotal): float
    {
        $value = max(0, $value);
        $itemsSubtotal = max(0, $itemsSubtotal);

        if ($type === 'none' || $value <= 0 || $itemsSubtotal <= 0) {
            return 0.0;
        }

        if ($type === 'percent') {
            return min($itemsSubtotal, round($itemsSubtotal * min($value, 100) / 100, 2));
        }

        return min($itemsSubtotal, round($value, 2));
    }
}
