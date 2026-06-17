<?php

namespace App\Services;

use App\Events\PaymentCompleted;
use App\Jobs\PushBridgeOrderJob;
use App\Models\Order;
use App\Models\PaymentLog;
use Illuminate\Support\Facades\DB;

class BogPaymentStatusSynchronizer
{
    public function syncOrder(Order $order, array $verifiedPayment, ?array $paymentDetail = null): string
    {
        $verifiedBogOrderId = (string) ($verifiedPayment['order_id'] ?? $verifiedPayment['id'] ?? '');
        $verifiedExternalOrderId = (string) ($verifiedPayment['external_order_id'] ?? '');
        $verifiedStatusKey = $this->normalizeBogStatus($verifiedPayment);

        return DB::transaction(function () use ($order, $verifiedBogOrderId, $verifiedExternalOrderId, $verifiedStatusKey, $verifiedPayment, $paymentDetail) {
            /** @var Order|null $lockedOrder */
            $lockedOrder = Order::query()
                ->with(['items.variant'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedOrder) {
                return 'missing';
            }

            if ($verifiedBogOrderId === '' || $verifiedStatusKey === '') {
                $this->createPaymentLogOnce(
                    $lockedOrder,
                    (string) $lockedOrder->bog_order_id,
                    (string) $lockedOrder->bog_external_order_id,
                    'INVALID',
                    'callback verification mismatch',
                    $verifiedPayment
                );

                return 'invalid';
            }

            if ((string) $lockedOrder->bog_order_id !== $verifiedBogOrderId) {
                $this->createPaymentLogOnce(
                    $lockedOrder,
                    $verifiedBogOrderId,
                    $verifiedExternalOrderId,
                    'INVALID',
                    'callback verification mismatch',
                    $verifiedPayment
                );

                return 'invalid';
            }

            if ((string) $lockedOrder->bog_external_order_id !== '' && (string) $lockedOrder->bog_external_order_id !== $verifiedExternalOrderId) {
                $this->createPaymentLogOnce(
                    $lockedOrder,
                    $verifiedBogOrderId,
                    $verifiedExternalOrderId,
                    'INVALID',
                    'external order verification mismatch',
                    $verifiedPayment
                );

                return 'invalid';
            }

            if ($verifiedStatusKey === 'completed') {
                if ($lockedOrder->payment_status !== 'completed') {
                    $lockedOrder->update([
                        'payment_status' => 'completed',
                    ]);

                    if ($lockedOrder->requiresBridgePush()) {
                        PushBridgeOrderJob::dispatch($lockedOrder->id);
                    }

                    if ($lockedOrder->payment_type === 1) {
                        event(new PaymentCompleted($lockedOrder->fresh()));
                    }
                }

                $this->createPaymentLogOnce(
                    $lockedOrder,
                    $verifiedBogOrderId,
                    $verifiedExternalOrderId,
                    'PERFORMED',
                    'warmatebuli gadaxda',
                    $paymentDetail ?? $verifiedPayment
                );

                return 'completed';
            }

            if (in_array($verifiedStatusKey, ['rejected', 'cancelled', 'expired'], true)) {
                if ($lockedOrder->payment_status !== 'rejected') {
                    foreach ($lockedOrder->items as $item) {
                        if ($item->fulfillment_mode === 'local_stock') {
                            $item->variant?->increment('quantity', (int) $item->quantity);
                        }
                    }

                    $lockedOrder->update([
                        'payment_status' => 'rejected',
                    ]);
                }

                $this->createPaymentLogOnce(
                    $lockedOrder,
                    $verifiedBogOrderId,
                    $verifiedExternalOrderId,
                    'REJECTED',
                    'gadaxda ver moxerxda',
                    $paymentDetail ?? $verifiedPayment
                );

                return 'rejected';
            }

            $this->createPaymentLogOnce(
                $lockedOrder,
                $verifiedBogOrderId,
                $verifiedExternalOrderId,
                'IGNORED',
                'callback status ignored',
                $verifiedPayment
            );

            return 'ignored';
        });
    }

    public function normalizeBogStatus(array $details): string
    {
        $status = $details['order_status']['key'] ?? $details['status'] ?? null;

        return strtolower((string) $status);
    }

    public function createPaymentLogOnce(
        Order $order,
        string $bogOrderId,
        string $externalOrderId,
        string $status,
        string $internalStatus,
        array $paymentDetail
    ): void {
        PaymentLog::firstOrCreate(
            [
                'order_id' => $order->id,
                'bog_order_id' => $bogOrderId,
                'external_order_id' => $externalOrderId ?: null,
                'status' => $status,
            ],
            [
                'chveni_statusi' => $internalStatus,
                'payment_detail' => $paymentDetail,
            ]
        );
    }
}
