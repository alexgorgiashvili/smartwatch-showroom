<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'gift_recommendation_tags')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->json('gift_recommendation_tags')->nullable()->after('gift_compatibility_tags');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'gift_recommendation_tags')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropColumn('gift_recommendation_tags');
            });
        }
    }
};
