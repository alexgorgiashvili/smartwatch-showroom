<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramOrderNotifier
{
    public function send(Order $order): void
    {
        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (! $token || ! $chatId) {
            return;
        }

        if (! Cache::add('telegram:order:' . $order->id, true, now()->addDays(7))) {
            return;
        }

        $order->loadMissing('items.variant.product');
        $items = $order->items->map(function ($item): string {
            $model = trim((string) ($item->variant?->product?->model ?? ''));
            $label = $model !== '' ? $model : $item->product_name;

            return '• ' . $this->escape($label . ' — ' . $item->variant_name . ' × ' . $item->quantity);
        });

        $lines = [
            '🛒 <b>ახალი შეკვეთა</b>',
            '',
            '№: <b>' . $this->escape($order->order_number) . '</b>',
            '👤 ' . $this->escape($order->customer_name),
            '📞 ' . $this->escape($order->customer_phone),
            '💳 გადახდა: ' . ($order->payment_type === 1 ? 'ონლაინ — ' . $this->escape($order->payment_status) : 'კურიერთან'),
            '💰 ჯამი: <b>' . $this->escape($order->currency . ' ' . number_format((float) $order->total_amount, 2)) . '</b>',
            '',
            '📦 ნივთები:',
            ...$items->all(),
        ];

        if (filled($order->city) || filled($order->delivery_address)) {
            $lines[] = '';
            $lines[] = '📍 ' . $this->escape(trim(implode(', ', array_filter([$order->city, $order->delivery_address]))));
        }

        $lines[] = '<a href="' . $this->escape(route('admin.orders.show', $order)) . '">შეკვეთის გახსნა პანელში</a>';

        try {
            $response = Http::asForm()->timeout(8)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => implode("\n", $lines),
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);

            if (! $response->successful()) {
                Cache::forget('telegram:order:' . $order->id);
                Log::warning('Failed to send Telegram order notification', ['order_id' => $order->id, 'status' => $response->status()]);
            }
        } catch (\Throwable $exception) {
            Cache::forget('telegram:order:' . $order->id);
            Log::warning('Telegram order notification exception', ['order_id' => $order->id, 'error' => $exception->getMessage()]);
        }
    }

    private function escape(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
