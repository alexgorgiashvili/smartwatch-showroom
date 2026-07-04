<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_be_created_without_source_controlled_credentials(): void
    {
        $password = 'Strong-Test-Password-123!';

        $this->artisan('admin:create', [
            '--name' => 'Primary Admin',
            '--email' => 'admin@example.test',
        ])
            ->expectsQuestion('Admin password', $password)
            ->expectsQuestion('Confirm admin password', $password)
            ->expectsOutput('Admin user created successfully.')
            ->assertSuccessful();

        $admin = User::query()
            ->where('email', 'admin@example.test')
            ->firstOrFail();

        $this->assertTrue($admin->is_admin);
        $this->assertTrue(Hash::check($password, $admin->password));
    }

    public function test_admin_creation_rejects_a_weak_password(): void
    {
        $this->artisan('admin:create', [
            '--name' => 'Primary Admin',
            '--email' => 'admin@example.test',
        ])
            ->expectsQuestion('Admin password', 'weak')
            ->expectsQuestion('Confirm admin password', 'weak')
            ->assertFailed();

        $this->assertDatabaseMissing('users', [
            'email' => 'admin@example.test',
        ]);
    }
}
