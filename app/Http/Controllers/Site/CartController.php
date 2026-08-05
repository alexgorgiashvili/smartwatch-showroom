<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Services\Cart\CartSnapshotService;
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

    public function show(Request $request, CartSnapshotService $snapshot): View
    {
        $cartSnapshot = $snapshot->build($request);
        $summary = $cartSnapshot['summary'];

        return view('cart.index', [
            'cartItems' => $cartSnapshot['standard_items'],
            'giftGroups' => $cartSnapshot['gift_groups'],
            'cartTotal' => $summary['total'],
            'cartCount' => $summary['count'],
        ]);
    }

    public function add(Request $request, CartSnapshotService $snapshot): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:10'],
            'post_add_action' => ['nullable', 'in:cart,checkout'],
        ]);

        $variant = ProductVariant::query()
            ->with('product')
            ->whereKey($data['variant_id'])
            ->firstOrFail();

        $returnJson = $this->shouldReturnJson($request);

        if (! $variant->product || ! $variant->product->is_active || ! $variant->canFulfillQuantity(1)) {
            if ($returnJson) {
                return response()->json(['success' => false, 'message' => __('storefront.cart.product_unavailable')], 422);
            }
            return redirect()->back()->with('cart_error', __('storefront.cart.product_unavailable'));
        }

        $quantityToAdd = (int) ($data['quantity'] ?? 1);
        $postAddAction = $data['post_add_action'] ?? 'cart';
        $cart = $request->session()->get('cart', []);

        $existingQuantity = (int) ($cart[$variant->id]['quantity'] ?? 0);
        $newQuantity = $existingQuantity + $quantityToAdd;

        if ($newQuantity > 10) {
            if ($returnJson) {
                return response()->json(['success' => false, 'message' => __('storefront.cart.max_quantity')], 422);
            }
            return redirect()->back()->with('cart_error', __('storefront.cart.max_quantity'));
        }

        if (! $variant->canFulfillQuantity($newQuantity)) {
            if ($returnJson) {
                return response()->json(['success' => false, 'message' => __('storefront.cart.insufficient_stock')], 422);
            }
            return redirect()->back()->with('cart_error', __('storefront.cart.insufficient_stock'));
        }

        $cart[$variant->id] = [
            'variant_id' => (int) $variant->id,
            'quantity' => $newQuantity,
        ];

        $request->session()->put('cart', $cart);

        $newCount = $snapshot->roughCount($request);

        if ($returnJson) {
            return response()->json([
                'success' => true,
                'message' => __('storefront.cart.added'),
                'cart_count' => $newCount,
                'redirect_url' => $postAddAction === 'checkout' ? route('checkout.index') : null,
            ]);
        }

        if ($postAddAction === 'checkout') {
            return redirect()->route('checkout.index');
        }

        return redirect()->back()->with('cart_status', __('storefront.cart.added'));
    }

    public function update(Request $request, CartSnapshotService $snapshot): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        $variant = ProductVariant::query()->whereKey($data['variant_id'])->firstOrFail();

        $returnJson = $this->shouldReturnJson($request);

        if (! $variant->canFulfillQuantity((int) $data['quantity'])) {
            if ($returnJson) {
                return response()->json(['success' => false, 'message' => __('storefront.cart.insufficient_stock')], 422);
            }

            return redirect()->back()->with('cart_error', __('storefront.cart.insufficient_stock'));
        }

        $cart = $request->session()->get('cart', []);

        if (! array_key_exists((int) $variant->id, $cart)) {
            if ($returnJson) {
                return response()->json(['success' => false, 'message' => __('storefront.cart.not_found')], 404);
            }

            return redirect()->back();
        }

        $cart[(int) $variant->id]['quantity'] = (int) $data['quantity'];
        $request->session()->put('cart', $cart);

        if ($returnJson) {
            $cartSnapshot = $snapshot->build($request);
            $cartItems = $cartSnapshot['standard_items'];
            $summary = $cartSnapshot['summary'];

            $item = $cartItems->first(fn ($line) => (int) $line['variant']->id === (int) $variant->id);
            $currency = $item['currency'] ?? 'GEL';
            $currencySymbol = $currency === 'GEL' ? '₾' : $currency;
            $itemSubtotal = (float) ($item['subtotal'] ?? 0);

            return response()->json([
                'success' => true,
                'message' => __('storefront.messages.cart_updated'),
                'cart_count' => (int) $summary['count'],
                'cart_total' => (float) $summary['total'],
                'cart_total_formatted' => number_format((float) $summary['total'], 2) . ' ' . $currencySymbol,
                'item_subtotal' => $itemSubtotal,
                'item_subtotal_formatted' => number_format($itemSubtotal, 2) . ' ' . $currencySymbol,
            ]);
        }

        return redirect()->back()->with('cart_status', __('storefront.messages.cart_updated'));
    }

    public function replaceVariant(Request $request, CartSnapshotService $snapshot): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'current_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'new_variant_id' => ['required', 'integer', 'exists:product_variants,id', 'different:current_variant_id'],
        ]);

        $returnJson = $this->shouldReturnJson($request);
        $cart = $request->session()->get('cart', []);

        if (! array_key_exists((int) $data['current_variant_id'], $cart)) {
            if ($returnJson) {
                return response()->json(['success' => false, 'message' => __('storefront.cart.not_found')], 404);
            }

            return redirect()->back()->with('cart_error', __('storefront.cart.not_found'));
        }

        $variants = ProductVariant::query()
            ->with('product')
            ->whereIn('id', [(int) $data['current_variant_id'], (int) $data['new_variant_id']])
            ->get()
            ->keyBy('id');

        $currentVariant = $variants->get((int) $data['current_variant_id']);
        $newVariant = $variants->get((int) $data['new_variant_id']);

        if (! $currentVariant || ! $newVariant || ! $currentVariant->product || ! $newVariant->product) {
            if ($returnJson) {
                return response()->json(['success' => false, 'message' => __('storefront.cart.variant_change_failed')], 422);
            }

            return redirect()->back()->with('cart_error', __('storefront.cart.variant_change_failed'));
        }

        if ((int) $currentVariant->product_id !== (int) $newVariant->product_id) {
            if ($returnJson) {
                return response()->json(['success' => false, 'message' => __('storefront.cart.same_product_only')], 422);
            }

            return redirect()->back()->with('cart_error', __('storefront.cart.same_product_only'));
        }

        if (! $newVariant->product->is_active || ! $newVariant->canFulfillQuantity(1)) {
            if ($returnJson) {
                return response()->json(['success' => false, 'message' => __('storefront.cart.variant_unavailable')], 422);
            }

            return redirect()->back()->with('cart_error', __('storefront.cart.variant_unavailable'));
        }

        $currentQuantity = max(1, min(10, (int) ($cart[(int) $currentVariant->id]['quantity'] ?? 1)));
        $existingNewQuantity = max(0, min(10, (int) ($cart[(int) $newVariant->id]['quantity'] ?? 0)));
        $mergedQuantity = min(10, $currentQuantity + $existingNewQuantity);
        $finalQuantity = min($mergedQuantity, max(1, $newVariant->available_quantity));
        $wasClamped = $finalQuantity < $mergedQuantity;

        unset($cart[(int) $currentVariant->id]);
        $cart[(int) $newVariant->id] = [
            'variant_id' => (int) $newVariant->id,
            'quantity' => $finalQuantity,
        ];

        $request->session()->put('cart', $cart);

        $cartSnapshot = $snapshot->build($request);
        $summary = $cartSnapshot['summary'];
        $item = $cartSnapshot['standard_items']->first(fn ($line) => (int) $line['variant']->id === (int) $newVariant->id);
        if (! $item) {
            if ($returnJson) {
                return response()->json(['success' => false, 'message' => __('storefront.cart.update_after_color_failed')], 422);
            }

            return redirect()->back()->with('cart_error', __('storefront.cart.update_after_color_failed'));
        }
        $currency = $item['currency'] ?? 'GEL';
        $currencySymbol = $currency === 'GEL' ? '₾' : $currency;

        $message = $wasClamped
            ? __('storefront.messages.color_changed_clamped')
            : __('storefront.messages.color_changed');

        if ($returnJson) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'cart_count' => (int) $summary['count'],
                'cart_total' => (float) $summary['total'],
                'cart_total_formatted' => number_format((float) $summary['total'], 2) . ' ' . $currencySymbol,
                'item_subtotal_formatted' => number_format((float) ($item['subtotal'] ?? 0), 2) . ' ' . $currencySymbol,
                'quantity' => (int) ($item['quantity'] ?? $finalQuantity),
                'variant_label' => $item['variant_label'] ?? $newVariant->localizedName(),
                'color_name' => $item['color_name'] ?? $newVariant->localizedColorName(),
                'reload' => true,
            ]);
        }

        return redirect()->back()->with('cart_status', $message);
    }

    public function remove(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
        ]);

        $cart = $request->session()->get('cart', []);
        unset($cart[(int) $data['variant_id']]);

        $request->session()->put('cart', $cart);

        return redirect()->back()->with('cart_status', __('storefront.cart.removed'));
    }

    public function clear(Request $request): RedirectResponse
    {
        $request->session()->forget('cart');
        $request->session()->forget('gift_cart_groups');

        return redirect()->route('cart.index')->with('cart_status', __('storefront.cart.cleared'));
    }

}
