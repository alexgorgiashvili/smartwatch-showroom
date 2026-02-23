<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    private function shouldReturnJson(Request $request): bool
    {
        return $request->expectsJson()
            || $request->wantsJson()
            || $request->ajax()
            || $request->isXmlHttpRequest()
            || str_contains((string) $request->header('Accept'), 'application/json')
            || strtolower((string) $request->header('X-Requested-With')) === 'xmlhttprequest';
    }

    public function show(Request $request): View
    {
        [$cartItems, $summary] = $this->buildCartSummary($request);

        return view('cart.index', [
            'cartItems' => $cartItems,
            'cartTotal' => $summary['total'],
            'cartCount' => $summary['count'],
        ]);
    }

    public function add(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $variant = ProductVariant::query()
            ->with('product')
            ->whereKey($data['variant_id'])
            ->firstOrFail();

        $returnJson = $this->shouldReturnJson($request);

        if (! $variant->product || ! $variant->product->is_active || $variant->quantity <= 0) {
            if ($returnJson) {
                return response()->json(['success' => false, 'message' => 'არჩეული პროდუქტი დროებით მიუწვდომელია.'], 422);
            }
            return redirect()->back()->with('cart_error', 'არჩეული პროდუქტი დროებით მიუწვდომელია.');
        }

        $quantityToAdd = (int) ($data['quantity'] ?? 1);
        $cart = $request->session()->get('cart', []);

        $existingQuantity = (int) ($cart[$variant->id]['quantity'] ?? 0);
        $newQuantity = $existingQuantity + $quantityToAdd;

        if ($newQuantity > 10) {
            if ($returnJson) {
                return response()->json(['success' => false, 'message' => 'ერთი ვარიანტისთვის მაქსიმუმ 10 ცალი შეგიძლიათ დაამატოთ.'], 422);
            }
            return redirect()->back()->with('cart_error', 'ერთი ვარიანტისთვის მაქსიმუმ 10 ცალი შეგიძლიათ დაამატოთ.');
        }

        if ($newQuantity > (int) $variant->quantity) {
            if ($returnJson) {
                return response()->json(['success' => false, 'message' => 'მარაგში საკმარისი რაოდენობა არ არის.'], 422);
            }
            return redirect()->back()->with('cart_error', 'მარაგში საკმარისი რაოდენობა არ არის.');
        }

        $cart[$variant->id] = [
            'variant_id' => (int) $variant->id,
            'quantity' => $newQuantity,
        ];

        $request->session()->put('cart', $cart);

        $newCount = collect($request->session()->get('cart', []))->sum(fn ($i) => (int) ($i['quantity'] ?? 0));

        if ($returnJson) {
            return response()->json([
                'success' => true,
                'message' => 'პროდუქტი დაემატა კალათაში.',
                'cart_count' => $newCount,
            ]);
        }

        return redirect()->back()->with('cart_status', 'პროდუქტი დაემატა კალათაში.');
    }

    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        $variant = ProductVariant::query()->whereKey($data['variant_id'])->firstOrFail();

        $returnJson = $this->shouldReturnJson($request);

        if ((int) $data['quantity'] > (int) $variant->quantity) {
            if ($returnJson) {
                return response()->json(['success' => false, 'message' => 'მარაგში საკმარისი რაოდენობა არ არის.'], 422);
            }

            return redirect()->back()->with('cart_error', 'მარაგში საკმარისი რაოდენობა არ არის.');
        }

        $cart = $request->session()->get('cart', []);

        if (! array_key_exists((int) $variant->id, $cart)) {
            if ($returnJson) {
                return response()->json(['success' => false, 'message' => 'პროდუქტი კალათაში ვერ მოიძებნა.'], 404);
            }

            return redirect()->back();
        }

        $cart[(int) $variant->id]['quantity'] = (int) $data['quantity'];
        $request->session()->put('cart', $cart);

        if ($returnJson) {
            [$cartItems, $summary] = $this->buildCartSummary($request);

            $item = $cartItems->first(fn ($line) => (int) $line['variant']->id === (int) $variant->id);
            $currency = $item['currency'] ?? 'GEL';
            $currencySymbol = $currency === 'GEL' ? '₾' : $currency;
            $itemSubtotal = (float) ($item['subtotal'] ?? 0);

            return response()->json([
                'success' => true,
                'message' => 'კალათა განახლდა.',
                'cart_count' => (int) $summary['count'],
                'cart_total' => (float) $summary['total'],
                'cart_total_formatted' => number_format((float) $summary['total'], 2) . ' ' . $currencySymbol,
                'item_subtotal' => $itemSubtotal,
                'item_subtotal_formatted' => number_format($itemSubtotal, 2) . ' ' . $currencySymbol,
            ]);
        }

        return redirect()->back()->with('cart_status', 'კალათა განახლდა.');
    }

    public function remove(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
        ]);

        $cart = $request->session()->get('cart', []);
        unset($cart[(int) $data['variant_id']]);

        $request->session()->put('cart', $cart);

        return redirect()->back()->with('cart_status', 'პროდუქტი წაიშალა კალათიდან.');
    }

    public function clear(Request $request): RedirectResponse
    {
        $request->session()->forget('cart');

        return redirect()->route('cart.index')->with('cart_status', 'კალათა გასუფთავდა.');
    }

    private function buildCartSummary(Request $request): array
    {
        $cart = collect($request->session()->get('cart', []));

        if ($cart->isEmpty()) {
            return [collect(), ['count' => 0, 'total' => 0.0]];
        }

        $variantIds = $cart->keys()->map(fn ($id) => (int) $id)->values();

        $variants = ProductVariant::query()
            ->with(['product.primaryImage'])
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');

        $cartItems = collect();
        $validatedCart = [];

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

            $validatedCart[(int) $variantId] = [
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

        $request->session()->put('cart', $validatedCart);

        return [
            $cartItems,
            [
                'count' => (int) $cartItems->sum('quantity'),
                'total' => (float) $cartItems->sum('subtotal'),
            ],
        ];
    }
}
