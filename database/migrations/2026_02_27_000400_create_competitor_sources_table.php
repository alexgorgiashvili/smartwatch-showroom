<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitor_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('domain', 120);
            $table->string('category_url', 500)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_status', 30)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['domain', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_sources');
    }
};
