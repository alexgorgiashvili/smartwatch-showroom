<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacebookPost extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'message',
        'image_url',
        'media_type',
        'video_url',
        'post_to_facebook',
        'post_to_instagram',
        'facebook_post_id',
        'instagram_post_id',
        'facebook_publish_status',
        'instagram_container_id',
        'instagram_publish_status',
        'facebook_error',
        'instagram_error',
        'last_publish_check_at',
        'status',
        'ai_prompt',
        'error_message',
        'published_at',
        'scheduled_at',
        'fb_reactions_count',
        'fb_shares_count',
        'fb_impressions',
        'ig_likes_count',
        'ig_reach',
        'metrics_fetched_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'last_publish_check_at' => 'datetime',
        'metrics_fetched_at' => 'datetime',
        'post_to_facebook' => 'boolean',
        'post_to_instagram' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled')->where('scheduled_at', '<=', now());
    }

    public function comments(): HasMany
    {
        return $this->hasMany(SocialComment::class);
    }
}
