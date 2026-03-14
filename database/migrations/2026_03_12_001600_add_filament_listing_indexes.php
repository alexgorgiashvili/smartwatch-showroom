<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'orders_status_created_idx');
            $table->index(['payment_status', 'created_at'], 'orders_payment_status_created_idx');
            $table->index('city_id', 'orders_city_id_idx');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index(['is_active', 'updated_at'], 'products_active_updated_idx');
            $table->index(['featured', 'updated_at'], 'products_featured_updated_idx');
        });

        Schema::table('payment_logs', function (Blueprint $table) {
            $table->index(['chveni_statusi', 'created_at'], 'payment_logs_internal_status_created_idx');
            $table->index(['order_id', 'created_at'], 'payment_logs_order_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payment_logs', function (Blueprint $table) {
            $table->dropIndex('payment_logs_internal_status_created_idx');
            $table->dropIndex('payment_logs_order_created_idx');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_active_updated_idx');
            $table->dropIndex('products_featured_updated_idx');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_created_idx');
            $table->dropIndex('orders_payment_status_created_idx');
            $table->dropIndex('orders_city_id_idx');
        });
    }
};
