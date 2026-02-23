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
        'email' => 'info@kidsimwatch.ge',
        'location' => 'Tbilisi, Georgia',
        'hours' => 'ყოველდღე 10:00 - 20:00',
        'instagram_url' => 'https://www.instagram.com/kidsimwatch',
        'facebook_url' => 'https://www.facebook.com/kidsimwatch',
        'messenger_url' => 'https://m.me/yourpage',
        'telegram_url' => 'https://t.me/kidsimwatch',
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
