<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('payment_type')->nullable()->after('order_source');
            $table->enum('payment_status', ['pending', 'completed', 'rejected'])->default('pending')->after('status');
            $table->string('bog_order_id')->nullable()->after('payment_status');
            $table->string('bog_external_order_id')->nullable()->after('bog_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_type', 'payment_status', 'bog_order_id', 'bog_external_order_id']);
        });
    }
};
