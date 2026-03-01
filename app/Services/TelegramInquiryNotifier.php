<?php

namespace App\Services;

use App\Models\Inquiry;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramInquiryNotifier
{
    private const DEFAULT_COUNTRY_CODE = '995';
    private const MAX_WHATSAPP_PREFILL_LENGTH = 500;

    public function send(Inquiry $inquiry): void
    {
        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (! $token || ! $chatId) {
            return;
        }

        $productName = optional($inquiry->product)->name;
        $draftReply = trim((string) $inquiry->getAttribute('chatbot_draft_reply'));
        $whatsAppUrl = $this->buildWhatsAppUrl($inquiry->phone, $draftReply);

        $lines = [
            '🔔 ახალი Inquiry',
            '',
            '👤 სახელი: ' . $this->escapeForTelegramHtml($inquiry->name),
            '📞 ტელ: ' . $this->escapeForTelegramHtml($inquiry->phone),
            $whatsAppUrl
                ? '🟢 WhatsApp: <a href="' . $this->escapeForTelegramHtml($whatsAppUrl) . '">გახსენი ჩატი წინასწარი ტექსტით</a>'
                : '🟢 WhatsApp: -',
            '📧 Email: ' . $this->escapeForTelegramHtml($inquiry->email ?: '-'),
            '⌛ დრო: ' . now()->format('Y-m-d H:i'),
        ];

        if ($productName) {
            $lines[] = '⌚ პროდუქტი: ' . $this->escapeForTelegramHtml($productName);
        }

        if ($inquiry->selected_color) {
            $lines[] = '🎨 ფერი: ' . $this->escapeForTelegramHtml($inquiry->selected_color);
        }

        $lines[] = '💬 შეტყობინება: ' . $this->escapeForTelegramHtml($inquiry->message ?: '-');

        if ($draftReply !== '') {
            $lines[] = '';
            $lines[] = '🤖 Chatbot Draft პასუხი:';
            $lines[] = $this->escapeForTelegramHtml($draftReply);
        }

        $message = implode("\n", $lines);

        try {
            $response = Http::asForm()
                ->timeout(8)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]);

            if (! $response->successful()) {
                Log::warning('Failed to send Telegram inquiry notification', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'inquiry_id' => $inquiry->id,
                ]);
            }
        } catch (\Throwable $exception) {
            Log::warning('Telegram inquiry notification exception', [
                'inquiry_id' => $inquiry->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function buildWhatsAppUrl(?string $phone, ?string $prefillText = null): ?string
    {
        $normalizedPhone = $this->normalizePhoneForWhatsApp($phone);

        if (! $normalizedPhone) {
            return null;
        }

        $url = 'https://wa.me/' . $normalizedPhone;
        $prefillText = trim((string) $prefillText);

        if ($prefillText === '') {
            return $url;
        }

        if (mb_strlen($prefillText) > self::MAX_WHATSAPP_PREFILL_LENGTH) {
            $prefillText = trim(mb_substr($prefillText, 0, self::MAX_WHATSAPP_PREFILL_LENGTH)) . '...';
        }

        return $url . '?text=' . rawurlencode($prefillText);
    }

    private function escapeForTelegramHtml(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function normalizePhoneForWhatsApp(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if (! $digits) {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, self::DEFAULT_COUNTRY_CODE) && strlen($digits) >= 11) {
            return $digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return self::DEFAULT_COUNTRY_CODE . substr($digits, 1);
        }

        if (strlen($digits) === 9) {
            return self::DEFAULT_COUNTRY_CODE . $digits;
        }

        return $digits;
    }
}
