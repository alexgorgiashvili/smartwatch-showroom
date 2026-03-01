<?php

namespace App\Models;

use App\Models\CompetitorMapping;
use App\Models\CompetitorProductSnapshot;
use App\Models\CompetitorSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CompetitorProduct extends Model
{
    protected $fillable = [
        'competitor_source_id',
        'external_product_id',
        'product_url_hash',
        'product_url',
        'title',
        'image_url',
        'current_price',
        'old_price',
        'currency',
        'availability',
        'is_in_stock',
        'last_seen_at',
    ];

    protected $casts = [
        'current_price' => 'decimal:2',
        'old_price' => 'decimal:2',
        'is_in_stock' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(CompetitorSource::class, 'competitor_source_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(CompetitorProductSnapshot::class);
    }

    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(CompetitorProductSnapshot::class)->latestOfMany('captured_at');
    }

    public function mapping(): HasOne
    {
        return $this->hasOne(CompetitorMapping::class);
    }
}
