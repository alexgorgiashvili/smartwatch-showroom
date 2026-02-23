<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $cart = collect($request->session()->get('cart', []));
        if ($cart->isEmpty()) {
            return redirect()->route('cart.index')->with('cart_error', 'კალათა ცარიელია.');
        }

        $variantIds = $cart->keys()->map(fn ($id) => (int) $id)->values();

        $variants = ProductVariant::query()
            ->with(['product.primaryImage'])
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');

        $cartItems = collect();
        $normalizedCart = [];

        foreach ($cart as $variantId => $item) {
            $variant = $variants->get((int) $variantId);

            if (! $variant || ! $variant->product || ! $variant->product->is_active) {
                continue;
            }

            $quantity = max(1, min((int) ($item['quantity'] ?? 1), 10));
            $unitPrice = (float) ($variant->product->sale_price ?? $variant->product->price ?? 0);

            if ($unitPrice <= 0) {
                continue;
            }

            $normalizedCart[(int) $variantId] = [
                'variant_id' => (int) $variant->id,
                'quantity' => $quantity,
            ];

            $cartItems->push([
                'variant' => $variant,
                'product' => $variant->product,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $unitPrice * $quantity,
                'currency' => $variant->product->currency,
                'image' => $variant->product->primaryImage?->url ?? asset('storage/images/home/smart-watch3.jpg'),
            ]);
        }

        $request->session()->put('cart', $normalizedCart);

        if ($cartItems->isEmpty()) {
            return redirect()->route('cart.index')->with('cart_error', 'კალათის პროდუქტები მიუწვდომელია.');
        }

        $firstItem = $cartItems->first();
        $currencyCode = $firstItem['currency'] ?? 'GEL';
        $currencySymbol = $currencyCode === 'GEL' ? '₾' : $currencyCode;

        return view('checkout.index', [
            'cartItems' => $cartItems,
            'cartTotal' => (float) $cartItems->sum('subtotal'),
            'cartCount' => (int) $cartItems->sum('quantity'),
            'currencySymbol' => $currencySymbol,
            'cities' => City::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
