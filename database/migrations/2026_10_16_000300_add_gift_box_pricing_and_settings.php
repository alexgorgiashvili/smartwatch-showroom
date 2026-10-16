<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'gift_box_price')) {
                $table->decimal('gift_box_price', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('products', 'gift_landed_cost')) {
                $table->decimal('gift_landed_cost', 12, 4)->nullable();
            }
            if (! Schema::hasColumn('products', 'gift_cost_status')) {
                $table->string('gift_cost_status', 16)->default('unknown');
            }
            if (! Schema::hasColumn('products', 'gift_requires_compatibility')) {
                $table->boolean('gift_requires_compatibility')->default(false);
            }
        });

        if (Schema::hasTable('ready_gift_boxes') && ! Schema::hasColumn('ready_gift_boxes', 'pricing_version')) {
            Schema::table('ready_gift_boxes', function (Blueprint $table): void {
                $table->unsignedTinyInteger('pricing_version')->default(1)->after('packaging_slug');
            });
        }

        if (! Schema::hasTable('gift_builder_settings')) {
            Schema::create('gift_builder_settings', function (Blueprint $table): void {
                $table->unsignedTinyInteger('id')->primary();
                $table->json('packaging_options');
                $table->decimal('delivery_fee', 8, 2)->default(7);
                $table->decimal('free_delivery_threshold', 8, 2)->default(100);
                $table->decimal('ad_cost_per_order', 10, 2)->nullable();
                $table->decimal('courier_cost_per_order', 10, 2)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_builder_settings');

        if (Schema::hasTable('ready_gift_boxes') && Schema::hasColumn('ready_gift_boxes', 'pricing_version')) {
            Schema::table('ready_gift_boxes', fn (Blueprint $table) => $table->dropColumn('pricing_version'));
        }

        if (Schema::hasTable('products')) {
            foreach (['gift_box_price', 'gift_landed_cost', 'gift_cost_status', 'gift_requires_compatibility'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    Schema::table('products', fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
        }
    }
};
