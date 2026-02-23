<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\User;
use App\Services\AiConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SelectiveAutoReplyPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testToggleAiSkipsAutoReplyWhenSelectivePolicyRejectsMessage(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'name' => 'Selective Skip Customer',
            'platform_user_ids' => ['messenger' => 'selective_skip_user'],
        ]);

        $conversation = Conversation::create([
            'customer_id' => $customer->id,
            'platform' => 'messenger',
            'platform_conversation_id' => 'conv_selective_skip',
            'status' => 'active',
            'ai_enabled' => false,
            'unread_count' => 0,
            'last_message_at' => now(),
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'sender_type' => 'customer',
            'sender_id' => $customer->id,
            'sender_name' => $customer->name,
            'content' => 'hi',
            'platform_message_id' => 'msg_selective_skip',
        ]);

        $mock = Mockery::mock(AiConversationService::class);
        $mock->shouldReceive('shouldAutoReplyToConversation')->once()->andReturn(false);
        $mock->shouldReceive('autoReply')->never();
        $this->app->instance(AiConversationService::class, $mock);

        $response = $this->actingAs($user)
            ->postJson('/api/conversations/' . $conversation->id . '/toggle-ai');

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'ai_enabled' => true]);
    }

    public function testToggleAiRunsAutoReplyWhenSelectivePolicyApprovesMessage(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'name' => 'Selective Run Customer',
            'platform_user_ids' => ['messenger' => 'selective_run_user'],
        ]);

        $conversation = Conversation::create([
            'customer_id' => $customer->id,
            'platform' => 'messenger',
            'platform_conversation_id' => 'conv_selective_run',
            'status' => 'active',
            'ai_enabled' => false,
            'unread_count' => 0,
            'last_message_at' => now(),
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'sender_type' => 'customer',
            'sender_id' => $customer->id,
            'sender_name' => $customer->name,
            'content' => 'gamarjoba ra ghirs es modeli?',
            'platform_message_id' => 'msg_selective_run',
        ]);

        $mock = Mockery::mock(AiConversationService::class);
        $mock->shouldReceive('shouldAutoReplyToConversation')->once()->andReturn(true);
        $mock->shouldReceive('autoReply')->once()->andReturn(true);
        $this->app->instance(AiConversationService::class, $mock);

        $response = $this->actingAs($user)
            ->postJson('/api/conversations/' . $conversation->id . '/toggle-ai');

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'ai_enabled' => true]);
    }
}
