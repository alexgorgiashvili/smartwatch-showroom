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
        Schema::table('messages', function (Blueprint $table) {
            // Add encrypted column to track if content is encrypted
            $table->boolean('encrypted')->default(false)->after('metadata')->comment('Whether message content is encrypted');

            // Add confidential flag for sensitive messages
            $table->boolean('confidential')->default(false)->after('encrypted')->comment('Mark message as containing sensitive/confidential info');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['encrypted', 'confidential']);
        });
    }
};
