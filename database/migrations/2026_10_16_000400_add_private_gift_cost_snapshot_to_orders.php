<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'gift_cost_snapshot')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->json('gift_cost_snapshot')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'gift_cost_snapshot')) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('gift_cost_snapshot'));
        }
    }
};
