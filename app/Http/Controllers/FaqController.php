<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function index(): View
    {
        $faqs = Faq::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Faq $faq) => $faq->category ?: 'სხვა');

        return view('pages.faq', [
            'faqCategories' => $faqs,
        ]);
    }
}
