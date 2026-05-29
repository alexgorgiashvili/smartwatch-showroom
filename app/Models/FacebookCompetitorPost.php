<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacebookCompetitorPost extends Model
{
    protected $fillable = [
        'competitor_page_id',
        'facebook_post_id',
        'post_url',
        'posted_at',
        'scraped_at',
        'text',
        'images_json',
        'video_url',
        'likes_count',
        'comments_count',
        'shares_count',
        'reactions_json',
        'is_relevant',
        'relevance_score',
        'relevance_reason',
        'product_mentions_json',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
        'scraped_at' => 'datetime',
        'images_json' => 'array',
        'reactions_json' => 'array',
        'product_mentions_json' => 'array',
        'is_relevant' => 'boolean',
    ];

    public function competitorPage(): BelongsTo
    {
        return $this->belongsTo(FacebookCompetitorPage::class, 'competitor_page_id');
    }

    public function scopeRelevant($query)
    {
        return $query->where('is_relevant', true);
    }

    public function scopeUnfiltered($query)
    {
        return $query->whereNull('is_relevant');
    }

    public function getEngagementTotalAttribute(): int
    {
        return $this->likes_count + $this->comments_count + $this->shares_count;
    }
}
