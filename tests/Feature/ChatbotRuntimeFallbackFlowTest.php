<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatbotRuntimeFallbackFlowTest extends TestCase
{
    use RefreshDatabase;

    public function testChatbotUsesHelpfulFallbackWhenProviderUnavailable(): void
    {
        $this->configureRuntimeFallbackTest();

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/chat/completions')) {
                return Http::response(['error' => 'upstream unavailable'], 503);
            }

            return Http::response([], 200);
        });

        $response = $this->postJson('/chatbot', [
            'message' => 'MyTechnic Ultra რა ღირს?',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'დამეხმარეთ ცოტათი მეტად: მომწერეთ ბიუჯეტი, სასურველი ფუნქცია ან კონკრეტული მოდელი, და ზუსტად შეგირჩევთ.')
            ->assertJsonPath('debug.fallback_reason', 'provider_unavailable')
            ->assertJsonPath('debug.regeneration_attempted', false);

        $botMessage = Message::query()->where('sender_type', 'bot')->firstOrFail();
        $this->assertSame('provider_unavailable', data_get($botMessage->metadata, 'fallback_reason'));
        $this->assertFalse((bool) data_get($botMessage->metadata, 'chatbot_failure'));
    }

    public function testChatbotUsesHelpfulFallbackWhenModelReturnsEmptyOutput(): void
    {
        $this->configureRuntimeFallbackTest();

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/chat/completions')) {
                return Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => '',
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response([], 200);
        });

        $response = $this->postJson('/chatbot', [
            'message' => 'MyTechnic Ultra რა ღირს?',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'დამეხმარეთ ცოტათი მეტად: მომწერეთ ბიუჯეტი, სასურველი ფუნქცია ან კონკრეტული მოდელი, და ზუსტად შეგირჩევთ.')
            ->assertJsonPath('debug.fallback_reason', 'empty_model_output')
            ->assertJsonPath('debug.regeneration_attempted', false);

        $botMessage = Message::query()->where('sender_type', 'bot')->firstOrFail();
        $this->assertSame('empty_model_output', data_get($botMessage->metadata, 'fallback_reason'));
        $this->assertFalse((bool) data_get($botMessage->metadata, 'chatbot_failure'));
    }

    public function testChatbotRecoversRecommendationQueriesWithDeterministicFallbackReply(): void
    {
        $this->configureRuntimeFallbackTest();

        Product::create([
            'name_en' => 'MyTechnic Ultra',
            'name_ka' => 'MyTechnic Ultra',
            'slug' => 'mytechnic-ultra',
            'price' => 299,
            'sale_price' => null,
            'currency' => 'GEL',
            'is_active' => true,
            'featured' => true,
        ]);

        Product::create([
            'name_en' => 'MyTechnic Neo',
            'name_ka' => 'MyTechnic Neo',
            'slug' => 'mytechnic-neo',
            'price' => 199,
            'sale_price' => null,
            'currency' => 'GEL',
            'is_active' => true,
            'featured' => false,
        ]);

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/chat/completions')) {
                throw new \RuntimeException('timeout');
            }

            return Http::response([], 200);
        });

        $response = $this->postJson('/chatbot', [
            'message' => 'რაიმე 2გ მოდელი მირჩიე',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('debug.fallback_reason', 'provider_exception');

        $message = (string) $response->json('message');
        $this->assertStringContainsString('2G სმარტსაათების არჩევანი არ გვაქვს', $message);
        $this->assertStringContainsString('MyTechnic Ultra', $message);
        $this->assertStringContainsString('MyTechnic Neo', $message);
        $this->assertStringNotContainsString('ბოდიში, დროებით პრობლემა გვაქვს', $message);
    }

    private function configureRuntimeFallbackTest(): void
    {
        config()->set('services.openai.key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1');
        config()->set('services.openai.model', 'gpt-4.1-mini');
        config()->set('services.openai.intent_enabled', false);
        config()->set('services.pinecone.api_key', null);
        config()->set('services.pinecone.host', null);
    }
}
