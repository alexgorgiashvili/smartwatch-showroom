<?php

namespace Tests\Unit;

use App\Services\Chatbot\LlmJudgeService;
use App\Services\Chatbot\ModelCompletionService;
use Mockery;
use Tests\TestCase;

class LlmJudgeServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testOpenAiJudgeUsesModelCompletionService(): void
    {
        config()->set('services.llm_judge_provider', 'openai');
        config()->set('services.openai.judge_model', 'gpt-4.1-mini');

        $modelCompletion = Mockery::mock(ModelCompletionService::class);
        $modelCompletion->shouldReceive('complete')
            ->once()
            ->with(
                'gpt-4.1-mini',
                Mockery::on(function (array $messages): bool {
                    return ($messages[0]['role'] ?? null) === 'system'
                        && ($messages[1]['role'] ?? null) === 'user'
                        && str_contains($messages[1]['content'] ?? '', 'Question:');
                }),
                Mockery::type('array')
            )
            ->andReturn([
                'reply' => '{"accuracy":5,"relevance":4,"georgian_grammar":5,"completeness":4,"safety":5,"overall":4.6,"notes":"კარგი პასუხია"}',
                'reason' => null,
                'usage' => [],
            ]);

        $service = new LlmJudgeService($modelCompletion);

        $result = $service->judge('ფასი რა არის?', 'ზუსტი ფასის თქმა', 'ფასი არის 200 ₾', 'კონტექსტი');

        $this->assertSame(5, $result['accuracy'] ?? null);
        $this->assertSame(4.6, $result['overall'] ?? null);
        $this->assertSame('კარგი პასუხია', $result['notes'] ?? null);
    }
}
