<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ready_gift_boxes', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 160)->unique();
            $table->string('title_ka');
            $table->string('title_en')->nullable();
            $table->text('short_description_ka')->nullable();
            $table->text('short_description_en')->nullable();
            $table->string('badge_ka', 120)->nullable();
            $table->string('badge_en', 120)->nullable();
            $table->string('cover_image_path')->nullable();
            $table->string('theme_key', 32)->default('grape');
            $table->string('packaging_slug', 80)->default('standard');
            $table->string('discount_type', 16)->default('none');
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->boolean('is_active')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'is_featured', 'sort_order'], 'ready_gift_boxes_public_index');
        });

        Schema::create('ready_gift_box_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ready_gift_box_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('default_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('role', 16);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['ready_gift_box_id', 'product_id'], 'ready_gift_box_product_unique');
            $table->index(['ready_gift_box_id', 'role', 'sort_order'], 'ready_gift_box_items_role_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ready_gift_box_items');
        Schema::dropIfExists('ready_gift_boxes');
    }
};
