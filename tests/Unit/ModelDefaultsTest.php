<?php

namespace Tests\Unit;

use Tests\TestCase;

class ModelDefaultsTest extends TestCase
{
    public function testSupervisorModelDefault(): void
    {
        $this->assertSame('gpt-4.1-mini', config('chatbot.supervisor.model'));
    }

    public function testReflectionCritiqueModelDefault(): void
    {
        $this->assertSame('gpt-4.1-mini', config('chatbot.reflection.critique_model'));
    }

    public function testOpenaiMainModelDefault(): void
    {
        $this->assertSame('gpt-4.1-mini', config('services.openai.model'));
    }

    public function testIntentModelDefault(): void
    {
        $this->assertSame('gpt-4.1-nano', config('services.openai.intent_model'));
    }

    public function testMultiAgentContextModelDefault(): void
    {
        $this->assertSame('gpt-4.1-mini', config('services.openai.multi_agent_context_model'));
    }

    public function testMultiAgentResponseModelDefault(): void
    {
        $this->assertSame('gpt-4.1-mini', config('services.openai.multi_agent_response_model'));
    }

    public function testMultiAgentQaModelDefault(): void
    {
        $this->assertSame('gpt-4.1-nano', config('services.openai.multi_agent_qa_model'));
    }

    public function testCohereRerankModelDefault(): void
    {
        $this->assertSame('rerank-multilingual-v3.0', config('services.cohere.model'));
    }
}
