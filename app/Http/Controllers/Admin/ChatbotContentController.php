<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatbotDocument;
use App\Models\ContactSetting;
use App\Models\Faq;
use App\Services\Chatbot\ChatbotContentSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChatbotContentController extends Controller
{
    public function index(Request $request)
    {
        $contactSettings = ContactSetting::allKeyed();

        $view = view('admin.chatbot-content.index', [
            'faqs' => Faq::query()->orderBy('sort_order')->orderBy('id')->get(),
            'contactSettings' => $contactSettings,
            'contactFields' => $this->contactFields(),
            'contactDocument' => ChatbotDocument::query()
                ->where('key', 'contact-main')
                ->first(['id', 'key', 'title', 'is_active', 'updated_at']),
        ]);

        return $this->renderPjaxView($request, $view);
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
            'location_en' => ['required', 'string', 'max:255'],
            'hours' => ['required', 'string', 'max:255'],
            'hours_en' => ['required', 'string', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'messenger_url' => ['nullable', 'url', 'max:255'],
            'faq_support_title' => ['required', 'string', 'max:120'],
            'faq_support_title_en' => ['required', 'string', 'max:120'],
            'faq_support_description' => ['required', 'string', 'max:500'],
            'faq_support_description_en' => ['required', 'string', 'max:500'],
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

    public function syncStaticPages(ChatbotContentSyncService $syncService): RedirectResponse
    {
        $synced = $syncService->syncStaticPages();

        return redirect()->route('admin.chatbot-content.index')
            ->with('status', 'About, privacy, and terms pages synced into chatbot content.')
            ->with('warning', $synced ? null : 'Pages saved, but embedding sync did not complete.');
    }

    private function validateFaq(Request $request): array
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:255'],
            'question_en' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string'],
            'answer_en' => ['required', 'string'],
            'category' => ['required', 'string', 'max:120'],
            'category_en' => ['required', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }

    /**
     * Contact settings fields shown in the admin form.
     *
     * @return array<int, array<string, mixed>>
     */
    private function contactFields(): array
    {
        return [
            [
                'name' => 'phone_display',
                'label' => 'Phone display',
                'type' => 'text',
                'placeholder' => '+995 555 123 456',
                'help' => 'Visible phone number on the site footer and contact page.',
            ],
            [
                'name' => 'phone_link',
                'label' => 'Phone link',
                'type' => 'text',
                'placeholder' => '+995555123456',
                'help' => 'Used for tel: links and click-to-call buttons.',
            ],
            [
                'name' => 'whatsapp_url',
                'label' => 'WhatsApp URL',
                'type' => 'url',
                'placeholder' => 'https://wa.me/995555123456',
                'help' => 'Used for WhatsApp buttons and chatbot handoff links.',
            ],
            [
                'name' => 'email',
                'label' => 'Email',
                'type' => 'email',
                'placeholder' => 'info@mytechnic.ge',
                'help' => 'Shown on the contact page, footer, and chatbot replies.',
            ],
            [
                'name' => 'location',
                'label' => 'Location',
                'type' => 'text',
                'placeholder' => 'Tbilisi, Georgia',
                'help' => 'Displayed in the footer, contact page, and chatbot context.',
            ],
            [
                'name' => 'location_en',
                'label' => 'Location (English)',
                'type' => 'text',
                'placeholder' => 'Tbilisi, Georgia',
                'help' => 'English location shown on the public storefront and in chatbot context.',
            ],
            [
                'name' => 'hours',
                'label' => 'Working hours',
                'type' => 'text',
                'placeholder' => 'ყოველდღე 10:00 - 20:00',
                'help' => 'Used in the contact card and the chatbot support profile.',
            ],
            [
                'name' => 'hours_en',
                'label' => 'Working hours (English)',
                'type' => 'text',
                'placeholder' => 'Every day, 10:00–20:00',
                'help' => 'English working hours for the contact card and chatbot support profile.',
            ],
            [
                'name' => 'instagram_url',
                'label' => 'Instagram URL',
                'type' => 'url',
                'placeholder' => 'https://www.instagram.com/mytechnic.ge',
                'help' => 'Optional social link shown in the header and footer.',
            ],
            [
                'name' => 'facebook_url',
                'label' => 'Facebook URL',
                'type' => 'url',
                'placeholder' => 'https://www.facebook.com/mytechnic.ge',
                'help' => 'Optional social link shown in the header and footer.',
            ],
            [
                'name' => 'messenger_url',
                'label' => 'Messenger URL',
                'type' => 'url',
                'placeholder' => 'https://m.me/mytechnic.ge',
                'help' => 'Optional social link shown in the header and footer.',
            ],
            [
                'name' => 'faq_support_title',
                'label' => 'FAQ support title',
                'type' => 'text',
                'placeholder' => 'გჭირდებათ სწრაფი დახმარება?',
                'help' => 'Shown in the FAQ sidebar support card.',
            ],
            [
                'name' => 'faq_support_title_en',
                'label' => 'FAQ support title (English)',
                'type' => 'text',
                'placeholder' => 'Need quick help?',
                'help' => 'English title shown in the FAQ sidebar support card.',
            ],
            [
                'name' => 'faq_support_description',
                'label' => 'FAQ support description',
                'type' => 'text',
                'placeholder' => 'მოგვწერეთ Live Chat-ში, WhatsApp-ზე ან Messenger-ზე და შეძლებისდაგვარად სწრაფად გიპასუხებთ.',
                'help' => 'Short helper text shown under the FAQ support title.',
            ],
            [
                'name' => 'faq_support_description_en',
                'label' => 'FAQ support description (English)',
                'type' => 'text',
                'placeholder' => 'Message us on Live Chat, WhatsApp, or Messenger.',
                'help' => 'English helper text shown under the FAQ support title.',
            ],
        ];
    }
}
