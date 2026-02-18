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
            ->with(['primaryImage', 'images'])
            ->orderByDesc('updated_at')
            ->take(6)
            ->get();

        if ($featured->isEmpty()) {
            $featured = Product::active()
                ->with(['primaryImage', 'images'])
                ->orderByDesc('updated_at')
                ->take(6)
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
        $availableLocales = ['en', 'ka'];

        if (in_array($locale, $availableLocales, true)) {
            $request->session()->put('locale', $locale);
        }

        return redirect()->back();
    }
}
