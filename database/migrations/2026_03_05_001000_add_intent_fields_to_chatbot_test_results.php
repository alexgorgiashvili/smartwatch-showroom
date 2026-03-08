<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatbot_test_results', function (Blueprint $table) {
            $table->json('intent_json')->nullable()->after('rag_context');
            $table->text('standalone_query')->nullable()->after('intent_json');
            $table->string('intent_type', 50)->nullable()->after('standalone_query');
            $table->decimal('intent_confidence', 6, 4)->nullable()->after('intent_type');
            $table->unsignedInteger('intent_latency_ms')->nullable()->after('intent_confidence');
            $table->boolean('intent_match')->nullable()->after('georgian_qa_passed');
            $table->boolean('entity_match')->nullable()->after('intent_match');

            $table->index('intent_type');
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_test_results', function (Blueprint $table) {
            $table->dropIndex(['intent_type']);
            $table->dropColumn([
                'intent_json',
                'standalone_query',
                'intent_type',
                'intent_confidence',
                'intent_latency_ms',
                'intent_match',
                'entity_match',
            ]);
        });
    }
};
