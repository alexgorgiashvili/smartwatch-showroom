<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatbot_training_cases', function (Blueprint $table) {
            $table->string('source_reference')->nullable()->after('source');
            $table->index(['source', 'source_reference'], 'chatbot_training_cases_source_ref_index');
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_training_cases', function (Blueprint $table) {
            $table->dropIndex('chatbot_training_cases_source_ref_index');
            $table->dropColumn('source_reference');
        });
    }
};
