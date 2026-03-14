<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->index('unread_count', 'conversations_unread_count_idx');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->index(['conversation_id', 'read_at'], 'messages_conversation_read_at_idx');
            $table->index(['conversation_id', 'sender_type', 'read_at'], 'messages_conversation_sender_read_idx');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_conversation_read_at_idx');
            $table->dropIndex('messages_conversation_sender_read_idx');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conversations_unread_count_idx');
        });
    }
};
