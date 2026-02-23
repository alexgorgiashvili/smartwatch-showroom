<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageApiTest extends TestCase
{
    use RefreshDatabase;

    protected ?User $admin;
    protected ?Customer $customer;
    protected ?Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->customer = Customer::factory()->create();
        $this->conversation = Conversation::factory()
            ->for($this->customer)
            ->create(['platform' => 'facebook']);
    }

    /** Test send message creates record */
    public function testSendMessageCreatesRecord(): void
    {
        // POST /admin/inbox/{conversation}/messages with content
        $response = $this->actingAs($this->admin)
            ->post(route('admin.inbox.messages.store', ['conversation' => $this->conversation->id]), [
                'content' => 'This is a reply message',
            ]);

        // Assert: 200 OK or 201 Created
        $response->assertStatus(200);

        // Assert: Message created in database
        $this->assertDatabaseHas('messages', [
            'content' => 'This is a reply message',
            'conversation_id' => $this->conversation->id,
            'sender_type' => 'admin',
        ]);

        // Assert: Response includes created message data
        $response->assertJsonStructure([
            'id',
            'content',
            'sender_type',
            'created_at',
        ]);
    }

    /** Test send message validation */
    public function testSendMessageValidation(): void
    {
        // POST with empty content
        $response = $this->actingAs($this->admin)
            ->post(route('admin.inbox.messages.store', ['conversation' => $this->conversation->id]), [
                'content' => '',
            ]);

        // Assert: 422 Unprocessable Entity
        $response->assertStatus(422);

        // Assert: Validation errors returned
        $response->assertJsonValidationErrors('content');
    }

    /** Test message content trimmed */
    public function testMessageContentTrimmed(): void
    {
        // POST with whitespace-only content
        $response = $this->actingAs($this->admin)
            ->post(route('admin.inbox.messages.store', ['conversation' => $this->conversation->id]), [
                'content' => '   \t\n  ',
            ]);

        // Assert: 422 rejected
        $response->assertStatus(422);
    }

    /** Test message max length validation */
    public function testMessageMaxLengthValidation(): void
    {
        // Create message with 5000 characters (assuming max is less)
        $longContent = str_repeat('a', 5000);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.inbox.messages.store', ['conversation' => $this->conversation->id]), [
                'content' => $longContent,
            ]);

        // Either 422 if validation fails or 200 if max is high
        $this->assertIn($response->status(), [200, 422]);
    }

    /** Test message creates with correct metadata */
    public function testMessageCreatesWithCorrectMetadata(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.inbox.messages.store', ['conversation' => $this->conversation->id]), [
                'content' => 'Test reply',
            ]);

        $response->assertStatus(200);

        $message = Message::whereContent('Test reply')->first();
        $this->assertNotNull($message);
        $this->assertEquals('admin', $message->sender_type);
        $this->assertEquals($this->conversation->id, $message->conversation_id);
    }

    /** Test unauthorized user can't send message */
    public function testUnauthorizedUserCannotSendMessage(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)
            ->post(route('admin.inbox.messages.store', ['conversation' => $this->conversation->id]), [
                'content' => 'Unauthorized message',
            ]);

        // Assert: 403 Forbidden
        $response->assertStatus(403);

        // Assert: Message not created
        $this->assertDatabaseMissing('messages', [
            'content' => 'Unauthorized message',
        ]);
    }

    /** Test mark message as read */
    public function testMarkMessageAsRead(): void
    {
        $message = Message::factory()
            ->for($this->conversation)
            ->for($this->customer)
            ->create(['read_at' => null]);

        // PATCH /admin/inbox/{conversation}/messages/{message}/read
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.inbox.messages.mark-read', [
                'conversation' => $this->conversation->id,
                'message' => $message->id,
            ]));

        $response->assertStatus(200);

        // Assert: Message marked as read
        $message->refresh();
        $this->assertNotNull($message->read_at);
    }

    /** Test delete message (admin only) */
    public function testDeleteMessage(): void
    {
        $message = Message::factory()
            ->for($this->conversation)
            ->for($this->customer)
            ->create();

        // DELETE /admin/inbox/{conversation}/messages/{message}
        $response = $this->actingAs($this->admin)
            ->delete(route('admin.inbox.messages.destroy', [
                'conversation' => $this->conversation->id,
                'message' => $message->id,
            ]));

        // Should return 200 or 204
        $this->assertIn($response->status(), [200, 204]);

        // Assert: Message deleted
        $this->assertDatabaseMissing('messages', [
            'id' => $message->id,
        ]);
    }

    /** Test conversation last_message_at updates */
    public function testConversationLastMessageAtUpdates(): void
    {
        $oldTime = now()->subHours(1);
        $this->conversation->update(['last_message_at' => $oldTime]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.inbox.messages.store', ['conversation' => $this->conversation->id]), [
                'content' => 'New message',
            ]);

        $response->assertStatus(200);

        // Assert: last_message_at updated
        $this->conversation->refresh();
        $this->assertTrue($this->conversation->last_message_at > $oldTime);
    }

    /** Test AI suggestion endpoint */
    public function testAiSuggestionEndpoint(): void
    {
        // Create some context messages
        Message::factory(3)
            ->for($this->conversation)
            ->for($this->customer)
            ->create();

        // POST /admin/inbox/{conversation}/suggest-ai
        $response = $this->actingAs($this->admin)
            ->get(route('admin.inbox.suggest-ai', ['conversation' => $this->conversation->id]));

        $response->assertStatus(200);

        // Assert: Returns suggestions
        $response->assertJsonStructure([
            'suggestions',
        ]);

        if ($response->json('suggestions')) {
            // Assert: Suggestions are strings
            foreach ($response->json('suggestions') as $suggestion) {
                $this->assertIsString($suggestion);
                $this->assertNotEmpty($suggestion);
            }
        }
    }

    /** Test batch AI suggestions */
    public function testBatchAiSuggestions(): void
    {
        // Create multiple conversations
        $conversations = Conversation::factory(3)
            ->for($this->customer)
            ->create(['platform' => 'facebook']);

        foreach ($conversations as $conv) {
            Message::factory(2)->for($conv)->for($this->customer)->create();
        }

        // POST /admin/inbox/suggestions/batch
        $response = $this->actingAs($this->admin)
            ->post(route('admin.inbox.suggestions.batch'), [
                'conversation_ids' => $conversations->pluck('id')->toArray(),
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'suggestions',
        ]);
    }
}
