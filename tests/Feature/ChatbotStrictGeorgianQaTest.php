<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatbotStrictGeorgianQaTest extends TestCase
{
    use RefreshDatabase;

    public function testChatbotReplacesNonGeorgianModelOutputWithStrictGeorgianFallback(): void
    {
        config()->set('services.openai.key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1');
        config()->set('services.openai.model', 'gpt-4.1-mini');

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Hello! I can assist you with products and pricing today.',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/chatbot', [
            'message' => 'gamarjoba',
        ]);

        $response->assertStatus(200);

        $reply = (string) $response->json('message');
        $this->assertMatchesRegularExpression('/\p{Georgian}/u', $reply);
        $this->assertStringNotContainsString('Hello', $reply);
    }
}
