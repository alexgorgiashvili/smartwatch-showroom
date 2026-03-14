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
        Schema::create('ai_traffic', function (Blueprint $table) {
            $table->id();
            $table->string('ai_bot', 100)->index();
            $table->string('ai_family', 100)->index();
            $table->text('user_agent');
            $table->string('url', 500);
            $table->string('path', 500)->index();
            $table->string('method', 10);
            $table->string('ip', 45)->nullable();
            $table->string('referer', 500)->nullable();
            $table->integer('response_code')->nullable();
            $table->integer('response_time_ms')->nullable();
            $table->timestamps();

            // Indexes for analytics
            $table->index('created_at');
            $table->index(['ai_family', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_traffic');
    }
};
