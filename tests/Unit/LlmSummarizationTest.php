<?php

namespace Tests\Unit;

use App\Services\Chatbot\BifurcatedMemoryService;
use App\Services\Chatbot\ConversationMemoryService;
use App\Services\Chatbot\ModelCompletionService;
use Mockery;
use Tests\TestCase;

class LlmSummarizationTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testLongConversationUsesLlmGeneratedGeorgianSummary(): void
    {
        config()->set('chatbot.memory.session_window', 4);
        config()->set('chatbot.memory.summarization_enabled', true);
        config()->set('chatbot.memory.summarization_model', 'gpt-4.1-nano');

        $history = [];

        for ($i = 1; $i <= 12; $i++) {
            $history[] = [
                'role' => $i % 2 === 0 ? 'assistant' : 'user',
                'content' => $i % 2 === 0
                    ? 'დიახ, ამ ბიუჯეტში რამდენიმე ვარიანტი გვაქვს GPS-ით და ზარის ფუნქციით.'
                    : 'ბავშვისთვის 200 ლარამდე GPS და SOS ფუნქციებიანი საათი მინდა, სასურველია შავი ფერი.',
            ];
        }

        $conversationMemory = Mockery::mock(ConversationMemoryService::class);
        $conversationMemory->shouldReceive('getContext')
            ->once()
            ->with(123)
            ->andReturn(['history' => $history]);

        $modelCompletion = Mockery::mock(ModelCompletionService::class);
        $modelCompletion->shouldReceive('complete')
            ->once()
            ->with(
                'gpt-4.1-nano',
                Mockery::on(function (array $messages): bool {
                    return ($messages[0]['role'] ?? null) === 'system'
                        && str_contains($messages[0]['content'] ?? '', 'შეაჯამე ეს საუბარი 2-3 წინადადებით ქართულად')
                        && ($messages[1]['role'] ?? null) === 'user';
                }),
                Mockery::type('array')
            )
            ->andReturn([
                'reply' => 'მომხმარებელი ეძებს ბავშვისთვის 200 ლარამდე შავ GPS საათს SOS ფუნქციით. მისთვის მნიშვნელოვანია ბიუჯეტი, უსაფრთხოების ფუნქციები და მუქი ფერი.',
                'reason' => null,
                'usage' => [],
            ]);

        $service = new BifurcatedMemoryService($conversationMemory, $modelCompletion);

        $sessionContext = $service->getSessionContext(123);

        $summary = (string) ($sessionContext['summary'] ?? '');

        $this->assertNotSame('', $summary);
        $this->assertMatchesRegularExpression('/\p{Georgian}/u', $summary);
        $this->assertStringNotContainsString('წინა საუბარში განხილული თემები:', $summary);
    }
}
