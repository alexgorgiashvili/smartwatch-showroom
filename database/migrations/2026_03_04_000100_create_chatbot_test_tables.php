<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_test_runs', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['pending', 'running', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->unsignedInteger('total_cases')->default(0);
            $table->unsignedInteger('passed_cases')->default(0);
            $table->unsignedInteger('failed_cases')->default(0);
            $table->unsignedInteger('skipped_cases')->default(0);
            $table->decimal('accuracy_pct', 5, 2)->nullable();
            $table->decimal('avg_llm_score', 3, 1)->nullable();
            $table->decimal('guardrail_pass_rate', 5, 2)->nullable();
            $table->decimal('duration_seconds', 8, 2)->nullable();
            $table->string('triggered_by', 100)->default('manual');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });

        Schema::create('chatbot_test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_run_id')->constrained('chatbot_test_runs')->cascadeOnDelete();
            $table->string('case_id', 50);
            $table->string('category', 50);
            $table->text('question');
            $table->text('expected_summary')->nullable();
            $table->text('actual_response')->nullable();
            $table->longText('rag_context')->nullable();
            $table->enum('status', ['pass', 'fail', 'skip', 'error'])->default('skip');

            $table->boolean('keyword_match')->default(false);
            $table->boolean('price_match')->nullable();
            $table->boolean('stock_match')->nullable();
            $table->boolean('guardrail_passed')->nullable();
            $table->boolean('georgian_qa_passed')->nullable();

            $table->unsignedTinyInteger('llm_accuracy')->nullable();
            $table->unsignedTinyInteger('llm_relevance')->nullable();
            $table->unsignedTinyInteger('llm_grammar')->nullable();
            $table->unsignedTinyInteger('llm_completeness')->nullable();
            $table->unsignedTinyInteger('llm_safety')->nullable();
            $table->decimal('llm_overall', 2, 1)->nullable();
            $table->text('llm_notes')->nullable();

            $table->unsignedInteger('response_time_ms')->nullable();
            $table->text('admin_feedback')->nullable();
            $table->enum('retrain_status', ['none', 'pending', 'done'])->default('none');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['test_run_id', 'status']);
            $table->index('category');
            $table->index('case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_test_results');
        Schema::dropIfExists('chatbot_test_runs');
    }
};
