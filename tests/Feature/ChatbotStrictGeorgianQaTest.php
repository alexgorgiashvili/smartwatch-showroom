<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Message;
use Tests\TestCase;

class ChatbotStrictGeorgianQaTest extends TestCase
{
    use RefreshDatabase;

    public function testChatbotReplacesNonGeorgianModelOutputWithStrictGeorgianFallback(): void
    {
        config()->set('services.openai.key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1');
        config()->set('services.openai.model', 'gpt-4.1-mini');
        config()->set('services.openai.intent_enabled', false);

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/chat/completions')) {
                return Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => 'Hello! I can assist you with products and pricing today.',
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
            ->assertJsonPath('debug.fallback_reason', 'strict_georgian');

        $reply = (string) $response->json('message');
        $this->assertMatchesRegularExpression('/\p{Georgian}/u', $reply);
        $this->assertStringNotContainsString('Hello', $reply);

        $botMessage = Message::query()->where('sender_type', 'bot')->firstOrFail();
        $this->assertSame('strict_georgian', data_get($botMessage->metadata, 'fallback_reason'));
        $this->assertFalse((bool) data_get($botMessage->metadata, 'chatbot_failure'));
    }
}
