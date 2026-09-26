<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Content Studio deliberately keeps its working copy separate from legacy
     * articles/facebook_posts. Publication creates a linked legacy record only
     * after an owner has approved the exact revision.
     */
    public function up(): void
    {
        Schema::create('content_campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('week_key', 10)->nullable()->index();
            $table->string('fingerprint', 64)->unique();
            $table->json('utm')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('content_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('content_campaigns')->cascadeOnDelete();
            $table->string('channel', 20); // article, facebook, instagram
            $table->string('status', 32)->default('generated')->index();
            $table->string('fingerprint', 64)->unique();
            $table->string('title')->nullable();
            $table->unsignedBigInteger('current_revision_id')->nullable();
            $table->unsignedBigInteger('approved_revision_id')->nullable();
            $table->unsignedBigInteger('published_record_id')->nullable();
            $table->string('published_record_type')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('verification_checked_at')->nullable();
            $table->json('evidence')->nullable();
            $table->json('review_metadata')->nullable();
            $table->text('reviewer_notes')->nullable();
            $table->string('last_error', 1000)->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'channel']);
        });

        Schema::create('content_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('fingerprint', 64)->unique();
            $table->json('payload');
            $table->json('evidence')->nullable();
            $table->json('validation')->nullable();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();
            $table->unique(['content_item_id', 'revision_number']);
        });

        Schema::create('content_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('content_revision_id')->nullable()->constrained('content_revisions')->nullOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('public_url', 2000)->nullable();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->json('validation')->nullable();
            $table->timestamps();
        });

        Schema::create('content_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_revision_id')->nullable()->constrained('content_revisions')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 80);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->index(['content_item_id', 'occurred_at']);
        });

        Schema::create('content_analytics_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_campaign_id')->nullable()->constrained('content_campaigns')->nullOnDelete();
            $table->foreignId('content_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 30); // ga4, gsc, facebook, instagram, ai_traffic
            $table->unsignedSmallInteger('window_days');
            $table->json('metrics');
            $table->timestamp('collected_at');
            $table->string('status', 20)->default('fresh');
            $table->string('error', 1000)->nullable();
            $table->timestamps();
            $table->unique(['content_campaign_id', 'content_item_id', 'source', 'window_days'], 'content_snapshot_scope_unique');
        });

        // Nullable link only; all pre-existing social records remain untouched.
        Schema::table('facebook_posts', function (Blueprint $table) {
            $table->unsignedBigInteger('content_item_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('facebook_posts', function (Blueprint $table) {
            $table->dropUnique(['content_item_id']);
            $table->dropColumn('content_item_id');
        });
        Schema::dropIfExists('content_analytics_snapshots');
        Schema::dropIfExists('content_audit_events');
        Schema::dropIfExists('content_assets');
        Schema::dropIfExists('content_revisions');
        Schema::dropIfExists('content_items');
        Schema::dropIfExists('content_campaigns');
    }
};
