<?php

namespace App\Listeners;

use App\Services\AI\AiCacheService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class InvalidateAiCacheOnProductUpdate implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct(
        private AiCacheService $cacheService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        // Get product from event
        $product = $event->product ?? null;

        if (!$product) {
            return;
        }

        // Invalidate product-specific caches
        $this->cacheService->invalidateProductSchema($product->id);

        // Invalidate product catalog for all languages
        $this->cacheService->invalidateProductCatalog();

        // Invalidate all recommendations (since product data changed)
        $this->cacheService->invalidateRecommendations();

        // Log cache invalidation
        Log::info('AI cache invalidated for product update', [
            'product_id' => $product->id,
            'product_name' => $product->name,
        ]);
    }
}
