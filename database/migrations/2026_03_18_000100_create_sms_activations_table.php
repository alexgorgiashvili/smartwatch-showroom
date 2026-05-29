<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_activations', function (Blueprint $table) {
            $table->id();
            $table->string('activation_id')->unique()->index();
            $table->string('phone_number');
            $table->string('service');
            $table->string('service_name')->nullable();
            $table->string('country');
            $table->string('country_name')->nullable();
            $table->decimal('cost', 10, 2)->default(0);
            $table->unsignedInteger('currency')->nullable();
            $table->string('status')->default('pending')->index();
            $table->string('sms_code')->nullable();
            $table->text('sms_text')->nullable();
            $table->timestamp('sms_received_at')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_activations');
    }
};
