<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mode', 20);
            $table->string('source_url', 2048)->nullable();
            $table->string('external_source', 50)->nullable();
            $table->string('external_product_id', 120)->nullable();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('name')->nullable();
            $table->json('identity_payload')->nullable();
            $table->timestamps();

            $table->index(['mode', 'updated_at']);
            $table->index(['external_source', 'external_product_id']);
            $table->unique(['product_id', 'mode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_targets');
    }
};
