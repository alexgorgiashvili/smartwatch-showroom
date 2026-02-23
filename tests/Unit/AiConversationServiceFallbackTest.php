<?php

namespace Tests\Unit;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Services\AiConversationService;
use App\Services\AiSuggestionService;
use App\Services\Chatbot\ChatbotQualityMetricsService;
use App\Services\Chatbot\UnifiedAiPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AiConversationServiceFallbackTest extends TestCase
{
    use RefreshDatabase;

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
        $service = new AiConversationService($suggestionService, new UnifiedAiPolicyService(), $metrics);
        $response = $service->generateResponse($conversation);

        $this->assertNotNull($response);
        $this->assertMatchesRegularExpression('/\p{Georgian}/u', $response);
        $this->assertStringContainsString('მოდელი', $response);
    }
}
