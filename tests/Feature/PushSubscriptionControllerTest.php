<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushSubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_store_push_subscription(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->postJson('/admin/push-subscriptions', [
                'endpoint' => 'https://push.example.test/subscriptions/abc123',
                'keys' => [
                    'p256dh' => 'test-public-key',
                    'auth' => 'test-auth-token',
                ],
                'contentEncoding' => 'aes128gcm',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $admin->id,
            'endpoint' => 'https://push.example.test/subscriptions/abc123',
            'endpoint_hash' => hash('sha256', 'https://push.example.test/subscriptions/abc123'),
            'public_key' => 'test-public-key',
            'auth_token' => 'test-auth-token',
            'content_encoding' => 'aes128gcm',
        ]);
    }

    public function test_admin_can_delete_push_subscription(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $subscription = PushSubscription::create([
            'user_id' => $admin->id,
            'endpoint' => 'https://push.example.test/subscriptions/delete-me',
            'endpoint_hash' => hash('sha256', 'https://push.example.test/subscriptions/delete-me'),
            'public_key' => 'test-public-key',
            'auth_token' => 'test-auth-token',
            'content_encoding' => 'aes128gcm',
        ]);

        $this->actingAs($admin)
            ->deleteJson('/admin/push-subscriptions', [
                'endpoint' => $subscription->endpoint,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('push_subscriptions', [
            'id' => $subscription->id,
        ]);
    }
}
