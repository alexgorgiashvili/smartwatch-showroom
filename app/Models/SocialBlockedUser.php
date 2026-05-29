<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialBlockedUser extends Model
{
    protected $fillable = [
        'platform',
        'author_id',
        'author_name',
        'reason',
        'blocked_by',
        'blocked_at',
    ];

    protected $casts = [
        'blocked_at' => 'datetime',
    ];

    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }
}

