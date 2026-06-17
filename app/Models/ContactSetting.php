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
        'email' => 'info@mytechnic.ge',
        'location' => 'Tbilisi, Georgia',
        'hours' => 'áƒ§áƒáƒ•áƒ”áƒšáƒ“áƒ˜áƒ•áƒ” 10:00 - 20:00',
    ];

    public static function allKeyed(): array
    {
        if (! Schema::hasTable('contact_settings')) {
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
