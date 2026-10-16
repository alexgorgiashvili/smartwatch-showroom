<?php

namespace App\Services\GiftBuilder;

use App\Models\ReadyGiftBox;
use Illuminate\Support\Collection;

class GiftEconomicsService
{
    /** @return array<string, mixed> */
    public function evaluateReadyBox(ReadyGiftBox $box): array
    {
        $settings = app(GiftBuilderSettingsService::class)->current();
        $packages = (array) ($settings['packaging_options'] ?? []);
        $products = [];
        $missing = [];
        $revenue = 0.0;
        $revenueComplete = true;

        foreach ($box->items as $item) {
            $product = $item->product;
            if (! $product) {
                $revenueComplete = false;
                $missing[] = 'product_missing:'.$item->product_id;
                continue;
            }
            $cost = $product->gift_landed_cost === null ? null : (float) $product->gift_landed_cost;
            $status = (string) ($product->gift_cost_status ?: 'unknown');
            if ($cost === null || $status === 'unknown') $missing[] = 'product_cost:'.$product->id;
            if ($cost !== null && $status !== 'confirmed') $missing[] = 'estimated_product_cost:'.$product->id;
            $boxPrice = $product->gift_box_price === null ? null : (float) $product->gift_box_price;
            if ($boxPrice === null || $boxPrice <= 0) {
                $revenueComplete = false;
                $missing[] = 'gift_box_price:'.$product->id;
            } else {
                $revenue += $boxPrice;
            }
            $products[] = [
                'product_id' => (int) $product->id,
                'variant_id' => $item->default_variant_id ? (int) $item->default_variant_id : null,
                'quantity' => 1,
                'box_unit_price' => $boxPrice,
                'landed_cost' => $cost,
                'cost_status' => $status,
            ];
        }

        $slug = (string) $box->packaging_slug;
        $package = (array) ($packages[$slug] ?? []);
        $packagePrice = isset($package['price']) ? (float) $package['price'] : null;
        $packageCost = isset($package['cost']) ? (float) $package['cost'] : null;
        if ($packageCost === null) $missing[] = 'packaging_cost:'.$slug;
        if ($packagePrice === null) $revenueComplete = false;
        else $revenue += $packagePrice;
        $deliveryRevenue = $revenueComplete
            ? app(GiftDeliveryPricingService::class)->quote(true, (int) round($revenue * 100, 0, PHP_ROUND_HALF_UP))['delivery_amount']
            : 0.0;
        $revenue += $deliveryRevenue;
        $ad = $settings['ad_cost_per_order'] === null ? null : (float) $settings['ad_cost_per_order'];
        $courier = $settings['courier_cost_per_order'] === null ? null : (float) $settings['courier_cost_per_order'];
        if ($ad === null) $missing[] = 'ad_cost_per_order';
        if ($courier === null) $missing[] = 'courier_cost_per_order';

        return $this->evaluate([
            'version' => 1,
            'products' => $products,
            'packaging' => [[
                'slug' => $slug,
                'quantity' => 1,
                'customer_price' => $packagePrice,
                'actual_cost' => $packageCost,
            ]],
            'gift_revenue' => $revenueComplete ? round($revenue, 2) : null,
            'delivery_revenue' => $revenueComplete ? round($deliveryRevenue, 2) : null,
            'ad_cost_per_order' => $ad,
            'courier_cost_per_order' => $courier,
            'missing_or_estimated_inputs' => array_values(array_unique($missing)),
        ]);
    }

    /** @return array<string, mixed> */
    public function snapshot(Collection $giftGroups, float $deliveryRevenue = 0.0): array
    {
        $settings = app(GiftBuilderSettingsService::class)->current();
        $packages = (array) ($settings['packaging_options'] ?? []);
        $products = [];
        $packaging = [];
        $missing = [];
        $giftRevenue = 0.0;

        foreach ($giftGroups as $group) {
            foreach ($group['items'] as $line) {
                $product = $line['product'];
                $cost = $product->gift_landed_cost === null ? null : (float) $product->gift_landed_cost;
                $status = (string) ($product->gift_cost_status ?: 'unknown');
                if ($cost === null || $status === 'unknown') {
                    $missing[] = 'product_cost:'.$product->id;
                }
                if ($cost !== null && $status !== 'confirmed') {
                    $missing[] = 'estimated_product_cost:'.$product->id;
                }
                $products[] = [
                    'product_id' => (int) $product->id,
                    'variant_id' => (int) $line['variant']->id,
                    'quantity' => (int) $line['quantity'],
                    'box_unit_price' => (float) $line['unit_price'],
                    'landed_cost' => $cost,
                    'cost_status' => $status,
                ];
                $giftRevenue += (float) $line['subtotal'];
            }

            $slug = (string) $group['packaging_slug'];
            $cost = data_get($packages, "{$slug}.cost");
            $cost = $cost === null ? null : (float) $cost;
            if ($cost === null) {
                $missing[] = 'packaging_cost:'.$slug;
            }
            $packaging[] = [
                'slug' => $slug,
                'quantity' => 1,
                'customer_price' => (float) $group['packaging_amount'],
                'actual_cost' => $cost,
            ];
            $giftRevenue += (float) $group['packaging_amount'];
        }

        $ad = $settings['ad_cost_per_order'] === null ? null : (float) $settings['ad_cost_per_order'];
        $courier = $settings['courier_cost_per_order'] === null ? null : (float) $settings['courier_cost_per_order'];
        $giftRevenue += $deliveryRevenue;
        if ($ad === null) $missing[] = 'ad_cost_per_order';
        if ($courier === null) $missing[] = 'courier_cost_per_order';

        return [
            'version' => 1,
            'products' => $products,
            'packaging' => $packaging,
            'gift_revenue' => round($giftRevenue, 2),
            'delivery_revenue' => round($deliveryRevenue, 2),
            'ad_cost_per_order' => $ad,
            'courier_cost_per_order' => $courier,
            'missing_or_estimated_inputs' => array_values(array_unique($missing)),
        ];
    }

    /** @return array<string, mixed> */
    public function evaluate(?array $snapshot, ?float $revenue = null): array
    {
        if (! $snapshot) {
            return [
                'status' => 'unavailable',
                'revenue' => $revenue,
                'known_costs' => null,
                'probable_remaining_amount' => null,
                'is_net_profit' => false,
                'missing_inputs' => ['gift_cost_snapshot'],
            ];
        }

        $revenue ??= isset($snapshot['gift_revenue']) ? (float) $snapshot['gift_revenue'] : null;
        $missing = (array) ($snapshot['missing_or_estimated_inputs'] ?? []);
        $knownCosts = 0.0;
        $hasKnownCost = false;
        foreach ((array) ($snapshot['products'] ?? []) as $product) {
            if (($product['landed_cost'] ?? null) === null) {
                $missing[] = 'product_cost:'.($product['product_id'] ?? 'unknown');
                continue;
            }
            $knownCosts += (float) $product['landed_cost'] * (int) ($product['quantity'] ?? 1);
            $hasKnownCost = true;
        }
        foreach ((array) ($snapshot['packaging'] ?? []) as $package) {
            if (($package['actual_cost'] ?? null) === null) {
                $missing[] = 'packaging_cost:'.($package['slug'] ?? 'unknown');
                continue;
            }
            $knownCosts += (float) $package['actual_cost'] * (int) ($package['quantity'] ?? 1);
            $hasKnownCost = true;
        }
        foreach (['ad_cost_per_order', 'courier_cost_per_order'] as $field) {
            if (($snapshot[$field] ?? null) === null) {
                $missing[] = $field;
                continue;
            }
            $knownCosts += (float) $snapshot[$field];
            $hasKnownCost = true;
        }
        $missing = array_values(array_unique($missing));

        return [
            'status' => $missing === [] ? 'estimated_inputs_recorded' : 'incomplete',
            'revenue' => $revenue === null ? null : round($revenue, 2),
            'known_costs' => $hasKnownCost ? round($knownCosts, 2) : null,
            'probable_remaining_amount' => $revenue === null || ! $hasKnownCost ? null : round($revenue - $knownCosts, 2),
            'is_net_profit' => false,
            'label' => 'სავარაუდო დარჩენილი თანხა — არა დადასტურებული წმინდა მოგება',
            'missing_inputs' => $missing,
        ];
    }
}
