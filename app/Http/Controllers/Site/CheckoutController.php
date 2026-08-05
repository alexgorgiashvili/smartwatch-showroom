<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Services\Cart\CartSnapshotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class CheckoutController extends Controller
{
    public function show(Request $request, CartSnapshotService $snapshot): View|RedirectResponse
    {
        try {
            $cartSnapshot = $snapshot->build($request, ['enforce_stock' => true]);
        } catch (RuntimeException) {
            return redirect()->route('cart.index')
                ->with('cart_error', __('storefront.cart.stock_changed'));
        }
        $summary = $cartSnapshot['summary'];

        if ((int) $summary['count'] <= 0) {
            return redirect()->route('cart.index')->with('cart_error', __('storefront.cart.empty_error'));
        }

        $popularCityNames = [
            'თბილისი',
            'ბათუმი',
            'ქუთაისი',
            'რუსთავი',
            'გორი',
            'ზუგდიდი',
            'ფოთი',
            'თელავი',
            'ქობულეთი',
            'ოზურგეთი',
        ];

        $firstGiftGroup = $cartSnapshot['gift_groups']->first();
        $firstGiftItem = $firstGiftGroup ? $firstGiftGroup['items']->first() : null;
        $firstItem = $cartSnapshot['standard_items']->first() ?: $firstGiftItem;
        $currencyCode = $firstItem['currency'] ?? 'GEL';
        $currencySymbol = $currencyCode === 'GEL' ? '₾' : $currencyCode;

        $cities = City::query()->orderBy(app()->getLocale() === 'en' ? 'name_en' : 'name')->get(['id', 'name', 'name_en']);
        $popularCityIds = collect($popularCityNames)
            ->map(fn (string $name) => $cities->firstWhere('name', $name)?->id)
            ->filter()
            ->values();
        $orderedCities = $popularCityIds
            ->map(fn (int $id) => $cities->firstWhere('id', $id))
            ->filter()
            ->merge($cities->reject(fn (City $city) => $popularCityIds->contains($city->id)))
            ->values();

        return view('checkout.index', [
            'cartItems' => $cartSnapshot['standard_items'],
            'giftGroups' => $cartSnapshot['gift_groups'],
            'cartTotal' => (float) $summary['total'],
            'cartCount' => (int) $summary['count'],
            'currencySymbol' => $currencySymbol,
            'cities' => $orderedCities,
            'popularCityIds' => $popularCityIds,
        ]);
    }
}
