<?php

namespace App\Services;

use App\Models\Inquiry;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramInquiryNotifier
{
    public function send(Inquiry $inquiry): void
    {
        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (! $token || ! $chatId) {
            return;
        }

        $productName = optional($inquiry->product)->name;

        $lines = [
            '🔔 ახალი Inquiry',
            '',
            '👤 სახელი: ' . $inquiry->name,
            '📞 ტელ: ' . $inquiry->phone,
            '📧 Email: ' . ($inquiry->email ?: '-'),
            '⌛ დრო: ' . now()->format('Y-m-d H:i'),
        ];

        if ($productName) {
            $lines[] = '⌚ პროდუქტი: ' . $productName;
        }

        if ($inquiry->selected_color) {
            $lines[] = '🎨 ფერი: ' . $inquiry->selected_color;
        }

        $lines[] = '💬 შეტყობინება: ' . ($inquiry->message ?: '-');

        $message = implode("\n", $lines);

        try {
            $response = Http::asForm()
                ->timeout(8)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $message,
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
}
