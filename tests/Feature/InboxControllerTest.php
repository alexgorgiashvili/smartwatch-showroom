<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboxControllerTest extends TestCase
{
    use RefreshDatabase;

    protected ?User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    /** Test index returns conversations */
    public function testIndexReturnsConversations(): void
    {
        // Create conversations
        $customers = Customer::factory(3)->create();
        foreach ($customers as $customer) {
            Conversation::factory()
                ->for($customer)
                ->create(['platform' => 'facebook']);
        }

        // GET /admin/inbox
        $response = $this->actingAs($this->admin)->get(route('admin.inbox.index'));

        // Assert: 200 OK
        $response->assertStatus(200);

        // Assert: View contains conversations
        $response->assertViewHas('conversations');
        $this->assertEquals(3, $response->viewData('conversations')->count());
    }

    /** Test index filters by status */
    public function testIndexFiltersByStatus(): void
    {
        $customer = Customer::factory()->create();

        // Create conversations with different statuses
        Conversation::factory()->for($customer)->create(['status' => 'active', 'platform' => 'facebook']);
        Conversation::factory()->for($customer)->create(['status' => 'archived', 'platform' => 'facebook']);
        Conversation::factory()->for($customer)->create(['status' => 'closed', 'platform' => 'facebook']);

        // GET /admin/inbox?status=archived
        $response = $this->actingAs($this->admin)->get(route('admin.inbox.index', ['status' => 'archived']));

        // Assert: Only archived conversations returned
        $response->assertStatus(200);
        $conversations = $response->viewData('conversations');
        $this->assertEquals(1, $conversations->count());
        $this->assertEquals('archived', $conversations->first()->status);
    }

    /** Test index filters by platform */
    public function testIndexFiltersByPlatform(): void
    {
        $customer = Customer::factory()->create();

        Conversation::factory()->for($customer)->create(['platform' => 'facebook']);
        Conversation::factory()->for($customer)->create(['platform' => 'instagram']);
        Conversation::factory()->for($customer)->create(['platform' => 'whatsapp']);

        // GET /admin/inbox?platform=whatsapp
        $response = $this->actingAs($this->admin)->get(route('admin.inbox.index', ['platform' => 'whatsapp']));

        // Assert: Only WhatsApp conversations returned
        $response->assertStatus(200);
        $conversations = $response->viewData('conversations');
        $this->assertEquals(1, $conversations->count());
        $this->assertEquals('whatsapp', $conversations->first()->platform);
    }

    /** Test index filters by unread */
    public function testIndexFiltersByUnread(): void
    {
        $customer = Customer::factory()->create();

        $unreadConversation = Conversation::factory()
            ->for($customer)
            ->create(['platform' => 'facebook', 'unread_count' => 5]);

        $readConversation = Conversation::factory()
            ->for($customer)
            ->create(['platform' => 'facebook', 'unread_count' => 0]);

        // GET /admin/inbox?unread=true
        $response = $this->actingAs($this->admin)->get(route('admin.inbox.index', ['unread' => 'true']));

        // Assert: Only unread conversations returned
        $response->assertStatus(200);
        $conversations = $response->viewData('conversations');
        $this->assertEquals(1, $conversations->count());
    }

    /** Test show conversation with messages */
    public function testShowConversationWithMessages(): void
    {
        $customer = Customer::factory()->create();
        $conversation = Conversation::factory()
            ->for($customer)
            ->create(['platform' => 'facebook']);

        // Create messages
        Message::factory(10)->for($conversation)->for($customer)->create();

        // GET /admin/inbox/{conversation}
        $response = $this->actingAs($this->admin)
            ->get(route('admin.inbox.show', ['conversation' => $conversation->id]));

        // Assert: 200 OK
        $response->assertStatus(200);

        // Assert: Messages loaded
        $response->assertViewHas('conversation');
        $response->assertViewHas('messages');
    }

    /** Test mark conversation as read */
    public function testMarkConversationAsRead(): void
    {
        $customer = Customer::factory()->create();
        $conversation = Conversation::factory()
            ->for($customer)
            ->create([
                'platform' => 'facebook',
                'unread_count' => 5,
            ]);

        Message::factory(5)->for($conversation)->for($customer)->create();

        // GET /admin/inbox/{conversation}
        $response = $this->actingAs($this->admin)
            ->get(route('admin.inbox.show', ['conversation' => $conversation->id]));

        $response->assertStatus(200);

        // Assert: unread_count = 0 after access
        $conversation->refresh();
        $this->assertEquals(0, $conversation->unread_count);
    }

    /** Test unauthorized access fails */
    public function testUnauthorizedAccessFails(): void
    {
        $customer = Customer::factory()->create();
        $conversation = Conversation::factory()
            ->for($customer)
            ->create(['platform' => 'facebook']);

        // Try access without authentication
        $response = $this->get(route('admin.inbox.index'));

        // Assert: NOT 200 - redirects to login
        $response->assertStatus(302);
        $response->assertRedirect();
    }

    /** Test non-admin can't access inbox */
    public function testNonAdminCannotAccessInbox(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->get(route('admin.inbox.index'));

        // Assert: 403 Forbidden
        $response->assertStatus(403);
    }

    /** Test pagination works */
    public function testPaginationWorks(): void
    {
        $customer = Customer::factory()->create();

        // Create 25 conversations
        for ($i = 0; $i < 25; $i++) {
            Conversation::factory()
                ->for($customer)
                ->create(['platform' => 'facebook']);
        }

        // GET /admin/inbox
        $response = $this->actingAs($this->admin)->get(route('admin.inbox.index'));

        $response->assertStatus(200);
        $conversations = $response->viewData('conversations');

        // Assert: Pagination (20 per page default)
        $this->assertEquals(20, $conversations->count());
        $this->assertTrue($conversations->hasPages());
    }

    /** Test search functionality */
    public function testSearchFunctionality(): void
    {
        $customer1 = Customer::factory()->create(['name' => 'John Doe']);
        $customer2 = Customer::factory()->create(['name' => 'Jane Smith']);

        Conversation::factory()->for($customer1)->create(['platform' => 'facebook']);
        Conversation::factory()->for($customer2)->create(['platform' => 'facebook']);

        // GET /admin/inbox?q=John
        $response = $this->actingAs($this->admin)
            ->get(route('admin.inbox.index', ['q' => 'John']));

        $response->assertStatus(200);
        $conversations = $response->viewData('conversations');
        $this->assertEquals(1, $conversations->count());
        $this->assertEquals('John Doe', $conversations->first()->customer->name);
    }

    /** Test conversation status update */
    public function testUpdateConversationStatus(): void
    {
        $customer = Customer::factory()->create();
        $conversation = Conversation::factory()
            ->for($customer)
            ->create(['platform' => 'facebook', 'status' => 'active']);

        // POST /admin/inbox/{conversation}/status
        $response = $this->actingAs($this->admin)
            ->post(route('admin.inbox.update-status', ['conversation' => $conversation->id]), [
                'status' => 'archived',
            ]);

        $response->assertStatus(200);

        // Assert: Status updated
        $conversation->refresh();
        $this->assertEquals('archived', $conversation->status);
    }
}
