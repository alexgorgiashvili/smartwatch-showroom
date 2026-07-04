<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_authenticate_through_admin_login(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $response = $this->post(route('admin.login.submit'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_can_authenticate_through_admin_login(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $response = $this->post(route('admin.login.submit'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_login_is_rate_limited(): void
    {
        $user = User::factory()->create([
            'is_admin' => true,
        ]);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('admin.login.submit'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        $this->post(route('admin.login.submit'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    public function test_local_environment_does_not_bypass_authentication(): void
    {
        config(['app.env' => 'local']);

        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_local_environment_does_not_bypass_admin_authorization(): void
    {
        config(['app.env' => 'local']);

        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_legacy_conversation_api_is_not_exposed(): void
    {
        $this->getJson('/api/conversations')->assertNotFound();
        $this->postJson('/api/conversations/1/toggle-ai')->assertNotFound();
    }
}
