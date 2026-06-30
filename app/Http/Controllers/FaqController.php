<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function index(): View
    {
        $faqs = $this->deduplicateFaqs(
            Faq::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
        )->groupBy(fn (Faq $faq) => $faq->category ?: 'სხვა');

        return view('pages.faq', [
            'faqCategories' => $faqs,
        ]);
    }

    private function deduplicateFaqs(Collection $faqs): Collection
    {
        return $faqs->unique(function (Faq $faq): string {
            return implode('|', [
                $faq->category ?: 'სხვა',
                $this->normalizeFaqText($faq->answer),
            ]);
        })->values();
    }

    private function normalizeFaqText(?string $value): string
    {
        $normalized = str_replace(["\\r\\n", "\\n", "\\r", "\r\n", "\r"], "\n", (string) $value);
        $normalized = preg_replace("/\n{2,}/", "\n\n", $normalized) ?? $normalized;

        return Str::lower(trim($normalized));
    }
}
