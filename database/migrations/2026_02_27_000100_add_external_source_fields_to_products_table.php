<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('external_source', 50)->nullable()->after('slug');
            $table->string('external_product_id', 120)->nullable()->after('external_source')->index();
            $table->string('external_source_url', 1024)->nullable()->after('external_product_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['external_product_id']);
            $table->dropColumn(['external_source', 'external_product_id', 'external_source_url']);
        });
    }
};
