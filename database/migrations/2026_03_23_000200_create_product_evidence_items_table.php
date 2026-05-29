<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_evidence_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_target_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_type', 60);
            $table->string('source_url', 2048)->nullable();
            $table->string('source_item_id', 255)->nullable();
            $table->string('author_name')->nullable();
            $table->string('author_type', 20)->default('unknown');
            $table->decimal('rating_raw', 5, 2)->nullable();
            $table->string('title')->nullable();
            $table->text('body_text');
            $table->string('language', 12)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('country', 80)->nullable();
            $table->decimal('credibility_weight', 5, 2)->nullable();
            $table->string('dedupe_hash', 64);
            $table->json('raw_payload')->nullable();
            $table->json('normalized_payload')->nullable();
            $table->timestamps();

            $table->index(['research_target_id', 'source_type']);
            $table->index(['research_target_id', 'author_type']);
            $table->index(['product_id', 'published_at']);
            $table->unique(['research_target_id', 'dedupe_hash'], 'evidence_target_dedupe_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_evidence_items');
    }
};
