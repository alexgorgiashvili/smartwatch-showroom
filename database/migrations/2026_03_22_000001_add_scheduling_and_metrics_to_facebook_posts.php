<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Extend status enum to include 'scheduled'
        DB::statement("ALTER TABLE facebook_posts MODIFY COLUMN status ENUM('draft','scheduled','published','failed') NOT NULL DEFAULT 'draft'");

        Schema::table('facebook_posts', function (Blueprint $table) {
            $table->timestamp('scheduled_at')->nullable()->after('published_at');
            $table->unsignedInteger('fb_reactions_count')->nullable()->after('scheduled_at');
            $table->unsignedInteger('fb_shares_count')->nullable()->after('fb_reactions_count');
            $table->unsignedBigInteger('fb_impressions')->nullable()->after('fb_shares_count');
            $table->unsignedInteger('ig_likes_count')->nullable()->after('fb_impressions');
            $table->unsignedBigInteger('ig_reach')->nullable()->after('ig_likes_count');
            $table->timestamp('metrics_fetched_at')->nullable()->after('ig_reach');
        });
    }

    public function down(): void
    {
        Schema::table('facebook_posts', function (Blueprint $table) {
            $table->dropColumn([
                'scheduled_at',
                'fb_reactions_count',
                'fb_shares_count',
                'fb_impressions',
                'ig_likes_count',
                'ig_reach',
                'metrics_fetched_at',
            ]);
        });

        DB::statement("ALTER TABLE facebook_posts MODIFY COLUMN status ENUM('draft','published','failed') NOT NULL DEFAULT 'draft'");
    }
};
