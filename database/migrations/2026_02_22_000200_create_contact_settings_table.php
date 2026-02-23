<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $defaults = [
            'phone_display' => '+995 555 123 456',
            'phone_link' => '+995555123456',
            'whatsapp_url' => 'https://wa.me/995555123456',
            'email' => 'info@kidsimwatch.ge',
            'location' => 'Tbilisi, Georgia',
            'hours' => 'ყოველდღე 10:00 - 20:00',
            'instagram_url' => 'https://www.instagram.com/kidsimwatch',
            'facebook_url' => 'https://www.facebook.com/kidsimwatch',
            'messenger_url' => 'https://m.me/yourpage',
            'telegram_url' => 'https://t.me/kidsimwatch',
        ];

        foreach ($defaults as $key => $value) {
            DB::table('contact_settings')->insert([
                'key' => $key,
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_settings');
    }
};
