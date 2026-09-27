<?php

namespace Tests\Unit;

use App\Services\Chatbot\IntentResult;
use Tests\TestCase;

class IntentResultNullEntityTest extends TestCase
{
    public function testHelperStringNullDoesNotBecomeAProductName(): void
    {
        $rawIntent = IntentResult::fromArray([
            'standalone_query' => 'რომელ მოდელს აქვს ვიდეოზარი?',
            'intent' => 'features',
            'entities' => [
                'brand' => 'null', 'model' => 'null',
                'product_slug_hint' => 'null', 'category' => 'none',
            ],
            'needs_product_data' => true,
        ], 0);
        $this->assertTrue($rawIntent->hasSpecificProduct());
        $intent = $rawIntent->normalizedForWidget();

        $this->assertFalse($intent->hasSpecificProduct());
        $this->assertNull($intent->brand());
        $this->assertNull($intent->model());
        $this->assertNull($intent->productSlugHint());
    }

    public function testGenericSlugHintDoesNotSuppressWidgetCatalogSearch(): void
    {
        $rawIntent = IntentResult::fromArray([
            'standalone_query' => 'საბავშვო მოდელი და ფასები',
            'intent' => 'price_query',
            'entities' => ['product_slug_hint' => 'საბავშვო მოდელი'],
            'needs_product_data' => true,
        ], 0);

        $this->assertSame('საბავშვო მოდელი', $rawIntent->productSlugHint());
        $this->assertNull($rawIntent->normalizedForWidget()->productSlugHint());
    }
}
