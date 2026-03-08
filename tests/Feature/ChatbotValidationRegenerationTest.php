<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatbotValidationRegenerationTest extends TestCase
{
    use RefreshDatabase;

    public function testChatbotRegeneratesOnceAfterValidatorPriceMismatch(): void
    {
        config()->set('services.openai.key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1');
        config()->set('services.openai.model', 'gpt-4.1-mini');
        config()->set('services.openai.intent_model', 'gpt-4.1-nano');
        config()->set('services.openai.intent_enabled', true);
        config()->set('services.pinecone.api_key', null);
        config()->set('services.pinecone.host', null);

        $product = Product::create([
            'name_en' => 'MyTechnic Ultra',
            'name_ka' => 'MyTechnic Ultra',
            'slug' => 'mytechnic-ultra',
            'brand' => 'MyTechnic',
            'model' => 'Ultra',
            'price' => 79,
            'currency' => 'GEL',
            'is_active' => true,
            'featured' => false,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'Default',
            'quantity' => 5,
            'low_stock_threshold' => 2,
        ]);

        $chatCompletionCalls = 0;
        $regenerationPromptSeen = false;

        Http::fake(function (Request $request) use (&$chatCompletionCalls, &$regenerationPromptSeen) {
            $url = $request->url();

            if (str_contains($url, '/moderations')) {
                return Http::response([
                    'results' => [
                        ['flagged' => false],
                    ],
                ], 200);
            }

            if (str_contains($url, '/chat/completions')) {
                $data = $request->data();
                $messages = $data['messages'] ?? [];
                $usesJsonMode = isset($data['response_format']['type']) && $data['response_format']['type'] === 'json_object';

                if ($usesJsonMode) {
                    return Http::response([
                        'choices' => [
                            [
                                'message' => [
                                    'content' => json_encode([
                                        'standalone_query' => 'MyTechnic Ultra რა ღირს?',
                                        'intent' => 'price_query',
                                        'entities' => [
                                            'brand' => 'MyTechnic',
                                            'model' => 'Ultra',
                                            'product_slug_hint' => 'mytechnic-ultra',
                                        ],
                                        'needs_product_data' => true,
                                        'search_keywords' => ['MyTechnic', 'Ultra'],
                                        'is_out_of_domain' => false,
                                        'confidence' => 0.98,
                                    ], JSON_UNESCAPED_UNICODE),
                                ],
                            ],
                        ],
                    ], 200);
                }

                $chatCompletionCalls++;

                if ($chatCompletionCalls === 1) {
                    return Http::response([
                        'choices' => [
                            [
                                'message' => [
                                    'content' => 'MyTechnic Ultra ღირს 200 ₾.',
                                ],
                            ],
                        ],
                    ], 200);
                }

                $lastMessage = end($messages);
                $regenerationPromptSeen = is_array($lastMessage)
                    && str_contains((string) ($lastMessage['content'] ?? ''), 'Validation issues to fix');

                return Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => 'MyTechnic Ultra ღირს 79 ₾.',
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
            ->assertJsonPath('message', 'MyTechnic Ultra ღირს 79 ₾.')
            ->assertJsonPath('debug.validation_passed', true)
            ->assertJsonPath('debug.fallback_reason', null)
            ->assertJsonPath('debug.regeneration_attempted', true)
            ->assertJsonPath('debug.regeneration_succeeded', true);

        $this->assertSame(2, $chatCompletionCalls);
        $this->assertTrue($regenerationPromptSeen);
    }
}
