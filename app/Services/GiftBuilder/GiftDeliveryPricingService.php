<?php

namespace App\Services\GiftBuilder;

class GiftDeliveryPricingService
{
    /** @return array{delivery_amount: float, delivery_amount_minor: int, free_delivery_remaining: float, free_delivery_remaining_minor: int} */
    public function quote(bool $hasGiftBox, int $goodsTotalMinor, bool $hasItems = true): array
    {
        if (! $hasGiftBox || ! $hasItems) {
            return [
                'delivery_amount' => 0.0,
                'delivery_amount_minor' => 0,
                'free_delivery_remaining' => 0.0,
                'free_delivery_remaining_minor' => 0,
            ];
        }

        $settings = app(GiftBuilderSettingsService::class)->current();
        $feeMinor = (int) round((float) $settings['delivery_fee'] * 100, 0, PHP_ROUND_HALF_UP);
        $thresholdMinor = (int) round((float) $settings['free_delivery_threshold'] * 100, 0, PHP_ROUND_HALF_UP);
        $deliveryMinor = $goodsTotalMinor >= $thresholdMinor ? 0 : $feeMinor;
        $remainingMinor = max(0, $thresholdMinor - $goodsTotalMinor);

        return [
            'delivery_amount' => $deliveryMinor / 100,
            'delivery_amount_minor' => $deliveryMinor,
            'free_delivery_remaining' => $remainingMinor / 100,
            'free_delivery_remaining_minor' => $remainingMinor,
        ];
    }
}
