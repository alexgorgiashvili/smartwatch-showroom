<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Services\Cart\CartSnapshotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function show(Request $request, CartSnapshotService $snapshot): View|RedirectResponse
    {
        $cartSnapshot = $snapshot->build($request);
        $summary = $cartSnapshot['summary'];

        if ((int) $summary['count'] <= 0) {
            return redirect()->route('cart.index')->with('cart_error', 'კალათა ცარიელია.');
        }

        $firstGiftGroup = $cartSnapshot['gift_groups']->first();
        $firstGiftItem = $firstGiftGroup ? $firstGiftGroup['items']->first() : null;
        $firstItem = $cartSnapshot['standard_items']->first() ?: $firstGiftItem;
        $currencyCode = $firstItem['currency'] ?? 'GEL';
        $currencySymbol = $currencyCode === 'GEL' ? '₾' : $currencyCode;

        return view('checkout.index', [
            'cartItems' => $cartSnapshot['standard_items'],
            'giftGroups' => $cartSnapshot['gift_groups'],
            'cartTotal' => (float) $summary['total'],
            'cartCount' => (int) $summary['count'],
            'currencySymbol' => $currencySymbol,
            'cities' => City::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
