<?php

namespace Tests\Unit;

use App\Models\Conversation;
use App\Models\Customer;
use App\Services\Chatbot\Agents\SupervisorAgent;
use App\Services\Chatbot\BifurcatedMemoryService;
use App\Services\Chatbot\ChatPipelineService;
use App\Services\Chatbot\ChatbotFallbackStrategyService;
use App\Services\Chatbot\IntentAnalyzerService;
use App\Services\Chatbot\IntentResult;
use App\Services\Chatbot\UnifiedAiPolicyService;
use Tests\TestCase;

class ChatPipelineServiceTest extends TestCase
{
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
