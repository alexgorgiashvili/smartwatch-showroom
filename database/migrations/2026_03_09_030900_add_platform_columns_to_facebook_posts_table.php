<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('facebook_posts', function (Blueprint $table) {
            $table->boolean('post_to_facebook')->default(true)->after('image_url');
            $table->boolean('post_to_instagram')->default(false)->after('post_to_facebook');
            $table->string('instagram_post_id')->nullable()->after('facebook_post_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facebook_posts', function (Blueprint $table) {
            $table->dropColumn(['post_to_facebook', 'post_to_instagram', 'instagram_post_id']);
        });
    }
};
