<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variant_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_image_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_variant_id', 'product_image_id'], 'variant_image_unique');
            $table->index(['product_variant_id', 'sort_order'], 'variant_image_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_images');
    }
};
