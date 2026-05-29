<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAutoReplyRule extends Model
{
    protected $fillable = [
        'facebook_post_id',
        'user_id',
        'match_type',
        'match_value',
        'use_ai',
        'reply_template',
        'enabled',
        'max_replies_per_author_per_day',
    ];

    protected $casts = [
        'use_ai' => 'boolean',
        'enabled' => 'boolean',
    ];

    public function facebookPost(): BelongsTo
    {
        return $this->belongsTo(FacebookPost::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

