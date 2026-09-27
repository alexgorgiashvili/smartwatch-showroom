<?php

namespace Tests\Unit;

use App\Models\Conversation;
use App\Models\Customer;
use App\Services\Chatbot\Agents\SupervisorAgent;
use App\Services\Chatbot\BifurcatedMemoryService;
use App\Services\Chatbot\ChatPipelineService;
use App\Services\Chatbot\ChatbotFallbackStrategyService;
use App\Services\Chatbot\ChatbotFallbackResolution;
use App\Services\Chatbot\IntentAnalyzerService;
use App\Services\Chatbot\IntentResult;
use App\Services\Chatbot\UnifiedAiPolicyService;
use Tests\TestCase;

class ChatPipelineServiceTest extends TestCase
{
    public function testUnresolvedFirstTurnFollowupsAskForTheMissingReferentWithoutModelCalls(): void
    {
        $conversation = new Conversation();
        $conversation->id = 101;
        $customer = new Customer();
        $customer->id = 202;
        $memory = $this->createMock(BifurcatedMemoryService::class);
        $memory->method('getSessionContext')->willReturn(['recent' => [], 'summary' => null]);
        $memory->expects($this->exactly(8))->method('appendMessage');
        $intentAnalyzer = $this->createMock(IntentAnalyzerService::class);
        $intentAnalyzer->expects($this->never())->method('analyze');
        $supervisor = $this->createMock(SupervisorAgent::class);
        $supervisor->expects($this->never())->method('orchestrate');
        $policy = $this->createMock(UnifiedAiPolicyService::class);
        $policy->method('isGreetingOnly')->willReturn(false);
        $fallbackStrategy = $this->createMock(ChatbotFallbackStrategyService::class);

        $service = new ChatPipelineService();
        $recommendation = $service->process('რომელს მირჩევ?', $conversation, $customer, null, $memory, $intentAnalyzer, $supervisor, $policy, $fallbackStrategy);
        $priceFollowup = $service->process('79 ლარიანშიც?', $conversation, $customer, null, $memory, $intentAnalyzer, $supervisor, $policy, $fallbackStrategy);
        $simFollowup = $service->process('სიმ ბარათი იდება?', $conversation, $customer, null, $memory, $intentAnalyzer, $supervisor, $policy, $fallbackStrategy);
        $colorFollowup = $service->process('79 ლარიანი მხოლოდ შავია?', $conversation, $customer, null, $memory, $intentAnalyzer, $supervisor, $policy, $fallbackStrategy);

        $this->assertStringContainsString('რა ბიუჯეტი', $recommendation->response());
        $this->assertStringContainsString('რომელ ფუნქციას', $priceFollowup->response());
        $this->assertStringContainsString('რომელი საათის მოდელზე', $simFollowup->response());
        $this->assertStringContainsString('რომელი მოდელის ფერი', $colorFollowup->response());
        $this->assertNull($simFollowup->fallbackReason());
    }

    public function testForeignLanguageCatalogReplyUsesRelevantGeorgianFallback(): void
    {
        $message = 'რა 2G მოდელები გაქვთ?';
        $intent = IntentResult::fromArray([
            'standalone_query' => $message,
            'intent' => 'recommendation',
            'entities' => [],
            'needs_product_data' => true,
        ], 0);
        $this->assertTrue($intent->hasCatalogFacet());
        $conversation = new Conversation();
        $conversation->id = 101;
        $customer = new Customer();
        $customer->id = 202;
        $memory = $this->createMock(BifurcatedMemoryService::class);
        $memory->method('getSessionContext')->willReturn(['recent' => []]);
        $memory->method('getUserPreferences')->willReturn([]);
        $memory->method('scopePreferencesForMessage')->willReturn([]);
        $memory->expects($this->exactly(2))->method('appendMessage');
        $intentAnalyzer = $this->createMock(IntentAnalyzerService::class);
        $intentAnalyzer->method('analyze')->willReturn($intent);
        $supervisor = $this->createMock(SupervisorAgent::class);
        $supervisor->method('orchestrate')->willReturn([
            'success' => true,
            'response' => 'Հայերեն პასუხი',
            'validation_passed' => true,
            'validation_context' => ['products' => [['id' => 1]]],
            'violations' => [], 'reflection_attempts' => 0,
            'reason' => null, 'extracted_preferences' => [],
        ]);
        $policy = $this->createMock(UnifiedAiPolicyService::class);
        $policy->method('isGreetingOnly')->willReturn(false);
        $policy->method('passesStrictGeorgianQa')->willReturn(false);
        $fallbackStrategy = $this->createMock(ChatbotFallbackStrategyService::class);
        $fallbackStrategy->expects($this->once())->method('resolveProviderFailureOutcome')
            ->willReturn(new ChatbotFallbackResolution('2G მოდელები მარაგშია.', null, true, [], true));

        $result = (new ChatPipelineService())->process(
            $message, $conversation, $customer, null,
            $memory, $intentAnalyzer, $supervisor, $policy, $fallbackStrategy
        );

        $this->assertSame('2G მოდელები მარაგშია.', $result->response());
    }

    public function testForeignLanguageWaterAnswerUsesVerifiedCatalogField(): void
    {
        $message = 'რომელი მოდელია წყალგამძლე?';
        $intent = IntentResult::fromArray([
            'standalone_query' => $message, 'intent' => 'features',
            'entities' => [], 'needs_product_data' => true,
        ], 0);
        $conversation = new Conversation();
        $conversation->id = 101;
        $customer = new Customer();
        $customer->id = 202;
        $memory = $this->createMock(BifurcatedMemoryService::class);
        $memory->method('getSessionContext')->willReturn(['recent' => []]);
        $memory->method('getUserPreferences')->willReturn([]);
        $memory->method('scopePreferencesForMessage')->willReturn([]);
        $memory->expects($this->exactly(2))->method('appendMessage');
        $intentAnalyzer = $this->createMock(IntentAnalyzerService::class);
        $intentAnalyzer->method('analyze')->willReturn($intent);
        $supervisor = $this->createMock(SupervisorAgent::class);
        $supervisor->method('orchestrate')->willReturn([
            'success' => true, 'response' => 'Հայերեն პასუხი',
            'validation_passed' => true,
            'validation_context' => ['products' => [
                ['name' => 'Q12', 'water_resistant' => 'IP67'],
                ['name' => 'Unknown', 'water_resistant' => ''],
            ]],
            'violations' => [], 'reflection_attempts' => 0,
            'reason' => null, 'extracted_preferences' => [],
        ]);
        $policy = $this->createMock(UnifiedAiPolicyService::class);
        $policy->method('isGreetingOnly')->willReturn(false);
        $policy->method('passesStrictGeorgianQa')->willReturn(false);
        $fallbackStrategy = $this->createMock(ChatbotFallbackStrategyService::class);
        $fallbackStrategy->expects($this->never())->method('resolveProviderFailureOutcome');

        $result = (new ChatPipelineService())->process(
            $message, $conversation, $customer, null,
            $memory, $intentAnalyzer, $supervisor, $policy, $fallbackStrategy
        );

        $this->assertStringContainsString('Q12', $result->response());
        $this->assertStringContainsString('IP67', $result->response());
        $this->assertStringNotContainsString('Unknown', $result->response());
    }

    public function testForeignLanguageCheapestAnswerUsesVerifiedInStockPrices(): void
    {
        $message = 'რომელი მოდელია ყველაზე იაფი?';
        $intent = IntentResult::fromArray([
            'standalone_query' => $message, 'intent' => 'recommendation',
            'entities' => [], 'needs_product_data' => true,
        ], 0);
        $conversation = new Conversation();
        $conversation->id = 101;
        $customer = new Customer();
        $customer->id = 202;
        $memory = $this->createMock(BifurcatedMemoryService::class);
        $memory->method('getSessionContext')->willReturn(['recent' => []]);
        $memory->method('getUserPreferences')->willReturn([]);
        $memory->method('scopePreferencesForMessage')->willReturn([]);
        $memory->expects($this->exactly(2))->method('appendMessage');
        $intentAnalyzer = $this->createMock(IntentAnalyzerService::class);
        $intentAnalyzer->method('analyze')->willReturn($intent);
        $supervisor = $this->createMock(SupervisorAgent::class);
        $supervisor->method('orchestrate')->willReturn([
            'success' => true, 'response' => 'Հայերեն პასუხი',
            'validation_passed' => true,
            'validation_context' => ['products' => [
                ['name' => 'Q21', 'price' => 79, 'sale_price' => 59, 'is_in_stock' => true],
                ['name' => 'Q12', 'price' => 79, 'sale_price' => null, 'is_in_stock' => true],
                ['name' => 'Unavailable', 'price' => 49, 'sale_price' => null, 'is_in_stock' => false],
            ]],
            'violations' => [], 'reflection_attempts' => 0,
            'reason' => null, 'extracted_preferences' => [],
        ]);
        $policy = $this->createMock(UnifiedAiPolicyService::class);
        $policy->method('isGreetingOnly')->willReturn(false);
        $policy->method('passesStrictGeorgianQa')->willReturn(false);
        $fallbackStrategy = $this->createMock(ChatbotFallbackStrategyService::class);
        $fallbackStrategy->expects($this->never())->method('resolveProviderFailureOutcome');

        $result = (new ChatPipelineService())->process(
            $message, $conversation, $customer, null,
            $memory, $intentAnalyzer, $supervisor, $policy, $fallbackStrategy
        );

        $this->assertStringContainsString('Q21', $result->response());
        $this->assertStringContainsString('59 ₾', $result->response());
        $this->assertStringNotContainsString('Unavailable', $result->response());
    }

    public function testRejectedAdvertisementPriceGetsARelevantSafeReply(): void
    {
        $message = 'რეკლამაში 55 ლარიანი რატომ გაქვთ?';
        $intent = IntentResult::fromArray([
            'standalone_query' => $message, 'intent' => 'price_query',
            'entities' => [], 'needs_product_data' => true,
        ], 0);
        $conversation = new Conversation();
        $conversation->id = 101;
        $customer = new Customer();
        $customer->id = 202;
        $memory = $this->createMock(BifurcatedMemoryService::class);
        $memory->method('getSessionContext')->willReturn(['recent' => []]);
        $memory->method('getUserPreferences')->willReturn([]);
        $memory->method('scopePreferencesForMessage')->willReturn([]);
        $intentAnalyzer = $this->createMock(IntentAnalyzerService::class);
        $intentAnalyzer->method('analyze')->willReturn($intent);
        $supervisor = $this->createMock(SupervisorAgent::class);
        $supervisor->method('orchestrate')->willReturn([
            'success' => false, 'response' => 'ზუსტი ფასი და მარაგი გადასამოწმებელია.',
            'validation_passed' => false, 'validation_context' => ['products' => []],
            'violations' => [['type' => 'price_without_live_catalog']],
            'reflection_attempts' => 0, 'reason' => 'validator_failed',
            'extracted_preferences' => [],
        ]);
        $policy = $this->createMock(UnifiedAiPolicyService::class);
        $policy->method('isGreetingOnly')->willReturn(false);
        $policy->method('passesStrictGeorgianQa')->willReturn(true);
        $fallbackStrategy = $this->createMock(ChatbotFallbackStrategyService::class);

        $result = (new ChatPipelineService())->process(
            $message, $conversation, $customer, null,
            $memory, $intentAnalyzer, $supervisor, $policy, $fallbackStrategy
        );

        $this->assertStringContainsString('რეკლამის ბმული', $result->response());
        $this->assertStringNotContainsString('მარაგი', $result->response());
    }

    public function testStandaloneMessageUsesScopedPreferencesForIntentAndSupervisor(): void
    {
        $service = new ChatPipelineService();

        $conversation = new Conversation();
        $conversation->id = 101;

        $customer = new Customer();
        $customer->id = 202;

        $memory = $this->createMock(BifurcatedMemoryService::class);
        $intentAnalyzer = $this->createMock(IntentAnalyzerService::class);
        $supervisor = $this->createMock(SupervisorAgent::class);
        $policy = $this->createMock(UnifiedAiPolicyService::class);
        $fallbackStrategy = $this->createMock(ChatbotFallbackStrategyService::class);

        $storedPreferences = [
            'budget_max_gel' => 100,
            'color' => 'blue',
            'features' => ['gps'],
        ];

        $scopedPreferences = [];
        $message = 'მხოლოდ GPS მინდა';
        $intentResult = IntentResult::fallback($message);

        $policy->expects($this->once())
            ->method('isGreetingOnly')
            ->with($message)
            ->willReturn(false);

        $memory->expects($this->once())
            ->method('getSessionContext')
            ->with(101)
            ->willReturn(['recent' => []]);

        $memory->expects($this->once())
            ->method('getUserPreferences')
            ->with(202)
            ->willReturn($storedPreferences);

        $memory->expects($this->once())
            ->method('scopePreferencesForMessage')
            ->with($storedPreferences, $message)
            ->willReturn($scopedPreferences);

        $intentAnalyzer->expects($this->once())
            ->method('analyze')
            ->with($message, [], $scopedPreferences, [
                'conversation_id' => 101,
                'customer_id' => 202,
                'channel' => 'widget',
            ])
            ->willReturn($intentResult);

        $memory->expects($this->exactly(2))
            ->method('appendMessage')
            ->withAnyParameters();

        $supervisor->expects($this->once())
            ->method('orchestrate')
            ->with($message, 101, 202, $intentResult, $scopedPreferences, [
                'conversation_id' => 101,
                'customer_id' => 202,
                'channel' => 'widget',
            ])
            ->willReturn([
                'success' => true,
                'response' => 'ტესტური პასუხი',
                'validation_passed' => true,
                'validation_context' => ['products' => []],
                'violations' => [],
                'reflection_attempts' => 0,
                'reason' => null,
                'extracted_preferences' => [],
            ]);

        $policy->expects($this->once())
            ->method('passesStrictGeorgianQa')
            ->with('ტესტური პასუხი')
            ->willReturn(true);

        $result = $service->process(
            $message,
            $conversation,
            $customer,
            null,
            $memory,
            $intentAnalyzer,
            $supervisor,
            $policy,
            $fallbackStrategy
        );

        $this->assertSame('ტესტური პასუხი', $result->response());
    }
}
