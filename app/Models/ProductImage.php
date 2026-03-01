<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'path',
        'thumbnail_path',
        'alt_en',
        'alt_ka',
        'sort_order',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = ['alt', 'url', 'thumbnail_url'];

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

        $normalizedPath = ltrim($this->path ?? '', '/');
        if (str_starts_with($normalizedPath, 'storage/')) {
            $normalizedPath = substr($normalizedPath, 8);
        }

        return Storage::url($normalizedPath);
    }

    public function getThumbnailUrlAttribute(): string
    {
        $path = $this->thumbnail_path ?: $this->path;

        if (str_starts_with((string) $path, 'http')) {
            return (string) $path;
        }

        $normalizedPath = ltrim((string) $path, '/');
        if (str_starts_with($normalizedPath, 'storage/')) {
            $normalizedPath = substr($normalizedPath, 8);
        }

        return Storage::url($normalizedPath);
    }
}
