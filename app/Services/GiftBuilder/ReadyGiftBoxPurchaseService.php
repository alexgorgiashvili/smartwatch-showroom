<?php

namespace App\Services\GiftBuilder;

use App\Models\ReadyGiftBox;
use Illuminate\Validation\ValidationException;

class ReadyGiftBoxPurchaseService
{
    /** @return array<string, mixed> */
    public function pricingPayload(ReadyGiftBox $box, array $payload): array
    {
        $box->loadMissing(['items.product.variants', 'items.defaultVariant']);

        $requestedByItem = $this->validateRequestedItems($payload, $box);
        $items = $box->items->map(function ($boxItem) use ($requestedByItem): array {
            $product = $boxItem->product;
            $product->variants->each(fn ($variant) => $variant->setRelation('product', $product));
            $requestedVariantId = (int) $requestedByItem[(int) $boxItem->id];
            $variant = $product->variants->firstWhere('id', $requestedVariantId);

            if (! $variant || ! $variant->canFulfillQuantity(1)) {
                throw ValidationException::withMessages([
                    'items' => app()->getLocale() === 'en'
                        ? "The selected variant for {$product->name} is unavailable."
                        : "„{$product->name}“-ის არჩეული ფერი ხელმისაწვდომი აღარ არის.",
                ]);
            }

            return [
                'variant_id' => (int) $variant->id,
                'quantity' => 1,
                'role' => $boxItem->role,
            ];
        })->values()->all();

        return [
            'recipient_type' => $payload['recipient_type'] ?? 'other',
            'occasion' => $payload['occasion'] ?? 'just_because',
            'budget_band' => $payload['budget_band'] ?? 'all',
            'packaging_slug' => $box->packaging_slug,
            'ready_box_slug' => $box->slug,
            'message' => $payload['message'] ?? $payload['greeting_message'] ?? null,
            'items' => $items,
        ];
    }

    /** @return array<int, int> */
    private function validateRequestedItems(array $payload, ReadyGiftBox $box): array
    {
        $items = $payload['items'] ?? null;
        if (! is_array($items) || count($items) !== $box->items->count()) {
            throw $this->invalidSelection();
        }

        $requested = [];
        foreach ($items as $item) {
            if (! is_array($item) || ! isset($item['item_id'], $item['variant_id']) || ! is_numeric($item['item_id']) || ! is_numeric($item['variant_id'])) {
                throw $this->invalidSelection();
            }

            $itemId = (int) $item['item_id'];
            if (isset($requested[$itemId])) {
                throw $this->invalidSelection();
            }

            $requested[$itemId] = (int) $item['variant_id'];
        }

        $expectedIds = $box->items->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
        $actualIds = collect(array_keys($requested))->sort()->values()->all();
        if ($actualIds !== $expectedIds) {
            throw $this->invalidSelection();
        }

        return $requested;
    }

    private function invalidSelection(): ValidationException
    {
        return ValidationException::withMessages([
            'items' => app()->getLocale() === 'en'
                ? 'Choose one available variant for every item in this gift box.'
                : 'ყუთის თითოეული პროდუქტისთვის აირჩიე ერთი ხელმისაწვდომი ფერი.',
        ]);
    }
}
