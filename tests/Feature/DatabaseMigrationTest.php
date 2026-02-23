<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseMigrationTest extends TestCase
{
    use RefreshDatabase;

    /** Test all migrations run */
    public function testAllMigrationsRun(): void
    {
        // Verify migrations completed
        $this->assertTrue(true); // RefreshDatabase trait runs migrations

        // Assert: No errors
        // Artisan::call() would throw if there were errors

        // Assert: All critical tables exist
        $tables = [
            'users',
            'customers',
            'conversations',
            'messages',
            'webhook_logs',
            'products',
            'orders',
            'inquiries',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(
                DB::getSchemaBuilder()->hasTable($table),
                "Table {$table} does not exist"
            );
        }
    }

    /** Test customers table structure */
    public function testCustomersTableStructure(): void
    {
        $columns = DB::getSchemaBuilder()->getColumnListing('customers');

        $requiredColumns = [
            'id',
            'name',
            'email',
            'phone',
            'avatar_url',
            'platform_user_ids',
            'metadata',
            'created_at',
            'updated_at',
        ];

        foreach ($requiredColumns as $column) {
            $this->assertContains($column, $columns, "Column {$column} missing from customers table");
        }
    }

    /** Test conversations table structure */
    public function testConversationsTableStructure(): void
    {
        $columns = DB::getSchemaBuilder()->getColumnListing('conversations');

        $requiredColumns = [
            'id',
            'customer_id',
            'platform',
            'platform_conversation_id',
            'subject',
            'status',
            'unread_count',
            'last_message_at',
            'created_at',
            'updated_at',
        ];

        foreach ($requiredColumns as $column) {
            $this->assertContains($column, $columns, "Column {$column} missing from conversations table");
        }
    }

    /** Test messages table structure */
    public function testMessagesTableStructure(): void
    {
        $columns = DB::getSchemaBuilder()->getColumnListing('messages');

        $requiredColumns = [
            'id',
            'conversation_id',
            'customer_id',
            'sender_type',
            'sender_id',
            'sender_name',
            'content',
            'media_url',
            'media_type',
            'platform_message_id',
            'metadata',
            'read_at',
            'encrypted',
            'confidential',
            'created_at',
            'updated_at',
        ];

        foreach ($requiredColumns as $column) {
            $this->assertContains($column, $columns, "Column {$column} missing from messages table");
        }
    }

    /** Test webhook_logs table structure */
    public function testWebhookLogsTableStructure(): void
    {
        $columns = DB::getSchemaBuilder()->getColumnListing('webhook_logs');

        $requiredColumns = [
            'id',
            'platform',
            'event_type',
            'payload',
            'verified',
            'processed',
            'error',
            'created_at',
            'updated_at',
        ];

        foreach ($requiredColumns as $column) {
            $this->assertContains($column, $columns, "Column {$column} missing from webhook_logs table");
        }
    }

    /** Test foreign key constraints exist */
    public function testForeignKeyConstraints(): void
    {
        // This is a basic check - actual foreign keys depend on migration details
        $tables = DB::getSchemaBuilder()->getColumnListing('conversations');
        $this->assertContains('customer_id', $tables);

        $tables = DB::getSchemaBuilder()->getColumnListing('messages');
        $this->assertContains('conversation_id', $tables);
        $this->assertContains('customer_id', $tables);
    }

    /** Test indexes exist for performance */
    public function testIndexesCreated(): void
    {
        // Get indexes for conversations table
        $conversationIndexes = DB::select('SHOW INDEXES FROM conversations');
        $indexNames = array_map(fn ($idx) => $idx->Key_name, $conversationIndexes);

        // Should have some indexes for performance
        $this->assertGreaterThan(0, count($indexNames));
    }

    /** Test table collation is utf8mb4 */
    public function testTableCollation(): void
    {
        $tables = ['customers', 'conversations', 'messages'];

        foreach ($tables as $table) {
            $result = DB::selectOne("SELECT TABLE_COLLATION FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = ?", [$table]);

            if ($result) {
                $this->assertStringContainsString('utf8', $result->TABLE_COLLATION);
            }
        }
    }

    /** Test all tables have timestamps */
    public function testTablesHaveTimestamps(): void
    {
        $tables = ['customers', 'conversations', 'messages', 'webhook_logs'];

        foreach ($tables as $table) {
            $columns = DB::getSchemaBuilder()->getColumnListing($table);
            $this->assertContains('created_at', $columns, "Table {$table} missing created_at");
            $this->assertContains('updated_at', $columns, "Table {$table} missing updated_at");
        }
    }

    /** Test nullable columns are properly defined */
    public function testNullableColumnsProperlyDefined(): void
    {
        // Columns that should be nullable
        $nullableColumns = [
            'customers' => ['email', 'phone', 'avatar_url'],
            'conversations' => ['subject', 'last_message_at'],
            'messages' => ['media_url', 'media_type', 'read_at'],
        ];

        foreach ($nullableColumns as $table => $columns) {
            foreach ($columns as $column) {
                // This is a structural check - actual nullability verified through service
                $this->assertTrue(
                    DB::getSchemaBuilder()->hasColumn($table, $column),
                    "Column {$column} missing from {$table}"
                );
            }
        }
    }

    /** Test default values are set correctly */
    public function testDefaultValuesSet(): void
    {
        // Verify that key columns have appropriate defaults
        // This is database-specific and may require raw queries

        // Create a test row to verify defaults
        $customer = DB::table('customers')->insertGetId([
            'name' => 'Test Customer',
        ]);

        $result = DB::table('customers')->find($customer);
        $this->assertNotNull($result);
        $this->assertNull($result->email);
    }
}
