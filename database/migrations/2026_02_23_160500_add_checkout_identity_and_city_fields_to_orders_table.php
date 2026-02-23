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
            $table->string('personal_number', 20)->nullable()->after('customer_phone');
            $table->text('exact_address')->nullable()->after('delivery_address');
            $table->foreignId('city_id')->nullable()->after('city')->constrained('cities')->nullOnDelete();
        });

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
