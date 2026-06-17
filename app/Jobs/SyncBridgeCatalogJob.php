<?php

namespace App\Jobs;

use App\Services\Bridge\BridgeCatalogSyncService;
use App\Services\Bridge\WooBridgeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncBridgeCatalogJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(WooBridgeService $bridge, BridgeCatalogSyncService $sync): void
    {
        if (! $bridge->configured()) {
            Log::warning('SyncBridgeCatalogJob skipped because bridge is not configured.');
            return;
        }

        try {
            $remoteIds = collect($bridge->listProducts())
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->all();

            $count = $sync->syncProducts($remoteIds);

            Log::info('SyncBridgeCatalogJob completed.', ['count' => $count]);
        } catch (\Throwable $e) {
            Log::error('SyncBridgeCatalogJob failed.', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
