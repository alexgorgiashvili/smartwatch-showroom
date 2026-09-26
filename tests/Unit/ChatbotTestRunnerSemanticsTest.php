<?php

namespace Tests\Unit;

use App\Services\Chatbot\TestRunnerService;
use PHPUnit\Framework\TestCase;

class ChatbotTestRunnerSemanticsTest extends TestCase
{
    public function testIntentOnlyCaseIsExplicitlyUngraded(): void
    {
        $result = (new TestRunnerService())->gradeWithMatchers(
            ['expected' => ['expected_intent' => 'general', 'georgian_only' => true]],
            'გამარჯობა',
            ['validation_passed' => true, 'georgian_passed' => true, 'intent' => ['type' => 'general']]
        );

        $this->assertTrue($result['intent_match']);
        $this->assertNull($result['matcher_pass']);
    }

    public function testGroundedAnswerWithRealPipelineSignalsCanPassMatchers(): void
    {
        $result = (new TestRunnerService())->gradeWithMatchers(
            ['expected' => [
                'expected_intent' => 'price_query',
                'must_contain_any' => ['ლარი'],
                'expected_price' => 199,
                'guardrail_should_pass' => true,
                'georgian_only' => true,
            ]],
            'საათი 199 ლარი ღირს.',
            ['validation_passed' => true, 'georgian_passed' => true, 'intent' => ['type' => 'price_query']]
        );

        $this->assertTrue($result['matcher_pass']);
        $this->assertTrue($result['price_match']);
    }

    public function testLegacyJudgePlaceholderReportsNoScore(): void
    {
        $result = (new TestRunnerService())->gradeWithLlmJudge([], 'გამარჯობა');

        $this->assertNull($result['llm_overall']);
        $this->assertStringContainsString('Ungraded', $result['llm_notes']);
    }
}
