<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
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
        return view('checkout.fail', [
            'orderNumber' => $request->string('order')->toString(),
        ]);
    }
}
