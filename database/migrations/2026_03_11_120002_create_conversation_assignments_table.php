<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->onDelete('cascade');
            $table->foreignId('agent_id')
                ->constrained('agents')
                ->onDelete('cascade');
            $table->foreignId('assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['conversation_id', 'assigned_at']);
            $table->index(['agent_id', 'assigned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_assignments');
    }
};
