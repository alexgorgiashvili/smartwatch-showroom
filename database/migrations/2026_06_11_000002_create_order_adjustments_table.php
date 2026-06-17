<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->uuid('gift_group_id')->nullable();
            $table->string('type', 32);
            $table->string('title');
            $table->decimal('amount', 10, 2);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'type']);
            $table->index('gift_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_adjustments');
    }
};
