<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('operating_system')->nullable()->after('warranty_months');
            $table->string('screen_size')->nullable()->after('operating_system');
            $table->string('display_type')->nullable()->after('screen_size');
            $table->string('screen_resolution')->nullable()->after('display_type');
            $table->unsignedInteger('battery_capacity_mah')->nullable()->after('screen_resolution');
            $table->decimal('charging_time_hours', 4, 1)->nullable()->after('battery_capacity_mah');
            $table->string('case_material')->nullable()->after('charging_time_hours');
            $table->string('band_material')->nullable()->after('case_material');
            $table->string('camera')->nullable()->after('band_material');
            $table->json('functions')->nullable()->after('camera');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'operating_system',
                'screen_size',
                'display_type',
                'screen_resolution',
                'battery_capacity_mah',
                'charging_time_hours',
                'case_material',
                'band_material',
                'camera',
                'functions',
            ]);
        });
    }
};
