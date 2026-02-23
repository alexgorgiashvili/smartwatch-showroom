<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatbotRealtimeStockContextTest extends TestCase
{
    use RefreshDatabase;

    public function testChatbotPromptIncludesRealtimeStockFromDatabase(): void
    {
        config()->set('services.openai.key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1');
        config()->set('services.openai.model', 'gpt-4.1-mini');

        $product = Product::create([
            'name_en' => 'KidSIM Ultra',
            'name_ka' => 'KidSIM Ultra',
            'slug' => 'kidsim-ultra',
            'price' => 299,
            'sale_price' => 249,
            'currency' => 'GEL',
            'is_active' => true,
            'featured' => false,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'Color: Blue',
            'quantity' => 7,
            'low_stock_threshold' => 2,
        ]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'დიახ, KidSIM Ultra ამჟამად მარაგშია.',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/chatbot', [
            'message' => 'KidSIM Ultra მარაგშია?',
        ]);

        $response->assertStatus(200);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            if (!str_contains($request->url(), '/chat/completions')) {
                return false;
            }

            $messages = $request->data()['messages'] ?? [];
            $userMessage = $messages[1]['content'] ?? '';

            return str_contains($userMessage, 'Products (live stock from database):')
                && str_contains($userMessage, 'KidSIM Ultra')
                && str_contains($userMessage, 'stock: მარაგშია (7 ცალი)');
        });
    }

    public function testChatbotPrioritizesMentionedProductInLiveStockContext(): void
    {
        config()->set('services.openai.key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1');
        config()->set('services.openai.model', 'gpt-4.1-mini');

        $target = Product::create([
            'name_en' => 'KidSIM Ultra',
            'name_ka' => 'KidSIM Ultra',
            'slug' => 'kidsim-ultra',
            'price' => 299,
            'currency' => 'GEL',
            'is_active' => true,
            'featured' => false,
        ]);

        ProductVariant::create([
            'product_id' => $target->id,
            'name' => 'Color: Blue',
            'quantity' => 3,
            'low_stock_threshold' => 2,
        ]);

        $other = Product::create([
            'name_en' => 'KidSIM Neo',
            'name_ka' => 'KidSIM Neo',
            'slug' => 'kidsim-neo',
            'price' => 199,
            'currency' => 'GEL',
            'is_active' => true,
            'featured' => false,
        ]);

        ProductVariant::create([
            'product_id' => $other->id,
            'name' => 'Color: Black',
            'quantity' => 8,
            'low_stock_threshold' => 2,
        ]);

        $other->touch();

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'KidSIM Ultra მარაგშია.',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/chatbot', [
            'message' => 'მაინტერესებს kidsim ultra მარაგშია?',
        ]);

        $response->assertStatus(200);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            if (!str_contains($request->url(), '/chat/completions')) {
                return false;
            }

            $messages = $request->data()['messages'] ?? [];
            $userMessage = (string) ($messages[1]['content'] ?? '');

            $ultraPos = strpos($userMessage, '- KidSIM Ultra');
            $neoPos = strpos($userMessage, '- KidSIM Neo');

            return $ultraPos !== false
                && $neoPos !== false
                && $ultraPos < $neoPos;
        });
    }

    public function testChatbotIncludesExactRequestedVariantStockInPromptContext(): void
    {
        config()->set('services.openai.key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1');
        config()->set('services.openai.model', 'gpt-4.1-mini');

        $product = Product::create([
            'name_en' => 'KidSIM Ultra',
            'name_ka' => 'KidSIM Ultra',
            'slug' => 'kidsim-ultra',
            'price' => 299,
            'currency' => 'GEL',
            'is_active' => true,
            'featured' => false,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'Color: Blue',
            'quantity' => 0,
            'low_stock_threshold' => 2,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'Color: Black',
            'quantity' => 6,
            'low_stock_threshold' => 2,
        ]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'ლურჯი ვარიანტი ამოწურულია.',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/chatbot', [
            'message' => 'kidsim ultra blue მარაგშია?',
        ]);

        $response->assertStatus(200);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            if (!str_contains($request->url(), '/chat/completions')) {
                return false;
            }

            $messages = $request->data()['messages'] ?? [];
            $userMessage = (string) ($messages[1]['content'] ?? '');

            return str_contains($userMessage, 'Requested product (exact match from live database):')
                && str_contains($userMessage, 'Product: KidSIM Ultra | slug: kidsim-ultra')
                && str_contains($userMessage, 'Requested variant: Color: Blue')
                && str_contains($userMessage, 'Variant stock: ამოწურულია (0 ცალი)');
        });
    }

    public function testChatbotMatchesGeorgianColorToVariantStock(): void
    {
        config()->set('services.openai.key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1');
        config()->set('services.openai.model', 'gpt-4.1-mini');

        $product = Product::create([
            'name_en' => 'KidSIM Ultra',
            'name_ka' => 'KidSIM Ultra',
            'slug' => 'kidsim-ultra',
            'price' => 299,
            'currency' => 'GEL',
            'is_active' => true,
            'featured' => false,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'Color: Blue',
            'quantity' => 4,
            'low_stock_threshold' => 2,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'Color: Black',
            'quantity' => 0,
            'low_stock_threshold' => 2,
        ]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'ლურჯი ვარიანტი მარაგშია.',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/chatbot', [
            'message' => 'kidsim ultra ლურჯი ფერი არის?',
        ]);

        $response->assertStatus(200);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            if (!str_contains($request->url(), '/chat/completions')) {
                return false;
            }

            $messages = $request->data()['messages'] ?? [];
            $userMessage = (string) ($messages[1]['content'] ?? '');

            return str_contains($userMessage, 'Requested variant: Color: Blue')
                && str_contains($userMessage, 'Variant stock: მარაგშია (4 ცალი)');
        });
    }
}
