<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ContentAuditEvent extends Model
{
    public $timestamps = false;
    protected $fillable = ['content_item_id', 'content_revision_id', 'actor_id', 'event', 'from_status', 'to_status', 'metadata', 'occurred_at'];
    protected $casts = ['metadata' => 'array', 'occurred_at' => 'datetime'];
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Content audit events are immutable.'));
        static::deleting(fn () => throw new LogicException('Content audit events are immutable.'));
    }
    public function item(): BelongsTo { return $this->belongsTo(ContentItem::class, 'content_item_id'); }
    public function revision(): BelongsTo { return $this->belongsTo(ContentRevision::class, 'content_revision_id'); }
}
