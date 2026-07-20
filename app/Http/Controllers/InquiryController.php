<?php

namespace App\Http\Controllers;

use App\Models\Inquiry;
use App\Services\InquiryDraftReplyService;
use App\Services\TelegramInquiryNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class InquiryController extends Controller
{
    public function store(
        Request $request,
        TelegramInquiryNotifier $telegramInquiryNotifier,
        InquiryDraftReplyService $inquiryDraftReplyService
    ): RedirectResponse
    {
        if (filled($request->input('website'))) {
            return $this->successResponse();
        }

        $rateLimitKey = 'inquiry:ip:' . sha1((string) $request->ip());
        if (RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
            return $this->successResponse();
        }

        RateLimiter::hit($rateLimitKey, 600);

        $data = $request->validate([
            'product_id' => ['nullable', 'exists:products,id'],
            'selected_color' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'regex:/^(?:995)?5\d{8}$/'],
            'email' => ['nullable', 'email', 'max:120'],
            'message' => ['nullable', 'string', 'max:1000'],
            'preferred_contact' => ['nullable', 'string', 'max:20'],
        ]);

        if ($this->containsExternalLink($data['message'] ?? '')) {
            return $this->successResponse();
        }

        $duplicateKey = 'inquiry:duplicate:' . hash('sha256', implode('|', [
            (string) ($data['product_id'] ?? ''),
            $this->normalizeText($data['message'] ?? ''),
        ]));

        if (! Cache::add($duplicateKey, true, now()->addMinutes(30))) {
            return $this->successResponse();
        }

        $data['locale'] = app()->getLocale();

        $inquiry = Inquiry::create($data);
        $inquiry->load('product');

        $draftReply = $inquiryDraftReplyService->generate($inquiry);

        if ($draftReply) {
            $inquiry->setAttribute('chatbot_draft_reply', $draftReply);
        }

        $telegramInquiryNotifier->send($inquiry);

        return $this->successResponse(array_filter([
                'name' => 'Lead',
                'payload' => array_filter([
                    'content_name' => $inquiry->product?->name,
                    'content_ids' => $inquiry->product ? [(string) $inquiry->product->id] : null,
                    'content_type' => $inquiry->product ? 'product' : 'inquiry',
                    'source' => 'inquiry_form',
                ], fn ($value) => $value !== null),
            ]));
    }

    private function successResponse(?array $analyticsEvent = null): RedirectResponse
    {
        $response = redirect()->back()->with('status', __('ui.inquiry_success'));

        return $analyticsEvent ? $response->with('analytics_event', $analyticsEvent) : $response;
    }

    private function containsExternalLink(string $message): bool
    {
        return (bool) preg_match('/(?:https?:\/\/|www\.)/iu', $message);
    }

    private function normalizeText(string $value): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)));
    }
}
