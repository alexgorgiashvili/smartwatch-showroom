<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_training_cases', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('prompt');
            $table->json('conversation_context_json')->nullable();
            $table->string('expected_intent')->nullable()->index();
            $table->json('expected_keywords_json')->nullable();
            $table->json('expected_product_slugs_json')->nullable();
            $table->string('expected_price_behavior')->nullable();
            $table->string('expected_stock_behavior')->nullable();
            $table->text('reviewer_notes')->nullable();
            $table->json('tags_json')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->string('source')->default('manual');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_training_cases');
    }
};
