<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_auto_reply_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facebook_post_id')->constrained('facebook_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('match_type', ['contains', 'keywords', 'regex']);
            $table->text('match_value');
            $table->boolean('use_ai')->default(false);
            $table->text('reply_template');
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('max_replies_per_author_per_day')->default(3);
            $table->timestamps();

            $table->index(['facebook_post_id', 'enabled', 'created_at'], 'sar_post_enabled_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_auto_reply_rules');
    }
};
