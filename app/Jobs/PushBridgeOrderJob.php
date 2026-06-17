<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Bridge\BridgeOrderSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PushBridgeOrderJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public ?int $orderId = null
    ) {
    }

    public function handle(BridgeOrderSyncService $sync): void
    {
        $orders = $this->orderId
            ? Order::query()->whereKey($this->orderId)->get()
            : Order::query()
                ->whereIn('bridge_sync_status', ['pending_push', 'pending_payment', 'push_failed'])
                ->whereIn('fulfillment_mode', ['dropship_bridge', 'mixed'])
                ->latest('updated_at')
                ->limit(25)
                ->get();

        foreach ($orders as $order) {
            if (! $order->isBridgePushAllowed()) {
                continue;
            }

            try {
                $sync->pushOrder($order);
            } catch (\Throwable $e) {
                $order->update([
                    'bridge_sync_status' => 'push_failed',
                ]);

                Log::error('PushBridgeOrderJob failed for order.', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
