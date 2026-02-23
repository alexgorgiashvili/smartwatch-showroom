<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Seeder;

class TestInboxSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create test customers
        $customer1 = Customer::firstOrCreate(
            ['email' => 'john.doe@test.com'],
            [
                'name' => 'John Doe',
                'phone' => '+1234567890',
                'platform_user_ids' => json_encode([
                    'whatsapp' => 'test_customer_1',
                ]),
            ]
        );

        $customer2 = Customer::firstOrCreate(
            ['email' => 'jane.smith@test.com'],
            [
                'name' => 'Jane Smith',
                'phone' => '+0987654321',
                'platform_user_ids' => json_encode([
                    'messenger' => 'test_customer_2',
                ]),
            ]
        );

        // Create conversation for customer 1
        $conversation1 = Conversation::firstOrCreate(
            [
                'customer_id' => $customer1->id,
                'platform' => 'whatsapp',
                'platform_conversation_id' => 'whatsapp_conv_1',
            ],
            [
                'status' => 'active',
                'last_message_at' => now(),
                'unread_count' => 1,
            ]
        );

        // Add messages to conversation 1
        Message::create([
            'conversation_id' => $conversation1->id,
            'customer_id' => $customer1->id,
            'platform_message_id' => 'test_msg_1',
            'sender_type' => 'customer',
            'sender_id' => $customer1->id,
            'sender_name' => $customer1->name,
            'content' => 'Hello! I\'m interested in the KidSIM Watch Pro',
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        Message::create([
            'conversation_id' => $conversation1->id,
            'customer_id' => $customer1->id,
            'platform_message_id' => 'test_msg_2',
            'sender_type' => 'admin',
            'sender_id' => 1,
            'sender_name' => 'Admin',
            'content' => 'Hi John! The KidSIM Watch Pro is available. Would you like to know more about its features?',
            'read_at' => now()->subMinutes(2),
            'created_at' => now()->subMinutes(3),
            'updated_at' => now()->subMinutes(3),
        ]);

        Message::create([
            'conversation_id' => $conversation1->id,
            'customer_id' => $customer1->id,
            'platform_message_id' => 'test_msg_3',
            'sender_type' => 'customer',
            'sender_id' => $customer1->id,
            'sender_name' => $customer1->name,
            'content' => 'Yes please! What colors do you have?',
            'created_at' => now()->subMinutes(1),
            'updated_at' => now()->subMinutes(1),
        ]);

        // Create conversation for customer 2
        $conversation2 = Conversation::firstOrCreate(
            [
                'customer_id' => $customer2->id,
                'platform' => 'facebook',
                'platform_conversation_id' => 'messenger_conv_1',
            ],
            [
                'status' => 'active',
                'last_message_at' => now()->subMinutes(10),
                'unread_count' => 1,
            ]
        );

        // Add messages to conversation 2
        Message::create([
            'conversation_id' => $conversation2->id,
            'customer_id' => $customer2->id,
            'platform_message_id' => 'test_msg_4',
            'sender_type' => 'customer',
            'sender_id' => $customer2->id,
            'sender_name' => $customer2->name,
            'content' => 'Do you ship internationally?',
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        $this->command->info('✅ Test inbox data seeded successfully!');
        $this->command->info('   - 2 customers created');
        $this->command->info('   - 2 conversations created');
        $this->command->info('   - 4 messages added');
    }
}
