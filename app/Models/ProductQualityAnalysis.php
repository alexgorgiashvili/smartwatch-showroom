<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductQualityAnalysis extends Model
{
    protected $fillable = [
        'research_target_id',
        'product_id',
        'status',
        'model_used',
        'evidence_count',
        'end_user_evidence_count',
        'supplier_evidence_count',
        'confidence_score',
        'verdict',
        'summary_json',
        'comparison_ready_payload',
        'error_message',
    ];

    protected $casts = [
        'confidence_score' => 'decimal:2',
        'summary_json' => 'array',
        'comparison_ready_payload' => 'array',
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
