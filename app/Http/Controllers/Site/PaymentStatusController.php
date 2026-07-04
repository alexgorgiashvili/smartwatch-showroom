<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentStatusController extends Controller
{
    public function success(Request $request): View
    {
        $orderNumber = $request->string('order')->toString();
        $order = Order::query()
            ->with('items')
            ->where('order_number', $orderNumber)
            ->first();
        $purchaseEvent = null;

        if ($order && (float) $order->total_amount > 0) {
            $purchaseEvent = [
                'transaction_id' => $order->order_number,
                'value' => (float) $order->total_amount,
                'currency' => strtoupper($order->currency ?: 'GEL'),
                'content_type' => 'product',
                'content_ids' => $order->items->map(fn ($item) => (string) ($item->product_variant_id ?? $item->id))->filter()->values()->all(),
                'items' => $order->items->map(fn ($item) => array_filter([
                    'item_id' => (string) ($item->product_variant_id ?? $item->id),
                    'item_name' => $item->product_name,
                    'price' => (float) $item->unit_price,
                    'quantity' => (int) $item->quantity,
                ]))->values()->all(),
                'contents' => $order->items->map(fn ($item) => array_filter([
                    'id' => (string) ($item->product_variant_id ?? $item->id),
                    'quantity' => (int) $item->quantity,
                    'item_price' => (float) $item->unit_price,
                    'item_name' => $item->product_name,
                ]))->values()->all(),
                'num_items' => (int) $order->items->sum('quantity'),
            ];
        }

        return view('checkout.success', [
            'orderNumber' => $orderNumber,
            'paymentMethod' => $request->string('method')->toString(),
            'purchaseEvent' => $purchaseEvent,
        ]);
    }

    public function fail(Request $request): View
    {
        $orderNumber = $request->string('order')->toString();
        $order = Order::query()
            ->select(['id', 'order_number', 'payment_type', 'payment_status', 'status'])
            ->where('order_number', $orderNumber)
            ->first();

        return view('checkout.fail', [
            'orderNumber' => $orderNumber,
            'retryUrl' => $order
                && (int) $order->payment_type === 1
                && $order->status === 'pending'
                && $order->payment_status !== 'completed'
                ? route('payment.bog.redirect', ['order_id' => $order->id])
                : null,
        ]);
    }
}
