<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ContentRevision extends Model
{
    protected $fillable = ['content_item_id', 'revision_number', 'fingerprint', 'payload', 'evidence', 'validation', 'submitted_by_user_id', 'submitted_at'];
    protected $casts = ['payload' => 'array', 'evidence' => 'array', 'validation' => 'array', 'submitted_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Content revisions are immutable. Create a new revision.'));
        static::deleting(fn () => throw new LogicException('Content revisions are immutable.'));
    }

    public function item(): BelongsTo { return $this->belongsTo(ContentItem::class, 'content_item_id'); }
    public function submittedBy(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by_user_id'); }
}
