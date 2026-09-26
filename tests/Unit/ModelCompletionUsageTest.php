<?php

namespace Tests\Unit;

use App\Services\Chatbot\ChatbotOutcomeReason;
use App\Services\Chatbot\ModelCompletionService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ModelCompletionUsageTest extends TestCase
{
    public function testEmptySuccessfulCompletionRetainsBilledUsage(): void
    {
        config()->set('services.openai.key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1');
        config()->set('services.langfuse.enabled', false);

        Http::fake(['*/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => '']]],
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 5],
        ], 200)]);

        $result = app(ModelCompletionService::class)->complete('gpt-4.1-mini', [
            ['role' => 'user', 'content' => 'გამარჯობა'],
        ]);

        $this->assertSame(ChatbotOutcomeReason::EMPTY_MODEL_OUTPUT, $result['reason']);
        $this->assertSame(100, $result['usage']['prompt_tokens']);
        $this->assertSame(5, $result['usage']['completion_tokens']);
        $this->assertGreaterThan(0, $result['estimated_cost_usd']);
    }
}
