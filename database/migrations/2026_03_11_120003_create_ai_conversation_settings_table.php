<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversation_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('global_enabled')->default(false);
            $table->integer('auto_reply_delay_ms')->default(2000);
            $table->json('enabled_platforms')->nullable();
            $table->json('business_hours')->nullable();
            $table->integer('max_auto_replies_per_conversation')->default(10);
            $table->text('fallback_message')->nullable();
            $table->timestamps();
        });

        DB::table('ai_conversation_settings')->insert([
            'global_enabled' => config('services.ai.enabled', false),
            'auto_reply_delay_ms' => 2000,
            'enabled_platforms' => json_encode(['instagram', 'facebook', 'whatsapp']),
            'max_auto_replies_per_conversation' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_conversation_settings');
    }
};
