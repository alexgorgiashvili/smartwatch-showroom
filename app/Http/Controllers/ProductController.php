<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $query = Product::active()->with(['primaryImage', 'images']);

        // Apply search
        if ($search = $request->input('search')) {
            $locale = app()->getLocale();
            $query->where(function ($q) use ($search, $locale) {
                $q->where('name_en', 'like', "%{$search}%")
                    ->orWhere('name_ka', 'like', "%{$search}%")
                    ->orWhere('short_description_en', 'like', "%{$search}%")
                    ->orWhere('short_description_ka', 'like', "%{$search}%");
            });
        }

        // Apply category filter
        $category = $request->input('category');
        if ($category === 'sim') {
            $query->where('sim_support', true);
        } elseif ($category === 'gps') {
            $query->where('gps_features', true);
        } elseif ($category === 'new') {
            $query->where('created_at', '>=', now()->subDays(30));
        }

        // Apply sorting
        $sort = $request->input('sort', 'featured');
        match ($sort) {
            'price_low' => $query->orderByRaw('COALESCE(sale_price, price) ASC NULLS LAST'),
            'price_high' => $query->orderByRaw('COALESCE(sale_price, price) DESC NULLS LAST'),
            'newest' => $query->latest(),
            default => $query->orderBy('featured', 'desc')->orderBy('name_en'),
        };

        $products = $query->get();

        return view('products.index', [
            'products' => $products,
            'search' => $search ?? '',
            'category' => $category,
        ]);
    }

    public function show(Product $product): View
    {
        if (! $product->is_active) {
            abort(404);
        }

        $product->load(['primaryImage', 'images']);

        return view('products.show', [
            'product' => $product,
        ]);
    }
}
