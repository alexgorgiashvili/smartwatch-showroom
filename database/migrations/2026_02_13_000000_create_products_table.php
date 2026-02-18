<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_ka');
            $table->string('slug')->unique();
            $table->string('short_description_en')->nullable();
            $table->string('short_description_ka')->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_ka')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('currency', 3)->default('GEL');
            $table->boolean('sim_support')->default(true);
            $table->boolean('gps_features')->default(true);
            $table->string('water_resistant')->nullable();
            $table->unsignedInteger('battery_life_hours')->nullable();
            $table->unsignedInteger('warranty_months')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('featured')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
