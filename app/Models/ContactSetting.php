<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ContactSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public const DEFAULTS = [
        'phone_display' => '+995 555 123 456',
        'phone_link' => '+995555123456',
        'whatsapp_url' => 'https://wa.me/995555123456',
        'email' => 'info@mytechnic.ge',
        'location' => 'Tbilisi, Georgia',
        'hours' => 'ყოველდივე 10:00 - 20:00',
        'instagram_url' => 'https://www.instagram.com/mytechnic.ge',
        'facebook_url' => 'https://www.facebook.com/mytechnic.ge',
        'messenger_url' => 'https://m.me/mytechnic.ge',
        'telegram_url' => 'https://t.me/mytechnic_ge',
    ];

    public static function allKeyed(): array
    {
        if (!Schema::hasTable('contact_settings')) {
            return self::DEFAULTS;
        }

        try {
            $stored = self::query()->pluck('value', 'key')->toArray();
        } catch (\Throwable) {
            return self::DEFAULTS;
        }

        return array_merge(self::DEFAULTS, $stored);
    }
}
