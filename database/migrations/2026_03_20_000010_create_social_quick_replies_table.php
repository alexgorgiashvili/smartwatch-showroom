<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_quick_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform')->nullable()->index();
            $table->string('title');
            $table->text('body');
            $table->timestamps();

            $table->index(['platform', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_quick_replies');
    }
};

