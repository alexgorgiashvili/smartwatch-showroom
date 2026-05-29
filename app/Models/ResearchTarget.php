<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ResearchTarget extends Model
{
    protected $fillable = [
        'product_id',
        'mode',
        'source_url',
        'external_source',
        'external_product_id',
        'brand',
        'model',
        'name',
        'identity_payload',
    ];

    protected $casts = [
        'identity_payload' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function evidenceItems(): HasMany
    {
        return $this->hasMany(ProductEvidenceItem::class);
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(ProductQualityAnalysis::class);
    }

    public function latestAnalysis(): HasOne
    {
        return $this->hasOne(ProductQualityAnalysis::class)->latestOfMany();
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->product?->name) {
            return (string) $this->product->name;
        }

        return (string) ($this->name ?: trim(implode(' ', array_filter([$this->brand, $this->model]))) ?: 'Untitled research target');
    }
}
