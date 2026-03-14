<?php

namespace Tests\Feature;

use App\Events\MessageReceived;
use App\Filament\Pages\Inbox;
use App\Livewire\Inbox\ChatPanel;
use App\Livewire\Inbox\ReplyForm;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\User;
use App\Services\AiSuggestionService;
use App\Services\OmnichannelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\TestCase;

class FilamentInboxSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'is_admin' => true,
        ]);
    }

    public function testAdminCanRenderFilamentInboxPage(): void
    {
        $conversation = $this->createConversationWithMessages([
            'customer_name' => 'Smoke Test Customer',
            'preview' => 'Need help choosing a watch',
            'platform' => 'whatsapp',
            'unread_count' => 2,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/inbox');

        $response->assertOk();
        $response->assertSee('Inbox');
        $response->assertSee('Omnichannel conversations');
        $response->assertSee($conversation->customer->name);
        $response->assertSee('Need help choosing a watch');
    }

    public function testChatPanelRendersMessagesAndMarksConversationAsRead(): void
    {
        $conversation = $this->createConversationWithMessages([
            'customer_name' => 'Panel Customer',
            'preview' => 'Is this waterproof?',
            'platform' => 'facebook',
            'unread_count' => 3,
            'with_admin_reply' => true,
        ]);

        $customerMessageIds = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('sender_type', 'customer')
            ->pluck('id');

        Livewire::actingAs($this->admin)
            ->test(ChatPanel::class, ['conversationId' => $conversation->id])
            ->assertSee('Panel Customer')
            ->assertSee('Is this waterproof?')
            ->assertSee('Absolutely, here are the options.')
            ->assertSee('Facebook Messenger');

        $conversation->refresh();

        $this->assertSame(0, $conversation->unread_count);
        $this->assertSame(
            $customerMessageIds->count(),
            Message::query()->whereIn('id', $customerMessageIds)->whereNotNull('read_at')->count()
        );
    }

    public function testAdminPushSubscriptionRoutesStoreAndDeleteRecords(): void
    {
        $endpoint = 'https://example.com/push/endpoint-123';
        $endpointHash = hash('sha256', $endpoint);

        $storeResponse = $this->actingAs($this->admin)->postJson('/admin/push-subscriptions', [
            'endpoint' => $endpoint,
            'expirationTime' => null,
            'contentEncoding' => 'aes128gcm',
            'keys' => [
                'p256dh' => 'public-key-value',
                'auth' => 'auth-token-value',
            ],
        ]);

        $storeResponse
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $this->admin->id,
            'endpoint_hash' => $endpointHash,
            'endpoint' => $endpoint,
        ]);

        $deleteResponse = $this->actingAs($this->admin)->deleteJson('/admin/push-subscriptions', [
            'endpoint' => $endpoint,
        ]);

        $deleteResponse
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('push_subscriptions', [
            'endpoint_hash' => $endpointHash,
        ]);
    }

    public function testInboxNavigationBadgeReflectsUnreadTotal(): void
    {
        $this->createConversationWithMessages([
            'customer_name' => 'Badge One',
            'preview' => 'First unread thread',
            'unread_count' => 2,
        ]);

        $this->createConversationWithMessages([
            'customer_name' => 'Badge Two',
            'preview' => 'Second unread thread',
            'platform' => 'instagram',
            'unread_count' => 5,
        ]);

        $this->assertSame('7', Inbox::getNavigationBadge());
        $this->assertSame('warning', Inbox::getNavigationBadgeColor());
    }

    public function testReplyFormCanGenerateAiSuggestionsAndUseSelectedSuggestion(): void
    {
        $conversation = $this->createConversationWithMessages([
            'customer_name' => 'Suggestion Customer',
            'preview' => 'Which model has GPS and calling?',
            'platform' => 'whatsapp',
        ]);

        $this->mock(AiSuggestionService::class, function (MockInterface $mock) use ($conversation): void {
            $mock->shouldReceive('generateSuggestions')
                ->once()
                ->withArgs(function (Conversation $passedConversation, Message $passedMessage, int $limit) use ($conversation): bool {
                    return $passedConversation->id === $conversation->id
                        && $passedMessage->content === 'Which model has GPS and calling?'
                        && $limit === 3;
                })
                ->andReturnUsing(static fn (): array => [
                    'KT24 has GPS, calling, and strong battery life.',
                    'For GPS and calling, I would start with KT24 or KT21 depending on your budget.',
                ]);
        });

        Livewire::actingAs($this->admin)
            ->test(ReplyForm::class, ['conversationId' => $conversation->id])
            ->call('suggestAi')
            ->assertSet('suggestions', [
                'KT24 has GPS, calling, and strong battery life.',
                'For GPS and calling, I would start with KT24 or KT21 depending on your budget.',
            ])
            ->assertSee('AI suggestions')
            ->assertSee('KT24 has GPS, calling, and strong battery life.')
            ->call('useSuggestion', 1)
            ->assertSet('message', 'For GPS and calling, I would start with KT24 or KT21 depending on your budget.');
    }

    public function testReplyFormSendMessageDispatchesRealtimeEventAndClearsDraftState(): void
    {
        Event::fake([MessageReceived::class]);

        $conversation = $this->createConversationWithMessages([
            'customer_name' => 'Realtime Customer',
            'preview' => 'Can you confirm delivery time?',
            'platform' => 'facebook',
        ]);

        $replyMessage = Message::query()->create([
            'conversation_id' => $conversation->id,
            'customer_id' => $conversation->customer_id,
            'sender_type' => 'admin',
            'sender_id' => $this->admin->id,
            'sender_name' => $this->admin->name,
            'content' => 'Yes, delivery is available tomorrow.',
            'read_at' => now(),
        ]);

        $this->mock(OmnichannelService::class, function (MockInterface $mock) use ($conversation, $replyMessage): void {
            $mock->shouldReceive('sendReply')
                ->once()
                ->with($conversation->id, $this->admin->id, 'Yes, delivery is available tomorrow.')
                ->andReturn($replyMessage);
        });

        Livewire::actingAs($this->admin)
            ->test(ReplyForm::class, ['conversationId' => $conversation->id])
            ->set('suggestions', ['Draft to clear'])
            ->set('message', 'Yes, delivery is available tomorrow.')
            ->call('sendMessage')
            ->assertSet('message', '')
            ->assertSet('suggestions', [])
            ->assertDispatched('message-sent')
            ->assertDispatched('conversation-updated');

        Event::assertDispatched(MessageReceived::class, function (MessageReceived $event) use ($conversation, $replyMessage): bool {
            return $event->message->id === $replyMessage->id
                && $event->conversation->id === $conversation->id
                && $event->customer->id === $conversation->customer_id
                && $event->platform === 'facebook';
        });
    }

    public function testPushSubscriptionTestEndpointTargetsFilamentInbox(): void
    {
        $this->mock(\App\Services\PushNotificationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendToUser')
                ->once()
                ->with(
                    $this->admin->id,
                    'Inbox Test',
                    'Push notifications are configured correctly.',
                    route('filament.admin.pages.inbox'),
                    ['type' => 'test']
                )
                ->andReturn(true);
        });

        $response = $this->actingAs($this->admin)->postJson('/admin/push-subscriptions/test');

        $response
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function testConversationListDispatchesBrowserNotificationOnIncomingCustomerMessageEvent(): void
    {
        $conversation = $this->createConversationWithMessages([
            'customer_name' => 'Notification Customer',
            'preview' => 'Please help with GPS setup',
            'platform' => 'instagram',
        ]);

        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Inbox\ConversationList::class)
            ->call('handleIncomingMessage', [
                'message' => [
                    'sender_type' => 'customer',
                    'content' => 'Can you guide me step by step?',
                ],
                'customer' => [
                    'name' => $conversation->customer->name,
                ],
                'conversation' => [
                    'id' => $conversation->id,
                ],
            ])
            ->assertDispatched('inbox-browser-notification');
    }

    public function testChatPanelIncomingMessageMarksConversationAsReadWithoutPollingRefresh(): void
    {
        $conversation = $this->createConversationWithMessages([
            'customer_name' => 'Realtime Inbox Customer',
            'preview' => 'Do you have pink color?',
            'platform' => 'whatsapp',
            'unread_count' => 2,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ChatPanel::class, ['conversationId' => null])
            ->set('conversationId', $conversation->id)
            ->call('handleIncomingMessage', [
                'conversation' => ['id' => $conversation->id],
                'message' => ['sender_type' => 'customer'],
            ])
            ->assertDispatched('conversation-updated', conversationId: $conversation->id);

        $conversation->refresh();

        $this->assertSame(0, $conversation->unread_count);
    }

    public function testReplyFormGeneratesSuggestionsUsingOpenAiHttpResponseFlow(): void
    {
        config()->set('services.openai.key', 'test-openai-key');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1');
        config()->set('services.openai.model', 'gpt-4.1-mini');

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => "1. შეგვიძლია გირჩიოთ GPS და ზარის მხარდაჭერიანი მოდელები.\n2. თუ ბიუჯეტს მეტყვით, ზუსტ ვარიანტებს დაგისახელებთ.\n3. სურვილის შემთხვევაში შევადარებთ ორ მოდელსაც.",
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 120,
                    'completion_tokens' => 60,
                    'total_tokens' => 180,
                ],
            ], 200),
        ]);

        $conversation = $this->createConversationWithMessages([
            'customer_name' => 'Live Suggestion Customer',
            'preview' => 'GPS და ზარების ფუნქცია მინდა',
            'platform' => 'facebook',
        ]);

        Livewire::actingAs($this->admin)
            ->test(ReplyForm::class, ['conversationId' => $conversation->id])
            ->call('suggestAi')
            ->assertSet('suggestions.0', 'შეგვიძლია გირჩიოთ GPS და ზარის მხარდაჭერიანი მოდელები.')
            ->assertSet('suggestions.1', 'თუ ბიუჯეტს მეტყვით, ზუსტ ვარიანტებს დაგისახელებთ.')
            ->assertSet('suggestions.2', 'სურვილის შემთხვევაში შევადარებთ ორ მოდელსაც.');
    }

    private function createConversationWithMessages(array $overrides = []): Conversation
    {
        $customer = Customer::query()->create([
            'name' => $overrides['customer_name'] ?? 'Inbox Customer',
            'platform_user_ids' => [
                ($overrides['platform'] ?? 'whatsapp') => 'user-' . uniqid(),
            ],
            'email' => uniqid('inbox-', true) . '@example.test',
        ]);

        $conversation = Conversation::query()->create([
            'customer_id' => $customer->id,
            'platform' => $overrides['platform'] ?? 'whatsapp',
            'platform_conversation_id' => 'conv-' . uniqid(),
            'subject' => 'Smoke Test Conversation',
            'status' => 'active',
            'ai_enabled' => false,
            'unread_count' => $overrides['unread_count'] ?? 1,
            'last_message_at' => now(),
        ]);

        Message::query()->create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'sender_type' => 'customer',
            'sender_id' => $customer->id,
            'sender_name' => $customer->name,
            'content' => $overrides['preview'] ?? 'Hello from the customer',
            'created_at' => now()->subMinutes(2),
            'updated_at' => now()->subMinutes(2),
        ]);

        if (($overrides['with_admin_reply'] ?? false) === true) {
            Message::query()->create([
                'conversation_id' => $conversation->id,
                'customer_id' => $customer->id,
                'sender_type' => 'admin',
                'sender_id' => $this->admin->id,
                'sender_name' => $this->admin->name,
                'content' => 'Absolutely, here are the options.',
                'read_at' => now(),
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ]);

            $conversation->update([
                'last_message_at' => now()->subMinute(),
            ]);
        }

        return $conversation->fresh(['customer', 'latestMessage']);
    }
}
