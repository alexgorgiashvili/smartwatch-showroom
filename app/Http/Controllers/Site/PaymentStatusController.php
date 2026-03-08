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
        return view('checkout.success', [
            'orderNumber' => $request->string('order')->toString(),
            'paymentMethod' => $request->string('method')->toString(),
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
