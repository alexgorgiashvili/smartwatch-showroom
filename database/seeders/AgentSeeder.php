<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Database\Seeder;

class AgentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create agents for all admin users
        $adminUsers = User::where('is_admin', true)->get();

        foreach ($adminUsers as $user) {
            Agent::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'status' => 'offline',
                    'is_online' => false,
                    'last_seen_at' => now(),
                ]
            );

            $this->command->info("Created agent for user: {$user->name}");
        }

        $this->command->info('Agent seeding completed.');
    }
}
