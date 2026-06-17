<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\Bridge\BridgeCatalogSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncBridgeInventoryJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(BridgeCatalogSyncService $sync): void
    {
        $remoteIds = Product::query()
            ->where('fulfillment_mode', 'dropship_bridge')
            ->whereNotNull('bridge_product_id')
            ->pluck('bridge_product_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($remoteIds === []) {
            Log::info('SyncBridgeInventoryJob skipped because no bridge products were mapped.');
            return;
        }

        try {
            $count = $sync->syncProducts($remoteIds);
            Log::info('SyncBridgeInventoryJob completed.', ['count' => $count]);
        } catch (\Throwable $e) {
            Log::error('SyncBridgeInventoryJob failed.', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
