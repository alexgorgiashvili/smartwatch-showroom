<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'last_seen_at',
        'is_online',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'is_online' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'assigned_to');
    }

    public function isOnline(): bool
    {
        return $this->is_online ||
               ($this->last_seen_at && $this->last_seen_at->gt(now()->subMinutes(5)));
    }

    public function markAsOnline(): void
    {
        $this->update([
            'is_online' => true,
            'status' => 'online',
            'last_seen_at' => now(),
        ]);
    }

    public function markAsOffline(): void
    {
        $this->update([
            'is_online' => false,
            'status' => 'offline',
            'last_seen_at' => now(),
        ]);
    }

    public function setStatus(string $status): void
    {
        $this->update([
            'status' => $status,
            'last_seen_at' => now(),
        ]);
    }
}
