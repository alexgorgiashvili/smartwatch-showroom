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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->enum('platform', ['facebook', 'instagram', 'whatsapp'])->index();
            $table->string('platform_conversation_id')->unique()->index();
            $table->string('subject')->nullable();
            $table->enum('status', ['active', 'archived', 'closed'])->default('active')->index();
            $table->integer('unread_count')->default(0);
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();

            $table->index(['customer_id', 'platform']);
            $table->index(['status', 'last_message_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
