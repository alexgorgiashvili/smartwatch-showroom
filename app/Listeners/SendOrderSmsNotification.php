<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Events\PaymentCompleted;
use App\Services\SmsOfficeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendOrderSmsNotification implements ShouldQueue
{
    use InteractsWithQueue;

    private SmsOfficeService $smsService;

    public function __construct(SmsOfficeService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Handle the event
     */
    public function handle(OrderCreated|PaymentCompleted $event): void
    {
        $order = $event->order;

        // Determine message based on event type and payment type
        if ($event instanceof OrderCreated) {
            // Only send SMS for courier payments (payment_type = 2)
            if ($order->payment_type !== 2 || $order->isSmsSent()) {
                return;
            }
            $message = $this->getCourierPaymentMessage();
        } elseif ($event instanceof PaymentCompleted) {
            // Only send SMS for card payments (payment_type = 1)
            if ($order->payment_type !== 1 || $order->isSmsSent()) {
                return;
            }
            $message = $this->getCardPaymentMessage();
        } else {
            return;
        }

        $result = $this->sendSms($order, $message);

        if ($result['success']) {
            $order->markSmsSent($result['reference'] ?? null);
        }
    }

    /**
     * Send SMS notification
     */
    private function sendSms($order, string $message): array
    {
        try {
            $result = $this->smsService->sendSms(
                $order->customer_phone,
                $message,
                false // Not urgent for order notifications
            );

            if ($result['success']) {
                Log::info('Order SMS sent successfully', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'phone' => $order->customer_phone,
                    'payment_type' => $order->payment_type,
                    'reference' => $result['reference'] ?? null
                ]);
            } else {
                Log::error('Failed to send order SMS', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'phone' => $order->customer_phone,
                    'payment_type' => $order->payment_type,
                    'error' => $result['message'] ?? 'Unknown error'
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Exception sending order SMS', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'phone' => $order->customer_phone,
                'payment_type' => $order->payment_type,
                'exception' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage(),
                'error_code' => 'exception'
            ];
        }
    }

    /**
     * Get message for courier payments
     */
    private function getCourierPaymentMessage(): string
    {
        return 'თქვენი შეკვეთა მიღებულია, ჩვენი გუნდი მალე დაგიკავშირდებათ შეკვეთის დასადასტურებლად';
    }

    /**
     * Get message for card payments
     */
    private function getCardPaymentMessage(): string
    {
        return 'შეკვეთა მიღებულია, ამანათის სტატუსს მიიღებთ ეტაპობრივად.';
    }
}
