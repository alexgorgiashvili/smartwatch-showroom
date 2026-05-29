<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InquiryController extends Controller
{
    public function index(Request $request)
    {
        $query = Inquiry::with('product:id,name_ka,name_en');

        // Filters
        if ($locale = $request->query('locale')) {
            $query->where('locale', $locale);
        }

        if ($preferredContact = $request->query('preferred_contact')) {
            $query->where('preferred_contact', $preferredContact);
        }

        // Search
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $inquiries = $query->orderBy('created_at', 'desc')
            ->paginate(25);

        $view = view('admin.inquiries.index', [
            'inquiries' => $inquiries,
            'filters' => [
                'locale' => $locale,
                'preferred_contact' => $preferredContact,
                'search' => $search,
            ],
            'totalCount' => Inquiry::count(),
            'todayCount' => Inquiry::whereDate('created_at', today())->count(),
        ]);

        return $this->renderPjaxView($request, $view);
    }

    public function show(Request $request, Inquiry $inquiry)
    {
        $inquiry->load('product:id,name_ka,name_en,slug,price,currency');

        $view = view('admin.inquiries.show', [
            'inquiry' => $inquiry,
        ]);

        return $this->renderPjaxView($request, $view);
    }
}
