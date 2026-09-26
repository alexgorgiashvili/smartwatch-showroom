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
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('conversations', function (Blueprint $table) {
            // Modify the platform enum to include 'messenger'
            // Using raw SQL as Laravel doesn't have a clean way to modify enums in PostgreSQL
            DB::statement("ALTER TABLE conversations MODIFY COLUMN platform ENUM('facebook', 'messenger', 'instagram', 'whatsapp', 'home')");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('conversations', function (Blueprint $table) {
            DB::statement("ALTER TABLE conversations MODIFY COLUMN platform ENUM('facebook', 'instagram', 'whatsapp')");
        });
    }
};
