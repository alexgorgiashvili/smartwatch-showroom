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
            // Modify the sender_type enum to include 'bot'
            DB::statement("ALTER TABLE messages MODIFY COLUMN sender_type ENUM('customer', 'admin', 'bot')");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Revert to original enum values
            DB::statement("ALTER TABLE messages MODIFY COLUMN sender_type ENUM('customer', 'admin')");
        });
    }
};
