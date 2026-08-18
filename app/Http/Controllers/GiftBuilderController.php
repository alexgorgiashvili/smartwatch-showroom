<?php

namespace App\Http\Controllers;

use App\Models\ReadyGiftBox;
use App\Services\Cart\CartSnapshotService;
use App\Services\GiftBuilder\GiftBuilderCartService;
use App\Services\GiftBuilder\GiftBuilderCatalogService;
use App\Services\GiftBuilder\GiftBuilderPricingService;
use App\Services\GiftBuilder\ReadyGiftBoxAvailabilityService;
use App\Services\GiftBuilder\ReadyGiftBoxPurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GiftBuilderController extends Controller
{
    private const PREVIEW_SESSION_KEY = 'gift_builder_preview_access';

    public function show(Request $request, GiftBuilderCatalogService $catalog): Response|RedirectResponse
    {
        $this->captureCampaign($request);

        if ($this->grantPreviewAccess($request)) {
            return redirect()->route('gift-builder.show', $this->previewContext($request));
        }

        $this->ensureAccess($request);

        return $this->giftView('gift-builder.show', [
            'builderConfig' => $catalog->builderConfig($request),
        ]);
    }

    public function boxes(Request $request, GiftBuilderCatalogService $catalog): Response|RedirectResponse
    {
        $this->captureCampaign($request);

        if ($this->grantPreviewAccess($request)) {
            return redirect()->route('gift-builder.boxes');
        }

        $this->ensureAccess($request);

        return $this->giftView('gift-builder.boxes', [
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
            'gift_box' => $priced,
        ]);
    }

    public function readyBoxOptions(
        Request $request,
        ReadyGiftBox $box,
        GiftBuilderCatalogService $catalog,
        ReadyGiftBoxAvailabilityService $availability,
    ): JsonResponse {
        $this->ensureAccess($request);
        $this->ensurePublicBox($box);

        $report = $availability->report($box);
        if (! $report['available']) {
            return response()->json([
                'success' => false,
                'message' => __('ready_gift_boxes.unavailable'),
            ], 409);
        }

        return response()->json([
            'success' => true,
            'box' => $catalog->serializeReadyBox($box),
        ]);
    }

    public function addReadyBoxToCart(
        Request $request,
        ReadyGiftBox $box,
        ReadyGiftBoxAvailabilityService $availability,
        ReadyGiftBoxPurchaseService $purchase,
        GiftBuilderPricingService $pricing,
        GiftBuilderCartService $cart,
        CartSnapshotService $snapshot,
    ): JsonResponse {
        $this->ensureAccess($request);
        $this->ensurePublicBox($box);

        $report = $availability->report($box);
        if (! $report['available']) {
            return response()->json([
                'success' => false,
                'message' => __('ready_gift_boxes.unavailable'),
            ], 409);
        }

        $priced = $pricing->price($purchase->pricingPayload($box, $request->all()));
        $result = $cart->addGroup($request, $priced);

        return response()->json([
            'success' => true,
            'message' => __('storefront.cart.gift_added'),
            'group_id' => $result['group_id'],
            'cart_count' => $snapshot->roughCount($request),
            'redirect_url' => route('cart.index'),
            'gift_box' => $priced,
        ]);
    }

    public function removeFromCart(
        Request $request,
        string $group,
        GiftBuilderCartService $cart
    ): RedirectResponse {
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

        abort_unless(
            (bool) config('gift_builder.public_enabled', false)
                || (bool) $request->session()->get(self::PREVIEW_SESSION_KEY),
            404
        );
    }

    private function ensurePublicBox(ReadyGiftBox $box): void
    {
        abort_unless($box->is_active && ! $box->trashed(), 404);
    }

    private function captureCampaign(Request $request): void
    {
        $campaign = collect($request->only([
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_term',
            'utm_content',
        ]))
            ->filter(fn ($value): bool => is_scalar($value))
            ->map(fn ($value): string => mb_substr(trim((string) $value), 0, 200))
            ->filter()
            ->all();

        if ($campaign !== []) {
            $existing = collect((array) $request->session()->get('gift_campaign', []))
                ->only(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'])
                ->filter(fn ($value): bool => is_scalar($value))
                ->map(fn ($value): string => mb_substr(trim((string) $value), 0, 200))
                ->filter()
                ->all();
            $request->session()->put('gift_campaign', array_merge($existing, $campaign));
        }
    }

    /** @return array<string, string|int> */
    private function previewContext(Request $request): array
    {
        return collect($request->only(['box', 'product', 'variant_id', 'template']))
            ->filter(fn ($value): bool => is_scalar($value) && trim((string) $value) !== '')
            ->map(fn ($value): string => mb_substr(trim((string) $value), 0, 200))
            ->all();
    }

    /** @param array<string, mixed> $data */
    private function giftView(string $view, array $data): Response
    {
        $response = response()->view($view, $data);

        if (config('gift_builder.public_enabled') !== true) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }

        return $response;
    }
}
