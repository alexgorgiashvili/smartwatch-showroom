<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_blocked_users', function (Blueprint $table) {
            $table->id();
            $table->string('platform')->index();
            $table->string('author_id')->index();
            $table->string('author_name')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('blocked_at')->nullable();
            $table->timestamps();

            $table->unique(['platform', 'author_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_blocked_users');
    }
};

