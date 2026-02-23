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
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('platform', ['facebook', 'instagram', 'whatsapp'])->index();
            $table->string('event_type')->index();
            $table->json('payload');
            $table->boolean('verified')->default(false)->index();
            $table->boolean('processed')->default(false)->index();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['platform', 'created_at']);
            $table->index(['processed', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
