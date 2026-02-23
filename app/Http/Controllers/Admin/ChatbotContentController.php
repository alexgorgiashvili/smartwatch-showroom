<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactSetting;
use App\Models\Faq;
use App\Services\Chatbot\ChatbotContentSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChatbotContentController extends Controller
{
    public function index(): View
    {
        return view('admin.chatbot-content.index', [
            'faqs' => Faq::query()->orderBy('sort_order')->orderBy('id')->get(),
            'contactSettings' => ContactSetting::allKeyed(),
        ]);
    }

    public function storeFaq(Request $request, ChatbotContentSyncService $syncService): RedirectResponse
    {
        $data = $this->validateFaq($request);
        $faq = Faq::create($data);

        $synced = $syncService->syncFaq($faq);

        return redirect()->route('admin.chatbot-content.index')
            ->with('status', 'FAQ დაემატა.')
            ->with('warning', $synced ? null : 'მონაცემი შეინახა, მაგრამ embedding sync ვერ შესრულდა.');
    }

    public function updateFaq(Request $request, Faq $faq, ChatbotContentSyncService $syncService): RedirectResponse
    {
        $data = $this->validateFaq($request);
        $faq->update($data);

        $synced = $syncService->syncFaq($faq);

        return redirect()->route('admin.chatbot-content.index')
            ->with('status', 'FAQ განახლდა.')
            ->with('warning', $synced ? null : 'მონაცემი შეინახა, მაგრამ embedding sync ვერ შესრულდა.');
    }

    public function destroyFaq(Faq $faq, ChatbotContentSyncService $syncService): RedirectResponse
    {
        $syncService->deactivateFaq($faq);
        $faq->delete();

        return redirect()->route('admin.chatbot-content.index')
            ->with('status', 'FAQ წაიშალა.');
    }

    public function updateContacts(Request $request, ChatbotContentSyncService $syncService): RedirectResponse
    {
        $data = $request->validate([
            'phone_display' => ['required', 'string', 'max:80'],
            'phone_link' => ['required', 'string', 'max:30'],
            'whatsapp_url' => ['required', 'url', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'hours' => ['required', 'string', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'messenger_url' => ['nullable', 'url', 'max:255'],
            'telegram_url' => ['nullable', 'url', 'max:255'],
        ]);

        foreach ($data as $key => $value) {
            ContactSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        $synced = $syncService->syncContacts(ContactSetting::allKeyed());

        return redirect()->route('admin.chatbot-content.index')
            ->with('status', 'საკონტაქტო ინფორმაცია განახლდა.')
            ->with('warning', $synced ? null : 'მონაცემი შეინახა, მაგრამ embedding sync ვერ შესრულდა.');
    }

    private function validateFaq(Request $request): array
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string'],
            'category' => ['required', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
