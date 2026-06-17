<?php

namespace App\Http\Controllers;

use App\Services\Cart\CartSnapshotService;
use App\Services\GiftBuilder\GiftBuilderCartService;
use App\Services\GiftBuilder\GiftBuilderCatalogService;
use App\Services\GiftBuilder\GiftBuilderPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GiftBuilderController extends Controller
{
    public function show(Request $request, GiftBuilderCatalogService $catalog): View
    {
        return view('gift-builder.show', [
            'builderConfig' => $catalog->builderConfig($request),
        ]);
    }

    public function products(Request $request, GiftBuilderCatalogService $catalog): JsonResponse
    {
        return response()->json([
            'products' => $catalog->products([
                'role' => $request->query('role', 'all'),
                'recipient_type' => $request->query('recipient_type'),
                'occasion' => $request->query('occasion'),
                'budget_band' => $request->query('budget_band', 'all'),
            ])->values(),
        ]);
    }

    public function price(Request $request, GiftBuilderPricingService $pricing): JsonResponse
    {
        return response()->json([
            'success' => true,
            'gift_box' => $pricing->price($request->all()),
        ]);
    }

    public function addToCart(
        Request $request,
        GiftBuilderPricingService $pricing,
        GiftBuilderCartService $cart,
        CartSnapshotService $snapshot
    ): JsonResponse {
        $priced = $pricing->price($request->all());
        $result = $cart->addGroup($request, $priced);

        return response()->json([
            'success' => true,
            'message' => 'სასაჩუქრე ყუთი დაემატა კალათაში.',
            'group_id' => $result['group_id'],
            'cart_count' => $snapshot->roughCount($request),
            'redirect_url' => route('cart.index'),
        ]);
    }

    public function removeFromCart(
        Request $request,
        string $group,
        GiftBuilderCartService $cart
    ): RedirectResponse {
        $cart->removeGroup($request, $group);

        return redirect()->back()->with('cart_status', 'სასაჩუქრე ყუთი წაიშალა კალათიდან.');
    }
}
