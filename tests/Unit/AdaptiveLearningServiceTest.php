<?php

namespace Tests\Unit;

use App\Models\ChatbotTestResult;
use App\Models\ChatbotTestRun;
use App\Services\Chatbot\AdaptiveLearningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdaptiveLearningServiceTest extends TestCase
{
    use RefreshDatabase;

    public function testLessonsAreFilteredToMatchingIntentAndGenericRows(): void
    {
        config()->set('chatbot-learning.enabled', true);
        config()->set('chatbot-learning.max_lessons', 6);

        $run = ChatbotTestRun::query()->create([
            'status' => 'completed',
            'total_cases' => 3,
            'passed_cases' => 0,
            'failed_cases' => 3,
            'skipped_cases' => 0,
            'triggered_by' => 'test',
        ]);

        ChatbotTestResult::query()->create([
            'test_run_id' => $run->id,
            'case_id' => 'comparison-1',
            'category' => 'comparison',
            'question' => 'Q12 ჯობია თუ CT23?',
            'expected_summary' => 'შედარებისას ორივე მოდელი უნდა განიხილო.',
            'actual_response' => 'მხოლოდ ერთ მოდელს შეეხო.',
            'intent_type' => 'comparison',
            'status' => 'fail',
        ]);

        ChatbotTestResult::query()->create([
            'test_run_id' => $run->id,
            'case_id' => 'generic-1',
            'category' => 'general',
            'question' => 'მომხმარებელს მოკლედ უპასუხე.',
            'expected_summary' => 'პასუხი უნდა იყოს მოკლე და ზუსტი.',
            'actual_response' => 'ზედმეტად გრძელი პასუხი.',
            'intent_type' => 'general',
            'status' => 'fail',
        ]);

        ChatbotTestResult::query()->create([
            'test_run_id' => $run->id,
            'case_id' => 'stock-1',
            'category' => 'stock',
            'question' => 'მარაგშია?',
            'expected_summary' => 'უნდა თქვას მხოლოდ მარაგის სტატუსი.',
            'actual_response' => 'არასწორი მარაგის პასუხი.',
            'intent_type' => 'stock_query',
            'status' => 'fail',
        ]);

        $service = new AdaptiveLearningService();

        $lessons = $service->buildLessonsText('comparison');

        $this->assertStringContainsString('Applies to intent: comparison', $lessons);
        $this->assertStringContainsString('Applies to intent: general', $lessons);
        $this->assertStringNotContainsString('Applies to intent: stock_query', $lessons);
    }
}
