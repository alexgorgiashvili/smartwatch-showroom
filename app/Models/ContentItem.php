<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentItem extends Model
{
    public const CHANNELS = ['article', 'facebook', 'instagram'];
    public const STATES = ['generated', 'in_review', 'changes_requested', 'ready_for_owner', 'approved', 'scheduled', 'publishing', 'published', 'failed', 'rejected'];

    protected $fillable = ['campaign_id', 'channel', 'status', 'fingerprint', 'title', 'current_revision_id', 'approved_revision_id', 'published_record_id', 'published_record_type', 'approved_at', 'scheduled_at', 'published_at', 'verification_checked_at', 'evidence', 'review_metadata', 'reviewer_notes', 'last_error'];
    protected $casts = ['approved_at' => 'datetime', 'scheduled_at' => 'datetime', 'published_at' => 'datetime', 'verification_checked_at' => 'datetime', 'evidence' => 'array', 'review_metadata' => 'array'];

    public function campaign(): BelongsTo { return $this->belongsTo(ContentCampaign::class, 'campaign_id'); }
    public function revisions(): HasMany { return $this->hasMany(ContentRevision::class); }
    public function audits(): HasMany { return $this->hasMany(ContentAuditEvent::class); }
    public function assets(): HasMany { return $this->hasMany(ContentAsset::class); }
    public function currentRevision(): BelongsTo { return $this->belongsTo(ContentRevision::class, 'current_revision_id'); }
    public function approvedRevision(): BelongsTo { return $this->belongsTo(ContentRevision::class, 'approved_revision_id'); }
}
