<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE messages MODIFY COLUMN sender_type ENUM('customer', 'admin', 'bot', 'system') NOT NULL");
        
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('reply_to_id')
                ->nullable()
                ->after('conversation_id')
                ->constrained('messages')
                ->nullOnDelete();
            
            $table->enum('delivery_status', ['pending', 'sent', 'delivered', 'failed'])
                ->default('sent')
                ->after('read_at')
                ->index();
            
            $table->index(['reply_to_id']);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['reply_to_id']);
            $table->dropIndex(['reply_to_id']);
            $table->dropIndex(['delivery_status']);
            $table->dropColumn(['reply_to_id', 'delivery_status']);
        });
        
        DB::statement("ALTER TABLE messages MODIFY COLUMN sender_type ENUM('customer', 'admin') NOT NULL");
    }
};
