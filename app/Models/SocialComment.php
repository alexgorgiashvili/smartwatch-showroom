<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialComment extends Model
{
    protected $fillable = [
        'facebook_post_id',
        'platform',
        'platform_comment_id',
        'platform_post_id',
        'parent_comment_id',
        'author_name',
        'author_id',
        'message',
        'sentiment',
        'status',
        'ai_suggested_reply',
        'actual_reply',
        'reply_platform_id',
        'auto_reply_rule_id',
        'auto_replied_at',
        'auto_reply_error',
        'commented_at',
        'replied_at',
    ];

    protected $casts = [
        'commented_at' => 'datetime',
        'replied_at' => 'datetime',
        'auto_replied_at' => 'datetime',
    ];

    public function facebookPost(): BelongsTo
    {
        return $this->belongsTo(FacebookPost::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_comment_id', 'platform_comment_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_comment_id', 'platform_comment_id');
    }

    public function scopeUnread($query)
    {
        return $query->where('status', 'unread');
    }

    public function scopeUnreplied($query)
    {
        return $query->whereIn('status', ['unread', 'read']);
    }

    public function scopeByPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeSpam($query)
    {
        return $query->where('status', 'spam');
    }
}
