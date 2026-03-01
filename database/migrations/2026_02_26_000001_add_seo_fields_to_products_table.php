<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('meta_title_ka', 160)->nullable()->after('slug');
            $table->string('meta_title_en', 160)->nullable()->after('meta_title_ka');
            $table->text('meta_description_ka')->nullable()->after('meta_title_en');
            $table->text('meta_description_en')->nullable()->after('meta_description_ka');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'meta_title_ka',
                'meta_title_en',
                'meta_description_ka',
                'meta_description_en',
            ]);
        });
    }
};
