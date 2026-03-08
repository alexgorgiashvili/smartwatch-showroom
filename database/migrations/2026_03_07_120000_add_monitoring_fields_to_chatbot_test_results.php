<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatbot_test_results', function (Blueprint $table) {
            $table->string('fallback_reason', 120)->nullable()->after('response_time_ms');
            $table->boolean('regeneration_attempted')->default(false)->after('fallback_reason');
            $table->boolean('regeneration_succeeded')->default(false)->after('regeneration_attempted');

            $table->index('fallback_reason');
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_test_results', function (Blueprint $table) {
            $table->dropIndex(['fallback_reason']);
            $table->dropColumn([
                'fallback_reason',
                'regeneration_attempted',
                'regeneration_succeeded',
            ]);
        });
    }
};
