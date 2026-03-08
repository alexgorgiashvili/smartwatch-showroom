<?php

namespace Tests\Feature;

use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatbotRuntimeFallbackFlowTest extends TestCase
{
    use RefreshDatabase;

    public function testChatbotMarksProviderUnavailableFallbackInDebugAndPersistence(): void
    {
        $this->configureRuntimeFallbackTest();

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/moderations')) {
                return Http::response([
                    'results' => [
                        ['flagged' => false],
                    ],
                ], 200);
            }

            if (str_contains($request->url(), '/chat/completions')) {
                return Http::response(['error' => 'upstream unavailable'], 503);
            }

            return Http::response([], 200);
        });

        $response = $this->postJson('/chatbot', [
            'message' => 'MyTechnic Ultra რა ღირს?',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'ბოდიში, სერვისი დროებით მიუწვდომელია.')
            ->assertJsonPath('debug.fallback_reason', 'provider_unavailable')
            ->assertJsonPath('debug.regeneration_attempted', false);

        $botMessage = Message::query()->where('sender_type', 'bot')->firstOrFail();
        $this->assertSame('provider_unavailable', data_get($botMessage->metadata, 'fallback_reason'));
        $this->assertFalse((bool) data_get($botMessage->metadata, 'chatbot_failure'));
    }

    public function testChatbotMarksEmptyModelOutputFallbackInDebugAndPersistence(): void
    {
        $this->configureRuntimeFallbackTest();

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/moderations')) {
                return Http::response([
                    'results' => [
                        ['flagged' => false],
                    ],
                ], 200);
            }

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
            ->assertJsonPath('message', 'ბოდიში, პასუხი ვერ მივიღე. სცადეთ კიდევ ერთხელ.')
            ->assertJsonPath('debug.fallback_reason', 'empty_model_output')
            ->assertJsonPath('debug.regeneration_attempted', false);

        $botMessage = Message::query()->where('sender_type', 'bot')->firstOrFail();
        $this->assertSame('empty_model_output', data_get($botMessage->metadata, 'fallback_reason'));
        $this->assertFalse((bool) data_get($botMessage->metadata, 'chatbot_failure'));
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
