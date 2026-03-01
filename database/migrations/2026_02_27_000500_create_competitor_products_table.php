<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('competitor_products')) {
            Schema::create('competitor_products', function (Blueprint $table) {
                $table->id();
                $table->foreignId('competitor_source_id')->constrained()->cascadeOnDelete();
                $table->string('external_product_id', 120)->nullable();
                $table->char('product_url_hash', 64);
                $table->string('product_url', 500);
                $table->string('title', 255);
                $table->string('image_url', 500)->nullable();
                $table->decimal('current_price', 10, 2)->nullable();
                $table->decimal('old_price', 10, 2)->nullable();
                $table->string('currency', 3)->default('GEL');
                $table->string('availability', 80)->nullable();
                $table->boolean('is_in_stock')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();

                $table->unique(['competitor_source_id', 'product_url_hash'], 'competitor_products_source_url_hash_unique');
                $table->index(['competitor_source_id', 'external_product_id'], 'cp_src_ext_idx');
                $table->index(['competitor_source_id', 'last_seen_at'], 'cp_src_seen_idx');
            });

            return;
        }

        $indexNames = array_map(
            static fn ($index) => (string) ($index->Key_name ?? ''),
            DB::select('SHOW INDEX FROM competitor_products')
        );

        if (!in_array('cp_src_ext_idx', $indexNames, true)) {
            Schema::table('competitor_products', function (Blueprint $table) {
                $table->index(['competitor_source_id', 'external_product_id'], 'cp_src_ext_idx');
            });
        }

        if (!in_array('cp_src_seen_idx', $indexNames, true)) {
            Schema::table('competitor_products', function (Blueprint $table) {
                $table->index(['competitor_source_id', 'last_seen_at'], 'cp_src_seen_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_products');
    }
};
