<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facebook_posts', function (Blueprint $table) {
            $table->enum('media_type', ['none', 'image', 'video'])->nullable()->after('image_url');
            $table->string('video_url')->nullable()->after('media_type');

            $table->enum('facebook_publish_status', ['queued', 'publishing', 'published', 'failed'])->nullable()->after('instagram_post_id');
            $table->string('instagram_container_id')->nullable()->after('facebook_publish_status');
            $table->enum('instagram_publish_status', ['queued', 'publishing', 'published', 'failed'])->nullable()->after('instagram_container_id');
            $table->text('facebook_error')->nullable()->after('instagram_publish_status');
            $table->text('instagram_error')->nullable()->after('facebook_error');
            $table->timestamp('last_publish_check_at')->nullable()->after('instagram_error');
        });
    }

    public function down(): void
    {
        Schema::table('facebook_posts', function (Blueprint $table) {
            $table->dropColumn([
                'media_type',
                'video_url',
                'facebook_publish_status',
                'instagram_container_id',
                'instagram_publish_status',
                'facebook_error',
                'instagram_error',
                'last_publish_check_at',
            ]);
        });
    }
};
