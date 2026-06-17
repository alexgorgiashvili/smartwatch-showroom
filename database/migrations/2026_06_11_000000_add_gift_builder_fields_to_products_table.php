<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'gift_builder_enabled')) {
                $table->boolean('gift_builder_enabled')->default(false)->after('featured');
            }
            if (! Schema::hasColumn('products', 'gift_builder_role')) {
                $table->string('gift_builder_role', 16)->default('none')->after('gift_builder_enabled');
            }
            if (! Schema::hasColumn('products', 'gift_recipient_tags')) {
                $table->json('gift_recipient_tags')->nullable()->after('gift_builder_role');
            }
            if (! Schema::hasColumn('products', 'gift_occasion_tags')) {
                $table->json('gift_occasion_tags')->nullable()->after('gift_recipient_tags');
            }
            if (! Schema::hasColumn('products', 'gift_budget_band')) {
                $table->string('gift_budget_band', 32)->nullable()->after('gift_occasion_tags');
            }
            if (! Schema::hasColumn('products', 'gift_compatibility_tags')) {
                $table->json('gift_compatibility_tags')->nullable()->after('gift_budget_band');
            }
            if (! Schema::hasColumn('products', 'gift_capacity_units')) {
                $table->unsignedTinyInteger('gift_capacity_units')->default(1)->after('gift_compatibility_tags');
            }
            if (! Schema::hasColumn('products', 'gift_badge_ka')) {
                $table->string('gift_badge_ka', 80)->nullable()->after('gift_capacity_units');
            }
            if (! Schema::hasColumn('products', 'gift_badge_en')) {
                $table->string('gift_badge_en', 80)->nullable()->after('gift_badge_ka');
            }
            if (! Schema::hasColumn('products', 'gift_builder_note_ka')) {
                $table->string('gift_builder_note_ka')->nullable()->after('gift_badge_en');
            }
            if (! Schema::hasColumn('products', 'gift_builder_note_en')) {
                $table->string('gift_builder_note_en')->nullable()->after('gift_builder_note_ka');
            }
            if (! Schema::hasColumn('products', 'gift_sort_order')) {
                $table->unsignedInteger('gift_sort_order')->default(0)->after('gift_builder_note_en');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'gift_builder_enabled',
                'gift_builder_role',
                'gift_recipient_tags',
                'gift_occasion_tags',
                'gift_budget_band',
                'gift_compatibility_tags',
                'gift_capacity_units',
                'gift_badge_ka',
                'gift_badge_en',
                'gift_builder_note_ka',
                'gift_builder_note_en',
                'gift_sort_order',
            ]);
        });
    }
};
