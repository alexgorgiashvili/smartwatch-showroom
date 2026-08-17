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
    private const PREVIEW_SESSION_KEY = 'gift_builder_preview_access';

    public function show(Request $request, GiftBuilderCatalogService $catalog): View|RedirectResponse
    {
        if ($this->grantPreviewAccess($request)) {
            return redirect()->route('gift-builder.show');
        }

        $this->ensureAccess($request);

        return view('gift-builder.show', [
            'builderConfig' => $catalog->builderConfig($request),
        ]);
    }

    public function boxes(Request $request, GiftBuilderCatalogService $catalog): View|RedirectResponse
    {
        if ($this->grantPreviewAccess($request)) {
            return redirect()->route('gift-builder.boxes');
        }

        $this->ensureAccess($request);

        return view('gift-builder.boxes', [
            'readyBoxes' => $catalog->readyBoxes(),
        ]);
    }

    public function products(Request $request, GiftBuilderCatalogService $catalog): JsonResponse
    {
        $this->ensureAccess($request);

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
        $this->ensureAccess($request);

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
        $this->ensureAccess($request);

        $priced = $pricing->price($request->all());
        $result = $cart->addGroup($request, $priced);

        return response()->json([
            'success' => true,
            'message' => __('storefront.cart.gift_added'),
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
        $this->ensureAccess($request);

        $cart->removeGroup($request, $group);

        return redirect()->back()->with('cart_status', __('storefront.cart.gift_removed'));
    }

    private function grantPreviewAccess(Request $request): bool
    {
        $configuredKey = trim((string) config('gift_builder.preview_key', ''));
        $providedKey = trim((string) $request->query('preview', ''));

        if ($configuredKey !== '' && $providedKey !== '' && hash_equals($configuredKey, $providedKey)) {
            $request->session()->put(self::PREVIEW_SESSION_KEY, true);

            return true;
        }

        return false;
    }

    private function ensureAccess(Request $request): void
    {
        abort_unless(config('gift_builder.enabled', false), 404);

        $publicEnabled = config('gift_builder.public_enabled');
        if ($publicEnabled === null) {
            $publicEnabled = config('gift_builder.enabled', false);
        }

        abort_unless($publicEnabled || (bool) $request->session()->get(self::PREVIEW_SESSION_KEY), 404);
    }
}
