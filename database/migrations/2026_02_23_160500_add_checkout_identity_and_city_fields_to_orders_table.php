<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'personal_number')) {
                $table->string('personal_number', 20)->nullable()->after('customer_phone');
            }
            if (!Schema::hasColumn('orders', 'exact_address')) {
                $table->text('exact_address')->nullable()->after('delivery_address');
            }
            if (!Schema::hasColumn('orders', 'city_id')) {
                $table->unsignedBigInteger('city_id')->nullable()->after('city');
            }
        });

        // Add FK separately (cities table is guaranteed to exist via earlier migration)
        $hasFk = DB::select("
            SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
            AND CONSTRAINT_NAME = 'orders_city_id_foreign'
        ");
        if (empty($hasFk)) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreign('city_id')->references('id')->on('cities')->nullOnDelete();
            });
        }

        DB::table('orders')
            ->whereNull('exact_address')
            ->update(['exact_address' => DB::raw('delivery_address')]);

        if (Schema::hasTable('cities')) {
            $cityMap = DB::table('cities')
                ->select(['id', 'name'])
                ->get()
                ->mapWithKeys(fn ($city) => [mb_strtolower(trim((string) $city->name)) => (int) $city->id]);

            DB::table('orders')
                ->select(['id', 'city'])
                ->whereNull('city_id')
                ->whereNotNull('city')
                ->orderBy('id')
                ->chunkById(200, function ($orders) use ($cityMap) {
                    foreach ($orders as $order) {
                        $key = mb_strtolower(trim((string) $order->city));
                        $cityId = $cityMap[$key] ?? null;

                        if ($cityId) {
                            DB::table('orders')
                                ->where('id', $order->id)
                                ->update(['city_id' => $cityId]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
            $table->dropColumn(['personal_number', 'exact_address']);
        });
    }
};
