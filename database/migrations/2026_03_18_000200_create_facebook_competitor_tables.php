<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_competitor_pages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('facebook_url');
            $table->string('page_id')->nullable();
            $table->string('category')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('scraping_frequency')->default('daily');
            $table->timestamp('last_scraped_at')->nullable();
            $table->unsignedInteger('total_posts_count')->default(0);
            $table->unsignedInteger('relevant_posts_count')->default(0);
            $table->decimal('avg_engagement_rate', 6, 3)->default(0);
            $table->unsignedInteger('follower_count')->default(0);
            $table->timestamps();

            $table->index('is_active');
            $table->index('last_scraped_at');
        });

        Schema::create('facebook_competitor_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competitor_page_id')
                ->constrained('facebook_competitor_pages')
                ->cascadeOnDelete();
            $table->string('facebook_post_id')->nullable();
            $table->string('post_url', 2048)->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('scraped_at')->nullable();
            $table->text('text')->nullable();
            $table->json('images_json')->nullable();
            $table->string('video_url', 2048)->nullable();
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->unsignedInteger('shares_count')->default(0);
            $table->json('reactions_json')->nullable();
            $table->boolean('is_relevant')->nullable();
            $table->unsignedTinyInteger('relevance_score')->default(0);
            $table->string('relevance_reason')->nullable();
            $table->json('product_mentions_json')->nullable();
            $table->timestamps();

            $table->unique('facebook_post_id');
            $table->index('competitor_page_id');
            $table->index('posted_at');
            $table->index('is_relevant');
            $table->index(['competitor_page_id', 'posted_at']);
        });

        Schema::create('facebook_competitor_analyses', function (Blueprint $table) {
            $table->id();
            $table->date('analysis_date');
            $table->string('analysis_type')->default('weekly');
            $table->json('competitor_page_ids_json')->nullable();
            $table->unsignedInteger('posts_analyzed_count')->default(0);
            $table->json('competitive_intelligence_json')->nullable();
            $table->json('content_strategy_json')->nullable();
            $table->json('sentiment_analysis_json')->nullable();
            $table->json('trend_analysis_json')->nullable();
            $table->json('recommendations_json')->nullable();
            $table->string('ai_model_used')->nullable();
            $table->unsignedInteger('tokens_used')->default(0);
            $table->timestamps();

            $table->index('analysis_date');
            $table->index('analysis_type');
        });

        Schema::create('facebook_competitor_insights', function (Blueprint $table) {
            $table->id();
            $table->string('insight_type');
            $table->string('priority')->default('medium');
            $table->string('status')->default('new');
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('data_json')->nullable();
            $table->foreignId('competitor_page_id')
                ->nullable()
                ->constrained('facebook_competitor_pages')
                ->nullOnDelete();
            $table->json('related_post_ids_json')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('actioned_at')->nullable();
            $table->timestamps();

            $table->index('insight_type');
            $table->index('priority');
            $table->index('status');
            $table->index(['status', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_competitor_insights');
        Schema::dropIfExists('facebook_competitor_analyses');
        Schema::dropIfExists('facebook_competitor_posts');
        Schema::dropIfExists('facebook_competitor_pages');
    }
};
