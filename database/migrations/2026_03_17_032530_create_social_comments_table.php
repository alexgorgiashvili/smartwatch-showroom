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
        Schema::create('social_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facebook_post_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform')->index();
            $table->string('platform_comment_id')->unique();
            $table->string('platform_post_id')->nullable();
            $table->string('parent_comment_id')->nullable();
            $table->string('author_name')->nullable();
            $table->string('author_id')->nullable();
            $table->text('message');
            $table->string('sentiment')->nullable();
            $table->enum('status', ['unread', 'read', 'replied', 'spam', 'hidden'])->default('unread');
            $table->text('ai_suggested_reply')->nullable();
            $table->text('actual_reply')->nullable();
            $table->string('reply_platform_id')->nullable();
            $table->timestamp('commented_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['platform', 'platform_post_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_comments');
    }
};
