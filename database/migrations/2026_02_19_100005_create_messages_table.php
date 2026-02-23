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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->onDelete('cascade');
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->onDelete('cascade');
            $table->enum('sender_type', ['customer', 'admin'])->index();
            $table->unsignedBigInteger('sender_id')->comment('Admin user_id if sender_type=admin, customer_id if sender_type=customer');
            $table->string('sender_name')->index();
            $table->longText('content');
            $table->string('media_url')->nullable();
            $table->enum('media_type', ['image', 'video', 'file', 'audio'])->nullable();
            $table->string('platform_message_id')->nullable()->unique()->comment('Platform identifier for idempotency');
            $table->json('metadata')->nullable()->comment('Platform-specific data (coordinates, products, etc)');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['sender_type', 'sender_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
