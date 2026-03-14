<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->constrained('agents')->onDelete('set null');
            $table->boolean('is_ai_enabled')->default(false);
            $table->text('internal_notes')->nullable();
            $table->timestamp('last_agent_reply_at')->nullable();
            $table->string('agent_replying_to', 50)->nullable(); // Track which agent is currently replying

            $table->index(['assigned_to', 'status']);
            $table->index('is_ai_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropIndex(['assigned_to', 'status']);
            $table->dropIndex(['is_ai_enabled']);
            $table->dropColumn([
                'assigned_to',
                'is_ai_enabled',
                'internal_notes',
                'last_agent_reply_at',
                'agent_replying_to'
            ]);
        });
    }
};
