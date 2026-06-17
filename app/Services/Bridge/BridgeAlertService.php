<?php

namespace App\Services\Bridge;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

class BridgeAlertService
{
    public function alerts(): Collection
    {
        $alerts = collect();

        $bridgeStatus = app(WooBridgeService::class)->cachedStatus();
        if (($bridgeStatus['configured'] ?? false) && ! ($bridgeStatus['authenticated'] ?? false)) {
            $isSslIssue = str_contains(strtolower((string) ($bridgeStatus['error'] ?? '')), 'ssl');

            $alerts->push([
                'level' => $isSslIssue ? 'warning' : 'danger',
                'title' => $isSslIssue ? 'Bridge SSL verification issue' : 'Bridge authentication failed',
                'message' => $bridgeStatus['error'] ?: 'Woo bridge API authentication is failing.',
            ]);
        }

        $pushFailures = Order::query()
            ->where('bridge_sync_status', 'push_failed')
            ->latest('updated_at')
            ->take(5)
            ->get();

        foreach ($pushFailures as $order) {
            $alerts->push([
                'level' => 'danger',
                'title' => 'Bridge push failed',
                'message' => "Order {$order->order_number} needs retry.",
                'href' => route('admin.orders.show', $order),
            ]);
        }

        $unmappedOrderedItems = Order::query()
            ->whereIn('bridge_sync_status', ['pending_push', 'pending_payment'])
            ->whereHas('items', fn ($query) => $query
                ->where('fulfillment_mode', 'dropship_bridge')
                ->where(function ($subQuery) {
                    $subQuery->whereNull('bridge_product_id')
                        ->orWhere('bridge_product_id', '')
                        ->orWhereNull('bridge_variation_id')
                        ->orWhere('bridge_variation_id', '');
                }))
            ->latest('updated_at')
            ->take(5)
            ->get();

        foreach ($unmappedOrderedItems as $order) {
            $alerts->push([
                'level' => 'warning',
                'title' => 'Unmapped dropship order item',
                'message' => "Order {$order->order_number} has a dropship item without bridge mapping.",
                'href' => route('admin.orders.show', $order),
            ]);
        }

        $syncFailures = ProductVariant::query()
            ->where('stock_sync_status', 'sync_failed')
            ->with('product:id,name_en')
            ->latest('updated_at')
            ->take(5)
            ->get();

        foreach ($syncFailures as $variant) {
            $alerts->push([
                'level' => 'warning',
                'title' => 'Bridge inventory sync failed',
                'message' => ($variant->product?->name_en ?: 'Product') . " / {$variant->name} failed to sync.",
                'href' => $variant->product ? route('admin.products.edit', $variant->product) : null,
            ]);
        }

        $pendingReviewProducts = Product::query()
            ->where('fulfillment_mode', 'dropship_bridge')
            ->where('is_active', false)
            ->where(function ($query) {
                $query->whereNull('product_sync_status')
                    ->orWhere('product_sync_status', 'pending_review');
            })
            ->count();

        if ($pendingReviewProducts > 0) {
            $alerts->push([
                'level' => 'info',
                'title' => 'Bridge products pending review',
                'message' => "{$pendingReviewProducts} synced bridge product(s) are still inactive.",
                'href' => route('admin.bridge.index'),
            ]);
        }

        return $alerts->take(10)->values();
    }
}
