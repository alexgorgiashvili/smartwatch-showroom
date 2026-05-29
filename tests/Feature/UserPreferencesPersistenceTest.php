<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Customer;
use App\Services\Chatbot\BifurcatedMemoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserPreferencesPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function testChatbotPersistsUserPreferencesIntoCustomerRecord(): void
    {
        config()->set('services.openai.key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1');
        config()->set('services.openai.intent_enabled', false);
        config()->set('services.pinecone.api_key', null);
        config()->set('services.pinecone.host', null);

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'chat/completions')) {
                return Http::response([
                    'choices' => [[
                        'message' => [
                            'content' => '200 ლარამდე შავი GPS საათის შერჩევაში დაგეხმარები.',
                        ],
                    ]],
                ], 200);
            }

            return Http::response([], 200);
        });

        $this->postJson('/chatbot', [
            'message' => '200 ლარამდე შავი GPS საათი მინდა',
        ])->assertStatus(200);

        $customer = Customer::query()->firstOrFail()->fresh();

        $this->assertSame(200, data_get($customer->preferences, 'budget_max_gel'));
        $this->assertSame('black', data_get($customer->preferences, 'color'));
        $this->assertContains('gps', data_get($customer->preferences, 'features', []));
    }

    public function testStoredPreferencesReloadFromDatabaseAfterCacheClear(): void
    {
        config()->set('services.openai.key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1');
        config()->set('services.openai.intent_enabled', false);
        config()->set('services.pinecone.api_key', null);
        config()->set('services.pinecone.host', null);

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'chat/completions')) {
                return Http::response([
                    'choices' => [[
                        'message' => [
                            'content' => 'მწვანე GPS საათს და 150 ლარიან ბიუჯეტს გავითვალისწინებ.',
                        ],
                    ]],
                ], 200);
            }

            return Http::response([], 200);
        });

        $this->postJson('/chatbot', [
            'message' => '150 ლარამდე მწვანე GPS საათი მინდა',
        ])->assertStatus(200);

        $customer = Customer::query()->firstOrFail();

        Conversation::create([
            'customer_id' => $customer->id,
            'platform' => 'home',
            'platform_conversation_id' => 'widget_' . Str::uuid(),
            'subject' => 'Widget Chat 2',
            'status' => 'active',
            'unread_count' => 0,
            'last_message_at' => now(),
        ]);

        $memory = app(BifurcatedMemoryService::class);
        $memory->clearUserPreferences($customer->id);

        $reloaded = $memory->getUserPreferences($customer->id);

        $this->assertSame(150, $reloaded['budget_max_gel'] ?? null);
        $this->assertSame('green', $reloaded['color'] ?? null);
        $this->assertContains('gps', $reloaded['features'] ?? []);
    }
}
