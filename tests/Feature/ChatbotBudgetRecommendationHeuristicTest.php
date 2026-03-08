<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatbotBudgetRecommendationHeuristicTest extends TestCase
{
    use RefreshDatabase;

    public function testBudgetRecommendationQuerySkipsIntentModelCall(): void
    {
        config()->set('services.openai.key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1');
        config()->set('services.openai.model', 'gpt-4.1-mini');
        config()->set('services.openai.intent_model', 'gpt-4.1-nano');
        config()->set('services.openai.intent_enabled', true);
        config()->set('services.pinecone.api_key', null);
        config()->set('services.pinecone.host', null);

        Http::fake(function (Request $request) {
            return Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '20 ლარის ფარგლებში გვაქვს რამდენიმე ვარიანტი.',
                        ],
                    ],
                ],
            ], 200);
        });

        $response = $this->postJson('/chatbot', [
            'message' => 'რამე 20 ლარის ფარგლებში გაქვთ?',
        ]);

        $response->assertOk()
            ->assertJsonPath('debug.intent', 'recommendation');

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            return str_contains($request->url(), '/chat/completions')
                && !isset($request->data()['response_format']);
        });
    }
}
