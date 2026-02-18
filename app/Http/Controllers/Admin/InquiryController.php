<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use Illuminate\View\View;

class InquiryController extends Controller
{
    public function index(): View
    {
        $inquiries = Inquiry::with('product')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.inquiries.index', [
            'inquiries' => $inquiries,
        ]);
    }

    public function show(Inquiry $inquiry): View
    {
        $inquiry->load('product');

        return view('admin.inquiries.show', [
            'inquiry' => $inquiry,
        ]);
    }
}
