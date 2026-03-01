<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('competitor_product_snapshots')) {
            return;
        }

        Schema::create('competitor_product_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competitor_product_id')->constrained()->cascadeOnDelete();
            $table->string('title', 255);
            $table->string('image_url', 500)->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('old_price', 10, 2)->nullable();
            $table->string('currency', 3)->default('GEL');
            $table->string('availability', 80)->nullable();
            $table->boolean('is_in_stock')->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->index(['competitor_product_id', 'captured_at'], 'competitor_snapshots_product_captured_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_product_snapshots');
    }
};
