<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'path',
        'alt_en',
        'alt_ka',
        'sort_order',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = ['alt', 'url'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getAltAttribute(): ?string
    {
        $locale = app()->getLocale();

        if ($locale === 'ka') {
            return $this->alt_ka ?: $this->alt_en;
        }

        return $this->alt_en ?: $this->alt_ka;
    }

    public function getUrlAttribute(): string
    {
        if (str_starts_with($this->path, 'http')) {
            return $this->path;
        }

        return asset($this->path);
    }
}
