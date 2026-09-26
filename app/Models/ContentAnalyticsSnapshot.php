<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentAnalyticsSnapshot extends Model
{
    protected $fillable = ['content_campaign_id', 'content_item_id', 'source', 'window_days', 'metrics', 'collected_at', 'status', 'error'];
    protected $casts = ['metrics' => 'array', 'collected_at' => 'datetime'];
}
