<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ContentCampaign extends Model
{
    use HasFactory;

    protected $fillable = ['uuid', 'name', 'week_key', 'fingerprint', 'utm', 'metadata'];
    protected $casts = ['utm' => 'array', 'metadata' => 'array'];

    protected static function booted(): void
    {
        static::creating(function (self $campaign): void {
            $campaign->uuid ??= (string) Str::uuid();
        });
    }

    public function items(): HasMany { return $this->hasMany(ContentItem::class, 'campaign_id'); }
    public function snapshots(): HasMany { return $this->hasMany(ContentAnalyticsSnapshot::class, 'content_campaign_id'); }
}
