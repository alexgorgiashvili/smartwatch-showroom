<?php

namespace Tests\Unit;

use App\Services\Chatbot\ModelCompletionService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ModelCompletionCompatibilityTest extends TestCase
{
    public function testGptSixChatCompletionsUsesCompatibleTokenAndReasoningParameters(): void
    {
        $method = new ReflectionMethod(ModelCompletionService::class, 'completionPayload');
        $service = new ModelCompletionService();

        $none = $method->invoke($service, 'gpt-6-luna', [], ['max_tokens' => 300, 'temperature' => 0.5]);
        $this->assertSame('none', $none['reasoning_effort']);
        $this->assertSame(300, $none['max_completion_tokens']);
        $this->assertSame(0.5, $none['temperature']);
        $this->assertArrayNotHasKey('max_tokens', $none);

        $medium = $method->invoke($service, 'gpt-6-luna', [], ['reasoning_effort' => 'medium']);
        $this->assertArrayNotHasKey('temperature', $medium);
        $this->assertSame('medium', $medium['reasoning_effort']);

        $baseline = $method->invoke($service, 'gpt-4.1-mini', [], ['max_tokens' => 300]);
        $this->assertSame(300, $baseline['max_tokens']);
        $this->assertArrayNotHasKey('reasoning_effort', $baseline);
    }
}
