<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacebookCompetitorInsight extends Model
{
    protected $fillable = [
        'insight_type',
        'priority',
        'status',
        'title',
        'description',
        'data_json',
        'competitor_page_id',
        'related_post_ids_json',
        'acknowledged_at',
        'actioned_at',
    ];

    protected $casts = [
        'data_json' => 'array',
        'related_post_ids_json' => 'array',
        'acknowledged_at' => 'datetime',
        'actioned_at' => 'datetime',
    ];

    public function competitorPage(): BelongsTo
    {
        return $this->belongsTo(FacebookCompetitorPage::class, 'competitor_page_id');
    }

    public function scopeNew($query)
    {
        return $query->where('status', 'new');
    }

    public function scopeHighPriority($query)
    {
        return $query->where('priority', 'high');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['new', 'acknowledged']);
    }

    public function acknowledge(): void
    {
        $this->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
        ]);
    }

    public function markActioned(): void
    {
        $this->update([
            'status' => 'actioned',
            'actioned_at' => now(),
        ]);
    }
}
