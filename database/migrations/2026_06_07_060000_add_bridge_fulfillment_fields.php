<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('fulfillment_mode', 32)->default('local_stock')->after('external_product_id');
            $table->string('bridge_product_id')->nullable()->after('fulfillment_mode');
            $table->text('bridge_product_permalink')->nullable()->after('bridge_product_id');
            $table->string('product_sync_status', 32)->nullable()->after('bridge_product_permalink');
            $table->timestamp('product_synced_at')->nullable()->after('product_sync_status');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('bridge_variation_id')->nullable()->after('low_stock_threshold');
            $table->string('bridge_sku')->nullable()->after('bridge_variation_id');
            $table->integer('bridge_stock_quantity')->nullable()->after('bridge_sku');
            $table->string('bridge_stock_status', 32)->nullable()->after('bridge_stock_quantity');
            $table->string('stock_sync_status', 32)->nullable()->after('bridge_stock_status');
            $table->timestamp('stock_synced_at')->nullable()->after('stock_sync_status');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('fulfillment_mode', 32)->nullable()->after('payment_status');
            $table->string('bridge_order_id')->nullable()->after('fulfillment_mode');
            $table->string('bridge_order_number')->nullable()->after('bridge_order_id');
            $table->string('bridge_sync_status', 32)->nullable()->after('bridge_order_number');
            $table->timestamp('bridge_synced_at')->nullable()->after('bridge_sync_status');
            $table->string('fulfillment_status', 32)->nullable()->after('bridge_synced_at');
            $table->string('tracking_number')->nullable()->after('fulfillment_status');
            $table->string('tracking_carrier')->nullable()->after('tracking_number');
            $table->timestamp('fulfilled_at')->nullable()->after('tracking_carrier');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('bridge_product_id')->nullable()->after('subtotal');
            $table->string('bridge_variation_id')->nullable()->after('bridge_product_id');
            $table->string('fulfillment_mode', 32)->nullable()->after('bridge_variation_id');
        });

        DB::table('products')
            ->whereNull('fulfillment_mode')
            ->update(['fulfillment_mode' => 'local_stock']);

        DB::table('products')
            ->where('external_source', 'woo_bridge')
            ->update([
                'fulfillment_mode' => 'dropship_bridge',
                'bridge_product_id' => DB::raw('COALESCE(bridge_product_id, external_product_id)'),
                'bridge_product_permalink' => DB::raw('COALESCE(bridge_product_permalink, external_source_url)'),
                'product_sync_status' => DB::raw("COALESCE(product_sync_status, 'synced')"),
                'product_synced_at' => DB::raw('COALESCE(product_synced_at, CURRENT_TIMESTAMP)'),
                'is_active' => 0,
            ]);
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['bridge_product_id', 'bridge_variation_id', 'fulfillment_mode']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'fulfillment_mode',
                'bridge_order_id',
                'bridge_order_number',
                'bridge_sync_status',
                'bridge_synced_at',
                'fulfillment_status',
                'tracking_number',
                'tracking_carrier',
                'fulfilled_at',
            ]);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn([
                'bridge_variation_id',
                'bridge_sku',
                'bridge_stock_quantity',
                'bridge_stock_status',
                'stock_sync_status',
                'stock_synced_at',
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'fulfillment_mode',
                'bridge_product_id',
                'bridge_product_permalink',
                'product_sync_status',
                'product_synced_at',
            ]);
        });
    }
};
