<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Chatbot\IntentAnalyzerService;
use App\Services\Chatbot\IntentResult;
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

    public function testWidgetV2RejectsArmenianScriptInOtherwiseGeorgianReply(): void
    {
        $this->configureWidget(100, 'ეს საათი ունի հատուկ ფუნქცია. Վստահ եմ.');

        $response = $this->postJson('/chatbot', ['message' => 'საათის ფუნქციები მაინტერესებს']);

        $response->assertOk();
        $this->assertDoesNotMatchRegularExpression('/[\x{0530}-\x{058F}]/u', (string) $response->json('message'));
    }

    public function testWidgetV2NormalizesArmenianPunctuationInGeorgianReply(): void
    {
        $this->configureWidget(100, "მიწოდება უფასოა საქართველოს მასშტაბით\u{0589}");

        $response = $this->postJson('/chatbot', ['message' => 'მიტანის პირობები მაინტერესებს']);

        $response->assertOk()->assertJsonPath('message', 'მიწოდება უფასოა საქართველოს მასშტაბით.');
    }

    public function testWidgetV2KeepsPriceQuestionRelevantWhenRejectingMixedScript(): void
    {
        $this->configureWidget(100, 'ფასი ვერ დავადგინე, քանի որ კატალოგში არ ჩანს.');
        $intentAnalyzer = \Mockery::mock(IntentAnalyzerService::class);
        $intentAnalyzer->shouldReceive('analyze')->once()->andReturn(IntentResult::fromArray([
            'standalone_query' => 'ეს საათი რა ღირს?',
            'intent' => 'price_query',
            'needs_product_data' => true,
            'search_keywords' => ['საათი'],
            'confidence' => 0.99,
        ], 0));
        app()->instance(IntentAnalyzerService::class, $intentAnalyzer);

        $response = $this->postJson('/chatbot', ['message' => 'ეს საათი რა ღირს?']);

        $response->assertOk();
        $this->assertStringContainsString('ფასს ვერ ვადასტურებ', (string) $response->json('message'));
        $this->assertDoesNotMatchRegularExpression('/[\x{0530}-\x{058F}]/u', (string) $response->json('message'));
    }

    public function testWidgetV2DoesNotPublishUnsupportedStockClaim(): void
    {
        $this->configureWidget(100, 'დიახ, ეს მოდელი მარაგშია.');
        config()->set('chatbot.reflection.max_retries', 1);

        $response = $this->postJson('/chatbot', ['message' => 'ეს მოდელი მარაგშია?']);

        $response->assertOk();
        $this->assertStringNotContainsString('დიახ, ეს მოდელი მარაგშია.', (string) $response->json('message'));
        $this->assertNotNull($response->json('debug.fallback_reason'));
    }

    public function testWidgetV2KeepsRequestedOutOfStockProductInValidationEvidence(): void
    {
        $this->createMixedStockCatalog();
        $this->configureWidget(100, 'MyTechnic Alpha-ს ვიდეოზარი დადასტურებული არ არის. ეს მოდელი მარაგში არ არის.');

        $response = $this->postJson('/chatbot', ['message' => 'MyTechnic Alpha-ს ვიდეოზარი აქვს?']);

        $response->assertOk();
        $this->assertStringContainsString('მარაგში არ არის', (string) $response->json('message'));
        $this->assertNull($response->json('debug.fallback_reason'));
    }

    public function testWidgetV2RejectsStockClaimBorrowedFromAnotherProduct(): void
    {
        $this->createMixedStockCatalog();
        $this->configureWidget(100, 'MyTechnic Alpha მარაგშია.');
        config()->set('chatbot.reflection.max_retries', 1);

        $response = $this->postJson('/chatbot', ['message' => 'MyTechnic Alpha მარაგშია?']);

        $response->assertOk();
        $this->assertStringNotContainsString('MyTechnic Alpha მარაგშია.', (string) $response->json('message'));
        $this->assertNotNull($response->json('debug.fallback_reason'));
    }

    public function testWidgetV2RejectsPriceBorrowedFromAnotherProduct(): void
    {
        $this->createMixedStockCatalog();
        $this->configureWidget(100, 'MyTechnic Alpha ღირს 299 ₾.');
        config()->set('chatbot.reflection.max_retries', 1);

        $response = $this->postJson('/chatbot', ['message' => 'MyTechnic Alpha რა ღირს?']);

        $response->assertOk();
        $this->assertStringNotContainsString('299 ₾', (string) $response->json('message'));
        $this->assertNotNull($response->json('debug.fallback_reason'));
    }

    private function createMixedStockCatalog(): void
    {
        foreach ([
            ['name' => 'MyTechnic Alpha', 'slug' => 'mytechnic-alpha', 'price' => 199, 'quantity' => 0],
            ['name' => 'MyTechnic Beta', 'slug' => 'mytechnic-beta', 'price' => 299, 'quantity' => 5],
        ] as $item) {
            $product = Product::create([
                'name_en' => $item['name'],
                'name_ka' => $item['name'],
                'slug' => $item['slug'],
                'price' => $item['price'],
                'is_active' => true,
            ]);
            ProductVariant::create([
                'product_id' => $product->id,
                'name' => 'Default',
                'quantity' => $item['quantity'],
            ]);
        }
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
