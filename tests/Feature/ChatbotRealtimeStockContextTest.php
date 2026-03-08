<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatbotRealtimeStockContextTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param array<int, array<string, mixed>> $messages
     */
    private function latestUserMessage(array $messages): string
    {
        $latest = collect($messages)
            ->filter(fn (array $message): bool => ($message['role'] ?? null) === 'user')
            ->last();

        return (string) ($latest['content'] ?? '');
    }

    private function fakeOpenAiForChatbot(string $assistantReply): void
    {
        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($assistantReply) {
            $url = $request->url();

            if (str_contains($url, '/moderations')) {
                return Http::response([
                    'results' => [
                        ['flagged' => false],
                    ],
                ], 200);
            }

            if (str_contains($url, '/embeddings')) {
                return Http::response([
                    'data' => [
                        ['embedding' => [0.01, 0.02, 0.03]],
                    ],
                ], 200);
            }

            if (str_contains($url, '/chat/completions')) {
                return Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => $assistantReply,
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response([], 200);
        });
    }

    public function testChatbotPromptIncludesRealtimeStockFromDatabase(): void
    {
        config()->set('services.openai.key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1');
        config()->set('services.openai.model', 'gpt-4.1-mini');

        $product = Product::create([
            'name_en' => 'MyTechnic Ultra',
            'name_ka' => 'MyTechnic Ultra',
            'slug' => 'mytechnic-ultra',
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

        $this->fakeOpenAiForChatbot('დიახ, MyTechnic Ultra ამჟამად მარაგშია.');

        $response = $this->postJson('/chatbot', [
            'message' => 'MyTechnic Ultra მარაგშია?',
        ]);

        $response->assertStatus(200);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            if (!str_contains($request->url(), '/chat/completions')) {
                return false;
            }

            $messages = $request->data()['messages'] ?? [];
            $userMessage = $this->latestUserMessage(is_array($messages) ? $messages : []);

            return (str_contains($userMessage, 'Products (live stock from database):')
                || str_contains($userMessage, 'პროდუქტები (ლაივ მარაგი ბაზიდან):'))
                && str_contains($userMessage, 'MyTechnic Ultra')
                && (str_contains($userMessage, 'stock: მარაგშია (7 ცალი)')
                    || str_contains($userMessage, 'მარაგი: მარაგშია (7 ცალი)'));
        });
    }

    public function testChatbotPrioritizesMentionedProductInLiveStockContext(): void
    {
        config()->set('services.openai.key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1');
        config()->set('services.openai.model', 'gpt-4.1-mini');

        $target = Product::create([
            'name_en' => 'MyTechnic Ultra',
            'name_ka' => 'MyTechnic Ultra',
            'slug' => 'mytechnic-ultra',
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
            'name_en' => 'MyTechnic Neo',
            'name_ka' => 'MyTechnic Neo',
            'slug' => 'mytechnic-neo',
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

        $this->fakeOpenAiForChatbot('MyTechnic Ultra მარაგშია.');

        $response = $this->postJson('/chatbot', [
            'message' => 'მაინტერესებს MyTechnic Ultra მარაგშია?',
        ]);

        $response->assertStatus(200);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            if (!str_contains($request->url(), '/chat/completions')) {
                return false;
            }

            $messages = $request->data()['messages'] ?? [];
            $userMessage = $this->latestUserMessage(is_array($messages) ? $messages : []);

            $ultraPos = strpos($userMessage, '- MyTechnic Ultra');
            $neoPos = strpos($userMessage, '- MyTechnic Neo');

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
            'name_en' => 'MyTechnic Ultra',
            'name_ka' => 'MyTechnic Ultra',
            'slug' => 'mytechnic-ultra',
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

        $this->fakeOpenAiForChatbot('ლურჯი ვარიანტი ამოწურულია.');

        $response = $this->postJson('/chatbot', [
            'message' => 'MyTechnic Ultra blue მარაგშია?',
        ]);

        $response->assertStatus(200);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            if (!str_contains($request->url(), '/chat/completions')) {
                return false;
            }

            $messages = $request->data()['messages'] ?? [];
            $userMessage = $this->latestUserMessage(is_array($messages) ? $messages : []);

            return (str_contains($userMessage, 'Requested product (exact match from live database):')
                    || str_contains($userMessage, 'მოთხოვნილი პროდუქტი (ზუსტი დამთხვევა ლაივ ბაზიდან):'))
                && (str_contains($userMessage, 'Product: MyTechnic Ultra | slug: mytechnic-ultra')
                    || str_contains($userMessage, 'პროდუქტი: MyTechnic Ultra | slug: mytechnic-ultra')
                    || str_contains($userMessage, 'პროდუქტი: MyTechnic Ultra | ბმული იდენტიფიკატორი: mytechnic-ultra'))
                && (str_contains($userMessage, 'Requested variant: Color: Blue')
                    || str_contains($userMessage, 'მოთხოვნილი ვარიანტი: Color: Blue'))
                && (str_contains($userMessage, 'Variant stock: ამოწურულია (0 ცალი)')
                    || str_contains($userMessage, 'ვარიანტის მარაგი: ამოწურულია (0 ცალი)'));
        });
    }

    public function testChatbotMatchesGeorgianColorToVariantStock(): void
    {
        config()->set('services.openai.key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1');
        config()->set('services.openai.model', 'gpt-4.1-mini');

        $product = Product::create([
            'name_en' => 'MyTechnic Ultra',
            'name_ka' => 'MyTechnic Ultra',
            'slug' => 'mytechnic-ultra',
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

        $this->fakeOpenAiForChatbot('ლურჯი ვარიანტი მარაგშია.');

        $response = $this->postJson('/chatbot', [
            'message' => 'MyTechnic Ultra ლურჯი ფერი არის?',
        ]);

        $response->assertStatus(200);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            if (!str_contains($request->url(), '/chat/completions')) {
                return false;
            }

            $messages = $request->data()['messages'] ?? [];
            $userMessage = $this->latestUserMessage(is_array($messages) ? $messages : []);

            return (str_contains($userMessage, 'Requested variant: Color: Blue')
                || str_contains($userMessage, 'მოთხოვნილი ვარიანტი: Color: Blue'))
                && (str_contains($userMessage, 'Variant stock: მარაგშია (4 ცალი)')
                    || str_contains($userMessage, 'ვარიანტის მარაგი: მარაგშია (4 ცალი)'));
        });
    }

    public function testChatbotResponseIncludesProductImageInWidgetCarouselPayload(): void
    {
        config()->set('services.openai.key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1');
        config()->set('services.openai.model', 'gpt-4.1-mini');

        $product = Product::create([
            'name_en' => 'MyTechnic Ultra',
            'name_ka' => 'MyTechnic Ultra',
            'slug' => 'mytechnic-ultra',
            'price' => 299,
            'sale_price' => 249,
            'currency' => 'GEL',
            'is_active' => true,
            'featured' => false,
        ]);

        ProductImage::create([
            'product_id' => $product->id,
            'path' => 'products/mytechnic-ultra/main.jpg',
            'thumbnail_path' => 'products/mytechnic-ultra/main-thumb.jpg',
            'alt_en' => 'MyTechnic Ultra',
            'alt_ka' => 'MyTechnic Ultra',
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'Color: Blue',
            'quantity' => 4,
            'low_stock_threshold' => 2,
        ]);

        $this->fakeOpenAiForChatbot('დიახ, MyTechnic Ultra გვაქვს.');

        $response = $this->postJson('/chatbot', [
            'message' => 'MyTechnic Ultra გაქვთ?',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('products.0.name', 'MyTechnic Ultra')
            ->assertJsonPath('products.0.url', url('/products/mytechnic-ultra'))
            ->assertJsonPath('products.0.image', '/storage/products/mytechnic-ultra/main-thumb.jpg');
    }

    public function testChatbotGreetingReturnsShortDeterministicReplyWithoutProducts(): void
    {
        config()->set('services.openai.key', '');

        $response = $this->postJson('/chatbot', [
            'message' => 'gamarjveba',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'გამარჯობა! სიამოვნებით დაგეხმარებით. მითხარით რა გაინტერესებთ: ფასი, მარაგი, GPS, SOS თუ კონკრეტული მოდელი.')
            ->assertJsonMissingPath('products');
    }

    public function testChatbotAttachesOnlyMentionedProductCardWhenReplyIsSpecific(): void
    {
        config()->set('services.openai.key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1');
        config()->set('services.openai.model', 'gpt-4.1-mini');

        $ultra = Product::create([
            'name_en' => 'MyTechnic Ultra',
            'name_ka' => 'MyTechnic Ultra',
            'slug' => 'mytechnic-ultra',
            'price' => 299,
            'currency' => 'GEL',
            'is_active' => true,
            'featured' => false,
        ]);

        ProductVariant::create([
            'product_id' => $ultra->id,
            'name' => 'Color: Blue',
            'quantity' => 4,
            'low_stock_threshold' => 2,
        ]);

        $neo = Product::create([
            'name_en' => 'MyTechnic Neo',
            'name_ka' => 'MyTechnic Neo',
            'slug' => 'mytechnic-neo',
            'price' => 199,
            'currency' => 'GEL',
            'is_active' => true,
            'featured' => false,
        ]);

        ProductVariant::create([
            'product_id' => $neo->id,
            'name' => 'Color: Black',
            'quantity' => 6,
            'low_stock_threshold' => 2,
        ]);

        $this->fakeOpenAiForChatbot('გირჩევთ MyTechnic Ultra მოდელს.');

        $response = $this->postJson('/chatbot', [
            'message' => 'რაიმე საათი მირჩიეთ',
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.name', 'MyTechnic Ultra');
    }

    public function testChatbotDoesNotReturnCarouselProductsWhenIntegrityFallbackIsUsed(): void
    {
        config()->set('services.openai.key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1');
        config()->set('services.openai.model', 'gpt-4.1-mini');

        $product = Product::create([
            'name_en' => 'MyTechnic Ultra',
            'name_ka' => 'MyTechnic Ultra',
            'slug' => 'mytechnic-ultra',
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

        $this->fakeOpenAiForChatbot('MyTechnic Ultra ამოწურულია.');

        $response = $this->postJson('/chatbot', [
            'message' => 'MyTechnic Ultra მარაგშია?',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'ზუსტი ფასი და მარაგი ამ მომენტში დამატებით გადამოწმებას საჭიროებს. გთხოვთ, დაგვიკავშირდეთ და დაუყოვნებლივ დაგიზუსტებთ ინფორმაციას.')
            ->assertJsonMissingPath('products');
    }
}
