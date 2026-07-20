<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Events\PaymentCompleted;
use App\Services\TelegramOrderNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendOrderTelegramNotification implements ShouldQueue
{
    public function handle(OrderCreated|PaymentCompleted $event, TelegramOrderNotifier $telegramOrderNotifier): void
    {
        $telegramOrderNotifier->send($event->order);
    }
}
