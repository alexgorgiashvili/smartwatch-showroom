<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Events\PaymentCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendOrderTelegramNotification implements ShouldQueue
{
    public function handle(OrderCreated|PaymentCompleted $event): void
    {
        app(TelegramOrderNotifier::class)->send($event->order);
    }
}
