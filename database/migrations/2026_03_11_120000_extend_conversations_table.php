<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('assigned_agent_id')
                ->nullable()
                ->after('assigned_to')
                ->constrained('agents')
                ->nullOnDelete();
            
            $table->enum('ai_mode', ['off', 'auto', 'manual_override'])
                ->default('off')
                ->after('is_ai_enabled');
            
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])
                ->default('normal')
                ->after('ai_mode')
                ->index();
            
            $table->json('tags')->nullable()->after('priority');
            
            $table->json('metadata')->nullable()->after('tags');
            
            $table->index(['status', 'priority', 'last_message_at']);
            $table->index(['assigned_agent_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['assigned_agent_id']);
            $table->dropIndex(['status', 'priority', 'last_message_at']);
            $table->dropIndex(['assigned_agent_id', 'status']);
            $table->dropColumn([
                'assigned_agent_id',
                'ai_mode',
                'priority',
                'tags',
                'metadata',
            ]);
        });
    }
};
