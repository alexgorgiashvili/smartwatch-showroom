<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('competitor_mappings')) {
            return;
        }

        Schema::create('competitor_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competitor_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique('competitor_product_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_mappings');
    }
};
