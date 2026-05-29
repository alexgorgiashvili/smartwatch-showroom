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
        Schema::create('prompt_enhancement_logs', function (Blueprint $table) {
            $table->id();
            $table->text('original_prompt');
            $table->text('enhanced_prompt');
            $table->json('analysis_metadata')->nullable(); // stores register, corrections, cultural notes
            $table->boolean('is_accepted')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prompt_enhancement_logs');
    }
};
