<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('chatbot_test_runs', function (Blueprint $table): void {
            if (!Schema::hasColumn('chatbot_test_runs', 'filters')) {
                $table->json('filters')->nullable()->after('triggered_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_test_runs', function (Blueprint $table): void {
            if (Schema::hasColumn('chatbot_test_runs', 'filters')) {
                $table->dropColumn('filters');
            }
        });
    }
};
