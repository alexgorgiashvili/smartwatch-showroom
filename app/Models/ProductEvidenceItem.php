<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductEvidenceItem extends Model
{
    protected $fillable = [
        'research_target_id',
        'product_id',
        'source_type',
        'source_url',
        'source_item_id',
        'author_name',
        'author_type',
        'rating_raw',
        'title',
        'body_text',
        'language',
        'published_at',
        'country',
        'credibility_weight',
        'dedupe_hash',
        'raw_payload',
        'normalized_payload',
    ];

    protected $casts = [
        'rating_raw' => 'decimal:2',
        'credibility_weight' => 'decimal:2',
        'published_at' => 'datetime',
        'raw_payload' => 'array',
        'normalized_payload' => 'array',
    ];

    public function researchTarget(): BelongsTo
    {
        return $this->belongsTo(ResearchTarget::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
