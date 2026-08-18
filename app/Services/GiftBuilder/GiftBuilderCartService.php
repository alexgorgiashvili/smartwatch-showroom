<?php

namespace App\Services\GiftBuilder;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GiftBuilderCartService
{
    public function addGroup(Request $request, array $pricedGiftBox): array
    {
        $groupId = (string) Str::uuid();
        $groups = $request->session()->get('gift_cart_groups', []);

        $groups[$groupId] = [
            'recipient_type' => $pricedGiftBox['recipient_type'],
            'occasion' => $pricedGiftBox['occasion'],
            'budget_band' => $pricedGiftBox['budget_band'],
            'packaging_slug' => $pricedGiftBox['packaging_slug'],
            'packaging_label' => $pricedGiftBox['packaging_label'],
            'packaging_amount' => (float) $pricedGiftBox['packaging_amount'],
            'discount_amount' => (float) $pricedGiftBox['discount_amount'],
            'discount_source' => $pricedGiftBox['discount_source'] ?? 'none',
            'discount_type' => $pricedGiftBox['discount_type'] ?? null,
            'discount_value' => (float) ($pricedGiftBox['discount_value'] ?? 0),
            'discount_retained' => (bool) ($pricedGiftBox['discount_retained'] ?? false),
            'ready_box' => $pricedGiftBox['ready_box'] ?? null,
            'campaign' => (array) $request->session()->get('gift_campaign', []),
            'message' => $pricedGiftBox['message'],
            'items' => collect($pricedGiftBox['items'])->map(fn (array $item): array => [
                'variant_id' => (int) $item['variant_id'],
                'quantity' => 1,
                'role' => $item['role'],
                'sort_order' => (int) $item['sort_order'],
            ])->values()->all(),
        ];

        $request->session()->put('gift_cart_groups', $groups);

        return [
            'group_id' => $groupId,
            'group' => $groups[$groupId],
        ];
    }

    public function removeGroup(Request $request, string $groupId): void
    {
        $groups = $request->session()->get('gift_cart_groups', []);
        unset($groups[$groupId]);
        $request->session()->put('gift_cart_groups', $groups);
    }
}
