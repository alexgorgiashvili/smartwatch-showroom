<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatbotAiCallBudgetTest extends TestCase
{
    use RefreshDatabase;

    public function testWidgetNormalPathUsesTwoOpenAiCallsWithoutModeration(): void
    {
        config()->set('services.openai.key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1');
        config()->set('services.openai.model', 'gpt-4.1-mini');
        config()->set('services.openai.intent_model', 'gpt-4.1-nano');
        config()->set('services.openai.intent_enabled', true);
        config()->set('services.pinecone.api_key', null);
        config()->set('services.pinecone.host', null);
        config()->set('services.cohere.enabled', false);

        Http::fake(function (Request $request) {
            if (!str_contains($request->url(), '/chat/completions')) {
                return Http::response([], 500);
            }

            $data = $request->data();
            $usesJsonMode = isset($data['response_format']['type']) && $data['response_format']['type'] === 'json_object';

            if ($usesJsonMode) {
                return Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'standalone_query' => 'მიტანის პირობები მაინტერესებს',
                                    'intent' => 'general',
                                    'entities' => [],
                                    'needs_product_data' => false,
                                    'search_keywords' => [],
                                    'is_out_of_domain' => false,
                                    'confidence' => 0.95,
                                ], JSON_UNESCAPED_UNICODE),
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'მიტანის ზუსტ პირობებს ჩვენი გუნდი დაგიზუსტებთ, თუმცა სიამოვნებით დაგეხმარებით შეკვეთის პროცესშიც.',
                        ],
                    ],
                ],
            ], 200);
        });

        $response = $this->postJson('/chatbot', [
            'message' => 'მიტანის პირობები მაინტერესებს',
        ]);

        $response->assertOk()
            ->assertJsonPath('debug.intent', 'general')
            ->assertJsonPath('debug.regeneration_attempted', false);

        Http::assertSentCount(2);
        Http::assertSent(function (Request $request): bool {
            return str_contains($request->url(), '/chat/completions')
                && isset($request->data()['response_format']['type'])
                && $request->data()['response_format']['type'] === 'json_object';
        });
        Http::assertSent(function (Request $request): bool {
            return str_contains($request->url(), '/chat/completions')
                && !isset($request->data()['response_format']);
        });
    }
}
