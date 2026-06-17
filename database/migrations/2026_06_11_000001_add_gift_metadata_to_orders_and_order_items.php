<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'is_gift_order')) {
                $table->boolean('is_gift_order')->default(false)->after('notes');
            }
            if (! Schema::hasColumn('orders', 'gift_groups')) {
                $table->json('gift_groups')->nullable()->after('is_gift_order');
            }
            if (! Schema::hasColumn('orders', 'gift_packaging_amount')) {
                $table->decimal('gift_packaging_amount', 10, 2)->default(0)->after('gift_groups');
            }
            if (! Schema::hasColumn('orders', 'gift_discount_amount')) {
                $table->decimal('gift_discount_amount', 10, 2)->default(0)->after('gift_packaging_amount');
            }
        });

        Schema::table('order_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('order_items', 'gift_group_id')) {
                $table->uuid('gift_group_id')->nullable()->after('fulfillment_mode');
            }
            if (! Schema::hasColumn('order_items', 'gift_role')) {
                $table->string('gift_role', 16)->nullable()->after('gift_group_id');
            }
            if (! Schema::hasColumn('order_items', 'gift_sort_order')) {
                $table->unsignedInteger('gift_sort_order')->nullable()->after('gift_role');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['gift_group_id', 'gift_role', 'gift_sort_order']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'is_gift_order',
                'gift_groups',
                'gift_packaging_amount',
                'gift_discount_amount',
            ]);
        });
    }
};
