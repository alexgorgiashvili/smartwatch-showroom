<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\BogPayService;
use App\Services\BogPaymentStatusSynchronizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileBogPendingPayments extends Command
{
    protected $signature = 'payments:reconcile-bog-pending {--minutes=30 : Minimum age of pending orders in minutes} {--limit=50 : Maximum orders to inspect}';

    protected $description = 'Verify old pending BOG card payments and restore stock for failed or expired payments.';

    public function handle(BogPayService $bogPayService, BogPaymentStatusSynchronizer $synchronizer): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $limit = max(1, (int) $this->option('limit'));

        $orders = Order::query()
            ->where('payment_type', 1)
            ->where('payment_status', 'pending')
            ->whereNotNull('bog_order_id')
            ->where('created_at', '<=', now()->subMinutes($minutes))
            ->oldest('created_at')
            ->limit($limit)
            ->get();

        $inspected = 0;
        $completed = 0;
        $rejected = 0;
        $ignored = 0;
        $failed = 0;

        foreach ($orders as $order) {
            $inspected++;

            try {
                $verifiedPayment = $bogPayService->getPaymentDetails((string) $order->bog_order_id);
                $result = $synchronizer->syncOrder($order, $verifiedPayment);
            } catch (\Throwable $exception) {
                $failed++;

                Log::warning('BOG pending payment reconciliation failed.', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'bog_order_id' => $order->bog_order_id,
                    'error' => $exception->getMessage(),
                ]);

                continue;
            }

            match ($result) {
                'completed' => $completed++,
                'rejected' => $rejected++,
                default => $ignored++,
            };
        }

        $this->info(sprintf(
            'Inspected: %d, completed: %d, rejected/restored: %d, ignored: %d, failed: %d',
            $inspected,
            $completed,
            $rejected,
            $ignored,
            $failed
        ));

        return self::SUCCESS;
    }
}
