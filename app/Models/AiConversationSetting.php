<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiConversationSetting extends Model
{
    protected $fillable = [
        'global_enabled',
        'auto_reply_delay_ms',
        'enabled_platforms',
        'business_hours',
        'max_auto_replies_per_conversation',
        'fallback_message',
    ];

    protected $casts = [
        'global_enabled' => 'boolean',
        'auto_reply_delay_ms' => 'integer',
        'enabled_platforms' => 'array',
        'business_hours' => 'array',
        'max_auto_replies_per_conversation' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate(
            [],
            [
                'global_enabled' => false,
                'auto_reply_delay_ms' => 2000,
                'enabled_platforms' => ['instagram', 'facebook', 'whatsapp'],
                'max_auto_replies_per_conversation' => 10,
            ]
        );
    }

    public function isEnabledForPlatform(string $platform): bool
    {
        if (!$this->global_enabled) {
            return false;
        }

        $enabledPlatforms = $this->enabled_platforms ?? [];
        
        return in_array($platform, $enabledPlatforms, true);
    }

    public function isWithinBusinessHours(): bool
    {
        if (!$this->business_hours) {
            return true;
        }

        return true;
    }
}
