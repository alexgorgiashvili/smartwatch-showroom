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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->json('platform_user_ids')->nullable()->comment('Store all platform identifiers (facebook_id, instagram_id, etc)');
            $table->string('email')->nullable()->unique()->index();
            $table->string('phone')->nullable()->index();
            $table->string('avatar_url')->nullable();
            $table->json('metadata')->nullable()->comment('Platform-specific data (location, timezone, etc)');
            $table->timestamps();

            $table->index(['created_at', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
