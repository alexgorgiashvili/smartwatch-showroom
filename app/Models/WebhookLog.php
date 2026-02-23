<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    protected $fillable = [
        'platform',
        'event_type',
        'payload',
        'verified',
        'processed',
        'error',
    ];

    protected $casts = [
        'payload' => 'array',
        'verified' => 'boolean',
        'processed' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Mark webhook log as verified
     */
    public function markAsVerified()
    {
        $this->update(['verified' => true]);
    }

    /**
     * Mark webhook log as processed
     */
    public function markAsProcessed()
    {
        $this->update(['processed' => true]);
    }

    /**
     * Mark webhook with error
     */
    public function markWithError($error)
    {
        $this->update([
            'processed' => true,
            'error' => $error,
        ]);
    }

    /**
     * Scope to unprocessed webhooks
     */
    public function scopeUnprocessed($query)
    {
        return $query->where('processed', false);
    }

    /**
     * Scope to verified webhooks
     */
    public function scopeVerified($query)
    {
        return $query->where('verified', true);
    }

    /**
     * Scope to specific platform
     */
    public function scopePlatform($query, $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope to specific event type
     */
    public function scopeEventType($query, $eventType)
    {
        return $query->where('event_type', $eventType);
    }
}
