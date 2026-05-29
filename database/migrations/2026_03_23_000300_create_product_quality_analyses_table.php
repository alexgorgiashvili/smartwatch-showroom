<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_quality_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_target_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('queued');
            $table->string('model_used')->nullable();
            $table->unsignedInteger('evidence_count')->default(0);
            $table->unsignedInteger('end_user_evidence_count')->default(0);
            $table->unsignedInteger('supplier_evidence_count')->default(0);
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->string('verdict', 40)->nullable();
            $table->json('summary_json')->nullable();
            $table->json('comparison_ready_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['research_target_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_quality_analyses');
    }
};
