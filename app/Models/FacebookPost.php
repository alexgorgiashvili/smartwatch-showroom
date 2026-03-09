<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacebookPost extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'message',
        'image_url',
        'post_to_facebook',
        'post_to_instagram',
        'facebook_post_id',
        'instagram_post_id',
        'status',
        'ai_prompt',
        'error_message',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
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
}
