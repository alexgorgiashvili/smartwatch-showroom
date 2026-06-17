<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Services\Bridge\BridgeAlertService;
use App\Services\Bridge\BridgeCatalogSyncService;
use App\Services\Bridge\BridgeOrderSyncService;
use App\Services\Bridge\WooBridgeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BridgeController extends Controller
{
    public function index(Request $request, WooBridgeService $bridge, BridgeAlertService $alerts)
    {
        $refreshRemote = $request->boolean('refresh_remote');
        $bridgeStatus = $refreshRemote
            ? $bridge->refreshStatus()
            : ($bridge->cachedStatus() ?? $this->defaultBridgeStatus());

        $bridgeProducts = $refreshRemote
            ? (($bridgeStatus['authenticated'] ?? false) ? $bridge->refreshProducts() : [])
            : $bridge->cachedProducts();

        if (! $refreshRemote && $bridgeProducts === []) {
            $bridgeProducts = $this->localBridgeProducts();
        }

        if (! $refreshRemote && ($bridge->cachedProducts() === [])) {
            $bridgeStatus['product_count'] = count($bridgeProducts);
        }

        $error = $bridgeStatus['error'] ?? null;

        $mappedProducts = Product::query()
            ->where('external_source', 'woo_bridge')
            ->get()
            ->keyBy('external_product_id')
            ->all();

        $view = view('admin.bridge.index', [
            'bridgeStatus' => $bridgeStatus,
            'bridgeProducts' => $bridgeProducts,
            'mappedProducts' => $mappedProducts,
            'bridgeError' => $error,
            'bridgeOrders' => Order::query()
                ->withCount(['items as dropship_items_count' => fn ($query) => $query->where('fulfillment_mode', 'dropship_bridge')])
                ->whereIn('fulfillment_mode', ['dropship_bridge', 'mixed'])
                ->latest()
                ->limit(20)
                ->get(),
            'bridgeAlerts' => $alerts->alerts(),
        ]);

        return $this->renderPjaxView($request, $view);
    }

    public function refresh(Request $request, WooBridgeService $bridge): RedirectResponse
    {
        $bridge->refreshStatus();
        $bridge->refreshProducts();

        return redirect()->route('admin.bridge.index')
            ->with('status', 'Bridge data refreshed from the remote store.');
    }

    public function syncProduct(int $remoteProductId, BridgeCatalogSyncService $sync): RedirectResponse
    {
        $product = $sync->syncProduct($remoteProductId);

        return redirect()->route('admin.bridge.index')
            ->with('status', "Bridge product synced into local catalog: {$product->name_en}");
    }

    public function syncAll(Request $request, WooBridgeService $bridge, BridgeCatalogSyncService $sync): RedirectResponse
    {
        $remoteIds = collect($bridge->listProducts())
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $count = $sync->syncProducts($remoteIds);

        return redirect()->route('admin.bridge.index')
            ->with('status', "Bridge sync complete. {$count} product(s) imported or updated.");
    }

    public function pushOrder(Order $order, BridgeOrderSyncService $sync): RedirectResponse
    {
        try {
            $result = $sync->pushOrder($order);
        } catch (\Throwable $e) {
            return redirect()->route('admin.bridge.index')
                ->with('error', $e->getMessage());
        }

        return redirect()->route('admin.bridge.index')
            ->with('status', 'Bridge order push status: ' . ($result['status'] ?? 'done'));
    }

    public function refreshOrder(Order $order, BridgeOrderSyncService $sync): RedirectResponse
    {
        try {
            $result = $sync->refreshOrderStatus($order);
        } catch (\Throwable $e) {
            return redirect()->route('admin.bridge.index')
                ->with('error', $e->getMessage());
        }

        return redirect()->route('admin.bridge.index')
            ->with('status', 'Bridge order refresh status: ' . ($result['status'] ?? 'done'));
    }

    private function defaultBridgeStatus(): array
    {
        return [
            'configured' => filled(config('services.bridge.base_url'))
                && filled(config('services.bridge.username'))
                && filled(config('services.bridge.app_password')),
            'reachable' => false,
            'authenticated' => false,
            'product_count' => 0,
            'base_url' => rtrim((string) config('services.bridge.base_url'), '/'),
            'error' => 'Bridge data has not been refreshed yet.',
        ];
    }

    private function localBridgeProducts(): array
    {
        return Product::query()
            ->where('external_source', 'woo_bridge')
            ->with(['variants:id,product_id,quantity'])
            ->withCount('variants')
            ->latest('updated_at')
            ->limit((int) config('services.bridge.product_limit', 20))
            ->get()
            ->map(function (Product $product): array {
                return [
                    'id' => $product->external_product_id ?: $product->id,
                    'name' => $product->name_en,
                    'status' => $product->is_active ? 'publish' : 'draft',
                    'type' => $product->variants_count > 1 ? 'variable' : 'simple',
                    'stock_quantity' => $product->variants->sum('quantity'),
                    'price' => number_format((float) $product->price, 2, '.', ''),
                    'permalink' => $product->external_source_url ?: route('products.show', ['product' => $product->slug]),
                ];
            })
            ->all();
    }
}
