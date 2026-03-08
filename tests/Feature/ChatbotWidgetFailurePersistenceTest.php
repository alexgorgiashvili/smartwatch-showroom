<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Services\Chatbot\ChatPipelineService;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ChatbotWidgetFailurePersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testWidgetPersistsFailureReplyWhenPipelineThrows(): void
    {
        $pipelineMock = Mockery::mock(ChatPipelineService::class);
        $pipelineMock->shouldReceive('process')
            ->once()
            ->andThrow(new \RuntimeException('Simulated pipeline failure'));

        $pushMock = Mockery::mock(PushNotificationService::class);
        $pushMock->shouldReceive('sendToAdmins')
            ->once()
            ->andReturn(false);

        $this->app->instance(ChatPipelineService::class, $pipelineMock);
        $this->app->instance(PushNotificationService::class, $pushMock);

        $response = $this->postJson('/chatbot', [
            'message' => 'MyTechnic Ultra რა ღირს?',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'ბოდიში, დროებით პრობლემა გვაქვს. სცადეთ მოგვიანებით.')
            ->assertJsonPath('debug.fallback_reason', 'runtime_exception')
            ->assertJsonPath('debug.products_found', 0);

        $this->assertDatabaseCount('messages', 2);

        $userMessage = Message::query()->where('sender_type', 'customer')->firstOrFail();
        $botMessage = Message::query()->where('sender_type', 'bot')->firstOrFail();

        $this->assertSame('MyTechnic Ultra რა ღირს?', $userMessage->content);
        $this->assertSame('ბოდიში, დროებით პრობლემა გვაქვს. სცადეთ მოგვიანებით.', $botMessage->content);
        $this->assertTrue((bool) data_get($botMessage->metadata, 'chatbot_failure'));
        $this->assertSame('runtime_exception', data_get($botMessage->metadata, 'fallback_reason'));
        $this->assertSame(\RuntimeException::class, data_get($botMessage->metadata, 'exception_class'));
    }
}
