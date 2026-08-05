<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $featured = Product::active()
            ->featured()
            ->with(['primaryImage', 'images', 'variants'])
            ->orderByRaw('COALESCE(home_sort_order, 0) ASC')
            ->orderByDesc('updated_at')
            // ->take(6)
            ->get();

        if ($featured->isEmpty()) {
            $featured = Product::active()
                ->with(['primaryImage', 'images', 'variants'])
                ->orderByRaw('COALESCE(home_sort_order, 0) ASC')
                ->orderByDesc('updated_at')
                // ->take(6)
                ->get();
        }

        return view('home', [
            'featured' => $featured,
        ]);
    }

    public function contact(): View
    {
        return view('contact.index');
    }

    public function locale(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, ['ka', 'en'], true), 404);

        $request->session()->put('locale', $locale);

        return redirect()->to($request->headers->get('referer') ?: route('home'));
    }
}
