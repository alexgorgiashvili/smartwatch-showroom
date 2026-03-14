<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\View\View;

class CityLandingController extends Controller
{
    /**
     * City configurations with Georgian and English names
     */
    private array $cities = [
        'tbilisi' => [
            'ka' => 'თბილისი',
            'en' => 'Tbilisi',
            'geo' => ['lat' => 41.7151, 'lng' => 44.8271],
        ],
        'batumi' => [
            'ka' => 'ბათუმი',
            'en' => 'Batumi',
            'geo' => ['lat' => 41.6168, 'lng' => 41.6367],
        ],
        'kutaisi' => [
            'ka' => 'ქუთაისი',
            'en' => 'Kutaisi',
            'geo' => ['lat' => 42.2679, 'lng' => 42.6993],
        ],
        'rustavi' => [
            'ka' => 'რუსთავი',
            'en' => 'Rustavi',
            'geo' => ['lat' => 41.5495, 'lng' => 45.0036],
        ],
        'gori' => [
            'ka' => 'გორ',
            'en' => 'Gori',
            'geo' => ['lat' => 41.9842, 'lng' => 44.1089],
        ],
    ];

    /**
     * Show city landing page
     */
    public function show(string $city): View
    {
        if (!isset($this->cities[$city])) {
            abort(404);
        }

        $locale = app()->getLocale();
        $cityData = $this->cities[$city];
        $cityName = $cityData[$locale] ?? $cityData['ka'];

        // Get featured products
        $products = Product::active()
            ->featured()
            ->with(['images', 'variants'])
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get();

        // If no featured products, get regular active products
        if ($products->isEmpty()) {
            $products = Product::active()
                ->with(['images', 'variants'])
                ->orderByDesc('updated_at')
                ->limit(8)
                ->get();
        }

        return view('landing.city', [
            'city' => $city,
            'cityName' => $cityName,
            'cityData' => $cityData,
            'products' => $products,
        ]);
    }

    /**
     * Get all cities for sitemap or navigation
     */
    public function getCities(): array
    {
        return $this->cities;
    }
}
