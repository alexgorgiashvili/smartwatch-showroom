<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacebookCompetitorPage extends Model
{
    protected $fillable = [
        'name',
        'facebook_url',
        'page_id',
        'category',
        'is_active',
        'scraping_frequency',
        'last_scraped_at',
        'total_posts_count',
        'relevant_posts_count',
        'avg_engagement_rate',
        'follower_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_scraped_at' => 'datetime',
        'avg_engagement_rate' => 'decimal:3',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(FacebookCompetitorPost::class, 'competitor_page_id');
    }

    public function insights(): HasMany
    {
        return $this->hasMany(FacebookCompetitorInsight::class, 'competitor_page_id');
    }

    public function relevantPosts(): HasMany
    {
        return $this->posts()->where('is_relevant', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDueForScraping($query)
    {
        return $query->active()->where(function ($q) {
            $q->whereNull('last_scraped_at')
              ->orWhere('last_scraped_at', '<', now()->subDay());
        });
    }
}
