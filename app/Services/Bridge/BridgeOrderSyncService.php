<?php

namespace App\Services\Bridge;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Arr;
use RuntimeException;

class BridgeOrderSyncService
{
    public function __construct(
        private readonly WooBridgeService $bridge
    ) {
    }

    public function pushOrder(Order $order): array
    {
        $order->loadMissing(['items.variant.product']);

        if (! $order->requiresBridgePush()) {
            $order->update([
                'bridge_sync_status' => 'not_required',
            ]);

            return ['status' => 'not_required'];
        }

        if ($order->bridge_order_id) {
            return [
                'status' => 'already_pushed',
                'bridge_order_id' => $order->bridge_order_id,
                'bridge_order_number' => $order->bridge_order_number,
            ];
        }

        if (! $order->isBridgePushAllowed()) {
            $order->update([
                'bridge_sync_status' => (int) $order->payment_type === 1 ? 'pending_payment' : 'pending_push',
            ]);

            throw new RuntimeException($this->buildEligibilityError($order));
        }

        $dropshipItems = $order->dropshipItems();

        if ($dropshipItems->isEmpty()) {
            $order->update([
                'bridge_sync_status' => 'not_required',
            ]);

            return ['status' => 'not_required'];
        }

        $lineItems = $dropshipItems->map(function (OrderItem $item): array {
            $this->assertItemReadyForPush($item);

            return [
                'product_id' => (int) $item->bridge_product_id,
                'variation_id' => $item->bridge_variation_id ? (int) $item->bridge_variation_id : 0,
                'quantity' => (int) $item->quantity,
                'subtotal' => number_format((float) $item->subtotal, 2, '.', ''),
                'total' => number_format((float) $item->subtotal, 2, '.', ''),
            ];
        })->values()->all();

        $payload = [
            'status' => 'processing',
            'set_paid' => (int) $order->payment_type === 1,
            'currency' => $order->currency ?: 'GEL',
            'customer_note' => $order->notes,
            'billing' => $this->mapAddress($order),
            'shipping' => $this->mapAddress($order),
            'line_items' => $lineItems,
            'meta_data' => [
                ['key' => 'laravel_order_number', 'value' => $order->order_number],
                ['key' => 'laravel_order_id', 'value' => (string) $order->id],
                ['key' => 'fulfillment_mode', 'value' => (string) $order->fulfillment_mode],
            ],
        ];

        try {
            $response = $this->bridge->createOrder($payload);
        } catch (\Throwable $e) {
            $order->update([
                'bridge_sync_status' => 'push_failed',
            ]);

            throw $e;
        }

        $order->update([
            'bridge_order_id' => (string) Arr::get($response, 'id'),
            'bridge_order_number' => (string) Arr::get($response, 'number'),
            'bridge_sync_status' => 'pushed',
            'bridge_synced_at' => now(),
        ]);

        return [
            'status' => 'pushed',
            'bridge_order_id' => $order->bridge_order_id,
            'bridge_order_number' => $order->bridge_order_number,
        ];
    }

    public function refreshOrderStatus(Order $order): array
    {
        if (! $order->bridge_order_id) {
            throw new RuntimeException('Bridge order has not been created yet.');
        }

        try {
            $remoteOrder = $this->bridge->getOrder((int) $order->bridge_order_id);
        } catch (\Throwable $e) {
            $order->update([
                'bridge_sync_status' => 'push_failed',
            ]);

            throw $e;
        }
        $remoteStatus = (string) ($remoteOrder['status'] ?? '');
        $tracking = $this->extractTrackingData($remoteOrder);

        $order->update([
            'bridge_sync_status' => $this->mapBridgeSyncStatus($remoteStatus, $tracking['tracking_number']),
            'fulfillment_status' => $this->mapFulfillmentStatus($remoteStatus, $tracking['tracking_number']),
            'tracking_number' => $tracking['tracking_number'],
            'tracking_carrier' => $tracking['tracking_carrier'],
            'fulfilled_at' => in_array($remoteStatus, ['completed', 'fulfilled'], true) ? now() : $order->fulfilled_at,
            'bridge_synced_at' => now(),
        ]);

        return [
            'status' => $order->bridge_sync_status,
            'remote_status' => $remoteStatus,
            'tracking_number' => $order->tracking_number,
        ];
    }

    private function assertItemReadyForPush(OrderItem $item): void
    {
        if (! $item->bridge_product_id) {
            throw new RuntimeException("Bridge mapping missing for item: {$item->product_name}");
        }

        $variant = $item->variant;
        if (! $variant || ! $variant->product) {
            throw new RuntimeException("Variant relation missing for item: {$item->product_name}");
        }

        if (! $variant->canFulfillQuantity((int) $item->quantity)) {
            throw new RuntimeException("Supplier stock unavailable for item: {$item->product_name}");
        }
    }

    private function buildEligibilityError(Order $order): string
    {
        if ((int) $order->payment_type === 1 && $order->payment_status !== 'completed') {
            return 'Card order cannot be pushed before payment is completed.';
        }

        if ((int) $order->payment_type === 2 && $order->status !== 'confirmed') {
            return 'COD order cannot be pushed before admin confirmation.';
        }

        return 'Order is not eligible for bridge push.';
    }

    private function mapAddress(Order $order): array
    {
        return [
            'first_name' => (string) $order->customer_name,
            'last_name' => '',
            'company' => '',
            'address_1' => (string) ($order->exact_address ?: $order->delivery_address),
            'address_2' => '',
            'city' => (string) ($order->city ?: ''),
            'state' => '',
            'postcode' => (string) ($order->postal_code ?: ''),
            'country' => 'GE',
            'email' => (string) ($order->customer_email ?: 'bridge-order@local.test'),
            'phone' => (string) $order->customer_phone,
        ];
    }

    private function mapBridgeSyncStatus(string $remoteStatus, ?string $trackingNumber): string
    {
        if ($trackingNumber) {
            return 'tracking_received';
        }

        return match ($remoteStatus) {
            'completed', 'fulfilled' => 'fulfilled',
            'cancelled', 'refunded', 'failed' => 'cancelled',
            default => 'pushed',
        };
    }

    private function mapFulfillmentStatus(string $remoteStatus, ?string $trackingNumber): string
    {
        if ($trackingNumber) {
            return 'partially_fulfilled';
        }

        return match ($remoteStatus) {
            'completed', 'fulfilled' => 'fulfilled',
            'cancelled', 'refunded', 'failed' => 'cancelled',
            default => 'processing',
        };
    }

    private function extractTrackingData(array $remoteOrder): array
    {
        $trackingNumber = null;
        $trackingCarrier = null;

        foreach (($remoteOrder['meta_data'] ?? []) as $meta) {
            $key = strtolower((string) ($meta['key'] ?? ''));
            $value = trim((string) ($meta['value'] ?? ''));

            if ($value === '') {
                continue;
            }

            if ($trackingNumber === null && in_array($key, ['tracking_number', '_tracking_number', 'shipment_tracking_number'], true)) {
                $trackingNumber = $value;
                continue;
            }

            if ($trackingCarrier === null && in_array($key, ['tracking_provider', '_tracking_provider', 'tracking_carrier'], true)) {
                $trackingCarrier = $value;
            }
        }

        return [
            'tracking_number' => $trackingNumber,
            'tracking_carrier' => $trackingCarrier,
        ];
    }
}
