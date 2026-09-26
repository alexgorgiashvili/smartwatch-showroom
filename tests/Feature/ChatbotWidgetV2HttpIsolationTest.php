<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatbotWidgetV2HttpIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function testWidgetV2SendsCandidateModelAndVerifiedPolicyThroughChatbotRoute(): void
    {
        $this->configureWidget(100);

        $this->postJson('/chatbot', ['message' => 'მიტანის პირობები მაინტერესებს'])
            ->assertOk()
            ->assertJsonPath('message', 'მიწოდება უფასოა საქართველოს მასშტაბით.');

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return str_ends_with($request->url(), '/chat/completions')
                && ($payload['model'] ?? null) === 'gpt-6-luna'
                && str_contains((string) data_get($payload, 'messages.0.content'), 'VERIFIED WIDGET BUSINESS POLICY:')
                && str_contains((string) data_get($payload, 'messages.0.content'), 'მიწოდება უფასოა საქართველოს მასშტაბით');
        });
    }

    public function testDisabledWidgetV2KeepsBaselineModelAndOmitsVerifiedPolicy(): void
    {
        $this->configureWidget(0);

        $this->postJson('/chatbot', ['message' => 'მიტანის პირობები მაინტერესებს'])
            ->assertOk();

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return str_ends_with($request->url(), '/chat/completions')
                && ($payload['model'] ?? null) === 'gpt-4.1-mini'
                && !str_contains((string) data_get($payload, 'messages.0.content'), 'VERIFIED WIDGET BUSINESS POLICY:');
        });
    }

    public function testWidgetV2DoesNotPublishInventedPriceWhenCatalogIsEmpty(): void
    {
        $this->configureWidget(100, 'ეს საათი ღირს 199 ₾.');
        config()->set('chatbot.reflection.max_retries', 1);

        $response = $this->postJson('/chatbot', ['message' => 'ეს საათი რა ღირს?']);

        $response->assertOk();
        $this->assertStringNotContainsString('199 ₾', (string) $response->json('message'));
        $this->assertNotNull($response->json('debug.fallback_reason'));
    }

    private function configureWidget(int $rolloutPercent, string $reply = 'მიწოდება უფასოა საქართველოს მასშტაბით.'): void
    {
        config()->set('chatbot.widget_v2.rollout_percent', $rolloutPercent);
        config()->set('chatbot.widget_v2.model', 'gpt-6-luna');
        config()->set('chatbot.supervisor.model', 'gpt-4.1-mini');
        config()->set('services.openai.key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1');
        config()->set('services.openai.intent_enabled', false);
        config()->set('services.pinecone.api_key', null);
        config()->set('services.pinecone.host', null);
        config()->set('services.cohere.enabled', false);

        Http::fake(fn (Request $request) => Http::response([
            'choices' => [[
                'message' => ['content' => $reply],
            ]],
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 12],
        ], 200));
    }
}
