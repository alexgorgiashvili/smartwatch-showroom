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
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null')
                ->comment('Admin user who performed the action');
            $table->string('action')->index()->comment('Action type (e.g., inbox.message.send)');
            $table->string('method')->comment('HTTP method (GET, POST, PATCH, DELETE, etc.)');
            $table->string('endpoint')->comment('API endpoint that was accessed');
            $table->string('ip_address')->index()->comment('Client IP address');
            $table->text('user_agent')->nullable()->comment('User agent string');
            $table->json('parameters')->nullable()->comment('Request parameters (sanitized, no passwords)');
            $table->text('description')->nullable()->comment('Additional description of the action');
            $table->integer('status_code')->default(200)->comment('HTTP response status code');
            $table->timestamps();

            // Indexes for searching and filtering
            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index(['ip_address', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};
