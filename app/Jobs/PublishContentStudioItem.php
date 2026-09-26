<?php

namespace App\Jobs;

use App\Models\ContentItem;
use App\Services\ContentStudioPublisher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishContentStudioItem implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function __construct(public int $itemId) {}
    public function handle(ContentStudioPublisher $publisher): void { $publisher->publish(ContentItem::findOrFail($this->itemId)); }
}
