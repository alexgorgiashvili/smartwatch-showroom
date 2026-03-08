<?php

namespace Tests\Unit;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Services\AiConversationService;
use App\Services\AiSuggestionService;
use App\Services\MetaApiService;
use App\Services\WhatsAppService;
use App\Services\Chatbot\CarouselBuilderService;
use App\Services\Chatbot\ChatbotFallbackStrategyService;
use App\Services\Chatbot\ChatbotProductSelectionService;
use App\Services\Chatbot\ChatbotQualityMetricsService;
use App\Services\Chatbot\ConversationMemoryService;
use App\Services\Chatbot\HybridSearchService;
use App\Services\Chatbot\InputGuardResult;
use App\Services\Chatbot\InputGuardService;
use App\Services\Chatbot\ResponseValidatorService;
use App\Services\Chatbot\UnifiedAiPolicyService;
use App\Services\Chatbot\ValidationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class AiConversationServiceFallbackTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(AiSuggestionService $suggestionService, ChatbotQualityMetricsService $metrics): AiConversationService
    {
        $memoryService = Mockery::mock(ConversationMemoryService::class);
        $memoryService->shouldReceive('appendMessage')->andReturnNull();

        $inputGuard = Mockery::mock(InputGuardService::class);
        $inputGuard->shouldReceive('inspect')
            ->andReturnUsing(fn (string $content): InputGuardResult => new InputGuardResult(true, trim($content), null, null));

        $responseValidator = Mockery::mock(ResponseValidatorService::class);
        $responseValidator->shouldReceive('validateAll')
            ->andReturnUsing(fn (): ValidationResult => ValidationResult::pass());

        return new AiConversationService(
            $suggestionService,
            new UnifiedAiPolicyService(),
            $metrics,
            $memoryService,
            $inputGuard,
            $responseValidator,
            Mockery::mock(CarouselBuilderService::class),
            Mockery::mock(HybridSearchService::class),
            Mockery::mock(MetaApiService::class),
            Mockery::mock(WhatsAppService::class),
            new ChatbotFallbackStrategyService(new UnifiedAiPolicyService(), $responseValidator),
            new ChatbotProductSelectionService()
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGenerateResponseUsesGeorgianFallbackForTransliteratedCustomerMessage(): void
    {
        $customer = Customer::create([
            'name' => 'Test User',
            'platform_user_ids' => ['messenger' => 'user_1'],
        ]);

        $conversation = Conversation::create([
            'customer_id' => $customer->id,
            'platform' => 'messenger',
            'platform_conversation_id' => 'conv_1',
            'status' => 'active',
            'unread_count' => 0,
            'last_message_at' => now(),
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'sender_type' => 'customer',
            'sender_id' => $customer->id,
            'sender_name' => $customer->name,
            'content' => 'gamarjoba ra ghirs es modeli?',
            'platform_message_id' => 'msg_1',
        ]);

        $suggestionService = Mockery::mock(AiSuggestionService::class);
        $suggestionService
            ->shouldReceive('generateSuggestions')
            ->once()
            ->andReturnUsing(function (): array {
                return ['Hi, how can I assist you today?'];
            });

        $metrics = new ChatbotQualityMetricsService();
        $service = $this->makeService($suggestionService, $metrics);
        $response = $service->generateResponse($conversation);

        $this->assertNotNull($response);
        $this->assertMatchesRegularExpression('/\p{Georgian}/u', $response);
        $this->assertStringContainsString('მოდელი', $response);
    }

    public function testGenerateResponseRecordsProviderIncidentWhenAiReturnsNoSuggestions(): void
    {
        $customer = Customer::create([
            'name' => 'Test User',
            'platform_user_ids' => ['messenger' => 'user_2'],
        ]);

        $conversation = Conversation::create([
            'customer_id' => $customer->id,
            'platform' => 'messenger',
            'platform_conversation_id' => 'conv_2',
            'status' => 'active',
            'unread_count' => 0,
            'last_message_at' => now(),
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'sender_type' => 'customer',
            'sender_id' => $customer->id,
            'sender_name' => $customer->name,
            'content' => 'რამე მირჩიე',
            'platform_message_id' => 'msg_2',
        ]);

        $suggestionService = Mockery::mock(AiSuggestionService::class);
        $suggestionService
            ->shouldReceive('generateSuggestions')
            ->once()
            ->andReturn([]);

        $metrics = new ChatbotQualityMetricsService();
        $service = $this->makeService($suggestionService, $metrics);
        $response = $service->generateResponse($conversation);

        $date = now()->toDateString();

        $this->assertSame('ბოდიში, სერვისი დროებით მიუწვდომელია.', $response);
        $this->assertSame(1, Cache::get("chatbot_quality:{$date}:omnichannel_provider_incident_total"));
        $this->assertSame(1, Cache::get("chatbot_quality:{$date}:omnichannel_provider_incident_no_suggestions_total"));
    }

    public function testGenerateResponseRegeneratesOnceAfterValidatorFailure(): void
    {
        $customer = Customer::create([
            'name' => 'Test User',
            'platform_user_ids' => ['messenger' => 'user_3'],
        ]);

        $conversation = Conversation::create([
            'customer_id' => $customer->id,
            'platform' => 'messenger',
            'platform_conversation_id' => 'conv_3',
            'status' => 'active',
            'unread_count' => 0,
            'last_message_at' => now(),
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'sender_type' => 'customer',
            'sender_id' => $customer->id,
            'sender_name' => $customer->name,
            'content' => 'MyTechnic Ultra რა ღირს?',
            'platform_message_id' => 'msg_3',
        ]);

        $suggestionService = Mockery::mock(AiSuggestionService::class);
        $regenerationCall = 0;
        $suggestionService
            ->shouldReceive('generateSuggestions')
            ->twice()
            ->andReturnUsing(function () use (&$regenerationCall): array {
                $regenerationCall++;

                return $regenerationCall === 1
                    ? ['ეს მოდელი ღირს 200 ₾.']
                    : ['MyTechnic Ultra ღირს 79 ₾.'];
            });

        $memoryService = Mockery::mock(ConversationMemoryService::class);
        $memoryService->shouldReceive('appendMessage')->andReturnNull();

        $inputGuard = Mockery::mock(InputGuardService::class);
        $inputGuard->shouldReceive('inspect')
            ->andReturnUsing(fn (string $content): InputGuardResult => new InputGuardResult(true, trim($content), null, null));

        $responseValidator = Mockery::mock(ResponseValidatorService::class);
        $responseValidator->shouldReceive('validateAll')
            ->twice()
            ->andReturn(
                ValidationResult::fail([['type' => 'price_mismatch', 'price' => 200.0]]),
                ValidationResult::pass()
            );

        $metrics = new ChatbotQualityMetricsService();

        $service = new AiConversationService(
            $suggestionService,
            new UnifiedAiPolicyService(),
            $metrics,
            $memoryService,
            $inputGuard,
            $responseValidator,
            Mockery::mock(CarouselBuilderService::class),
            Mockery::mock(HybridSearchService::class),
            Mockery::mock(MetaApiService::class),
            Mockery::mock(WhatsAppService::class),
            new ChatbotFallbackStrategyService(new UnifiedAiPolicyService(), $responseValidator),
            new ChatbotProductSelectionService()
        );

        $response = $service->generateResponse($conversation);

        $this->assertSame('MyTechnic Ultra ღირს 79 ₾.', $response);
    }

    public function testAutoReplyPersistsFallbackAndRegenerationMetadataOnOutgoingMessage(): void
    {
        $customer = Customer::create([
            'name' => 'Test User',
            'platform_user_ids' => ['whatsapp' => 'user_4'],
        ]);

        $conversation = Conversation::create([
            'customer_id' => $customer->id,
            'platform' => 'whatsapp',
            'platform_conversation_id' => 'conv_4',
            'status' => 'active',
            'unread_count' => 0,
            'last_message_at' => now(),
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'sender_type' => 'customer',
            'sender_id' => $customer->id,
            'sender_name' => $customer->name,
            'content' => 'ამ მოდელის ზუსტი ფასი მითხარი',
            'platform_message_id' => 'msg_4',
        ]);

        $suggestionService = Mockery::mock(AiSuggestionService::class);
        $regenerationCall = 0;
        $suggestionService
            ->shouldReceive('generateSuggestions')
            ->twice()
            ->andReturnUsing(function () use (&$regenerationCall): array {
                $regenerationCall++;

                return $regenerationCall === 1
                    ? ['ეს მოდელი ღირს 200 ₾.']
                    : ['MyTechnic Ultra ღირს 79 ₾.'];
            });

        $memoryService = Mockery::mock(ConversationMemoryService::class);
        $memoryService->shouldReceive('appendMessage')->andReturnNull();

        $inputGuard = Mockery::mock(InputGuardService::class);
        $inputGuard->shouldReceive('inspect')
            ->andReturnUsing(fn (string $content): InputGuardResult => new InputGuardResult(true, trim($content), null, null));

        $responseValidator = Mockery::mock(ResponseValidatorService::class);
        $responseValidator->shouldReceive('validateAll')
            ->twice()
            ->andReturn(
                ValidationResult::fail([['type' => 'price_mismatch', 'price' => 200.0]]),
                ValidationResult::pass()
            );

        $carouselBuilder = Mockery::mock(CarouselBuilderService::class);
        $carouselBuilder->shouldReceive('productsFromMatches')->never();

        $hybridSearch = Mockery::mock(HybridSearchService::class);
        $hybridSearch->shouldReceive('hybridSearch')->never();

        $metrics = new ChatbotQualityMetricsService();

        $service = new AiConversationService(
            $suggestionService,
            new UnifiedAiPolicyService(),
            $metrics,
            $memoryService,
            $inputGuard,
            $responseValidator,
            $carouselBuilder,
            $hybridSearch,
            Mockery::mock(MetaApiService::class),
            Mockery::mock(WhatsAppService::class),
            new ChatbotFallbackStrategyService(new UnifiedAiPolicyService(), $responseValidator),
            new ChatbotProductSelectionService()
        );

        $result = $service->autoReply($conversation);

        $this->assertTrue($result);

        $botMessage = Message::query()
            ->where('sender_type', 'admin')
            ->where('conversation_id', $conversation->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('MyTechnic Ultra ღირს 79 ₾.', $botMessage->content);
        $this->assertNull(data_get($botMessage->metadata, 'fallback_reason'));
        $this->assertTrue((bool) data_get($botMessage->metadata, 'validation_passed'));
        $this->assertTrue((bool) data_get($botMessage->metadata, 'georgian_passed'));
        $this->assertTrue((bool) data_get($botMessage->metadata, 'regeneration_attempted'));
        $this->assertTrue((bool) data_get($botMessage->metadata, 'regeneration_succeeded'));
    }

    public function testAutoReplyPersistsNormalizedMetadataForDiscoveryCarousel(): void
    {
        $customer = Customer::create([
            'name' => 'Test User',
            'platform_user_ids' => ['whatsapp' => 'user_5'],
        ]);

        $conversation = Conversation::create([
            'customer_id' => $customer->id,
            'platform' => 'whatsapp',
            'platform_conversation_id' => 'conv_5',
            'status' => 'active',
            'unread_count' => 0,
            'last_message_at' => now(),
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'sender_type' => 'customer',
            'sender_id' => $customer->id,
            'sender_name' => $customer->name,
            'content' => 'რამე მირჩიე',
            'platform_message_id' => 'msg_5',
        ]);

        $memoryService = Mockery::mock(ConversationMemoryService::class);
        $memoryService->shouldReceive('appendMessage')->once()->with($conversation->id, 'assistant', 'გიზიარებთ შესაბამის მოდელებს 👇')->andReturnNull();

        $inputGuard = Mockery::mock(InputGuardService::class);
        $inputGuard->shouldReceive('inspect')->never();

        $responseValidator = Mockery::mock(ResponseValidatorService::class);
        $responseValidator->shouldReceive('validateAll')->never();

        $carouselBuilder = Mockery::mock(CarouselBuilderService::class);
        $carouselBuilder->shouldReceive('productsFromMatches')
            ->once()
            ->andReturn([
                [
                    'title' => 'MyTechnic Ultra',
                    'subtitle' => 'ფასი: 79 ₾',
                    'image_url' => 'https://example.com/ultra.jpg',
                    'product_url' => 'https://example.com/products/mytechnic-ultra',
                    'cta_label' => 'ნახვა',
                    'cart_label' => 'კალათაში',
                ],
                [
                    'title' => 'MyTechnic Pro',
                    'subtitle' => 'ფასი: 99 ₾',
                    'image_url' => 'https://example.com/pro.jpg',
                    'product_url' => 'https://example.com/products/mytechnic-pro',
                    'cta_label' => 'ნახვა',
                    'cart_label' => 'კალათაში',
                ],
            ]);

        $hybridSearch = Mockery::mock(HybridSearchService::class);
        $hybridSearch->shouldReceive('hybridSearch')->once()->andReturn([
            ['score' => 0.95, 'metadata' => ['slug' => 'mytechnic-ultra', 'title' => 'MyTechnic Ultra', 'image_url' => 'https://example.com/ultra.jpg', 'sale_price' => 79]],
            ['score' => 0.9, 'metadata' => ['slug' => 'mytechnic-pro', 'title' => 'MyTechnic Pro', 'image_url' => 'https://example.com/pro.jpg', 'sale_price' => 99]],
        ]);

        $whatsAppService = Mockery::mock(WhatsAppService::class);
        $whatsAppService->shouldReceive('sendCarousel')
            ->once()
            ->with('user_5', 'conv_5', Mockery::type('array'))
            ->andReturn(['success' => true]);

        $suggestionService = Mockery::mock(AiSuggestionService::class);
        $suggestionService->shouldReceive('generateSuggestions')->never();

        $metrics = new ChatbotQualityMetricsService();

        $service = new AiConversationService(
            $suggestionService,
            new UnifiedAiPolicyService(),
            $metrics,
            $memoryService,
            $inputGuard,
            $responseValidator,
            $carouselBuilder,
            $hybridSearch,
            Mockery::mock(MetaApiService::class),
            $whatsAppService,
            new ChatbotFallbackStrategyService(new UnifiedAiPolicyService(), $responseValidator),
            new ChatbotProductSelectionService()
        );

        $result = $service->autoReply($conversation);

        $this->assertTrue($result);

        $botMessage = Message::query()
            ->where('sender_type', 'admin')
            ->where('conversation_id', $conversation->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('გიზიარებთ შესაბამის მოდელებს 👇', $botMessage->content);
        $this->assertSame('carousel', data_get($botMessage->metadata, 'type'));
        $this->assertSame(2, data_get($botMessage->metadata, 'cards_count'));
        $this->assertNull(data_get($botMessage->metadata, 'fallback_reason'));
        $this->assertTrue((bool) data_get($botMessage->metadata, 'validation_passed'));
        $this->assertSame([], data_get($botMessage->metadata, 'validation_violations'));
        $this->assertTrue((bool) data_get($botMessage->metadata, 'georgian_passed'));
        $this->assertFalse((bool) data_get($botMessage->metadata, 'regeneration_attempted'));
        $this->assertFalse((bool) data_get($botMessage->metadata, 'regeneration_succeeded'));
        $this->assertSame(2, data_get($botMessage->metadata, 'products_found'));
        $this->assertSame(2, data_get($botMessage->metadata, 'products_attached'));
        $this->assertFalse((bool) data_get($botMessage->metadata, 'carousel_suppressed'));
    }

    public function testAutoReplySuppressesDiscoveryCarouselForProductSpecificRecommendationQuery(): void
    {
        $customer = Customer::create([
            'name' => 'Test User',
            'platform_user_ids' => ['whatsapp' => 'user_6'],
        ]);

        $conversation = Conversation::create([
            'customer_id' => $customer->id,
            'platform' => 'whatsapp',
            'platform_conversation_id' => 'conv_6',
            'status' => 'active',
            'unread_count' => 0,
            'last_message_at' => now(),
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'sender_type' => 'customer',
            'sender_id' => $customer->id,
            'sender_name' => $customer->name,
            'content' => 'MyTechnic Ultra მირჩიე',
            'platform_message_id' => 'msg_6',
        ]);

        $suggestionService = Mockery::mock(AiSuggestionService::class);
        $suggestionService
            ->shouldReceive('generateSuggestions')
            ->once()
            ->andReturnUsing(fn (): array => ['რომელი კონკრეტული MyTechnic Ultra მოდელი გაინტერესებთ?']);

        $memoryService = Mockery::mock(ConversationMemoryService::class);
        $memoryService->shouldReceive('appendMessage')->times(2)->andReturnNull();

        $inputGuard = Mockery::mock(InputGuardService::class);
        $inputGuard->shouldReceive('inspect')
            ->once()
            ->andReturnUsing(fn (string $content): InputGuardResult => new InputGuardResult(true, trim($content), null, null));

        $responseValidator = Mockery::mock(ResponseValidatorService::class);
        $responseValidator->shouldReceive('validateAll')->once()->andReturn(ValidationResult::pass());

        $carouselBuilder = Mockery::mock(CarouselBuilderService::class);
        $carouselBuilder->shouldReceive('productsFromMatches')
            ->once()
            ->andReturn([
                [
                    'title' => 'MyTechnic Ultra Pro',
                    'subtitle' => 'ფასი: 349 ₾',
                    'image_url' => 'https://example.com/ultra-pro.jpg',
                    'product_url' => 'https://example.com/products/mytechnic-ultra-pro',
                    'cta_label' => 'ნახვა',
                    'cart_label' => 'კალათაში',
                ],
                [
                    'title' => 'MyTechnic Ultra Max',
                    'subtitle' => 'ფასი: 379 ₾',
                    'image_url' => 'https://example.com/ultra-max.jpg',
                    'product_url' => 'https://example.com/products/mytechnic-ultra-max',
                    'cta_label' => 'ნახვა',
                    'cart_label' => 'კალათაში',
                ],
            ]);

        $hybridSearch = Mockery::mock(HybridSearchService::class);
        $hybridSearch->shouldReceive('hybridSearch')->once()->andReturn([
            ['score' => 0.95, 'metadata' => ['slug' => 'mytechnic-ultra-pro', 'title' => 'MyTechnic Ultra Pro', 'image_url' => 'https://example.com/ultra-pro.jpg', 'sale_price' => 349]],
            ['score' => 0.94, 'metadata' => ['slug' => 'mytechnic-ultra-max', 'title' => 'MyTechnic Ultra Max', 'image_url' => 'https://example.com/ultra-max.jpg', 'sale_price' => 379]],
        ]);

        $whatsAppService = Mockery::mock(WhatsAppService::class);
        $whatsAppService->shouldReceive('sendCarousel')->never();

        $metrics = new ChatbotQualityMetricsService();

        $service = new AiConversationService(
            $suggestionService,
            new UnifiedAiPolicyService(),
            $metrics,
            $memoryService,
            $inputGuard,
            $responseValidator,
            $carouselBuilder,
            $hybridSearch,
            Mockery::mock(MetaApiService::class),
            $whatsAppService,
            new ChatbotFallbackStrategyService(new UnifiedAiPolicyService(), $responseValidator),
            new ChatbotProductSelectionService()
        );

        $result = $service->autoReply($conversation);

        $this->assertTrue($result);

        $botMessage = Message::query()
            ->where('sender_type', 'admin')
            ->where('conversation_id', $conversation->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('რომელი კონკრეტული MyTechnic Ultra მოდელი გაინტერესებთ?', $botMessage->content);
        $this->assertSame(2, data_get($botMessage->metadata, 'products_found'));
        $this->assertSame(0, data_get($botMessage->metadata, 'products_attached'));
        $this->assertTrue((bool) data_get($botMessage->metadata, 'carousel_suppressed'));
    }
}
