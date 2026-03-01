<?php

namespace App\Http\Controllers;

use App\Models\Inquiry;
use App\Services\InquiryDraftReplyService;
use App\Services\TelegramInquiryNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InquiryController extends Controller
{
    public function store(
        Request $request,
        TelegramInquiryNotifier $telegramInquiryNotifier,
        InquiryDraftReplyService $inquiryDraftReplyService
    ): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['nullable', 'exists:products,id'],
            'selected_color' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
            'message' => ['nullable', 'string', 'max:1000'],
            'preferred_contact' => ['nullable', 'string', 'max:20'],
        ]);

        $data['locale'] = app()->getLocale();

        $inquiry = Inquiry::create($data);
        $inquiry->load('product');

        $draftReply = $inquiryDraftReplyService->generate($inquiry);

        if ($draftReply) {
            $inquiry->setAttribute('chatbot_draft_reply', $draftReply);
        }

        $telegramInquiryNotifier->send($inquiry);

        return redirect()
            ->back()
            ->with('status', __('ui.inquiry_success'));
    }
}
