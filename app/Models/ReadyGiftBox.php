<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class ReadyGiftBox extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'slug',
        'title_ka',
        'title_en',
        'short_description_ka',
        'short_description_en',
        'badge_ka',
        'badge_en',
        'cover_image_path',
        'theme_key',
        'packaging_slug',
        'discount_type',
        'discount_value',
        'is_active',
        'is_featured',
        'sort_order',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReadyGiftBoxItem::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function mainItem(): HasOne
    {
        return $this->hasOne(ReadyGiftBoxItem::class)->where('role', 'main');
    }

    public function addonItems(): HasMany
    {
        return $this->hasMany(ReadyGiftBoxItem::class)
            ->where('role', 'addon')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getTitleAttribute(): string
    {
        return $this->localizedValue($this->title_ka, $this->title_en, $this->slug);
    }

    public function getShortDescriptionAttribute(): string
    {
        return $this->localizedValue($this->short_description_ka, $this->short_description_en);
    }

    public function getBadgeAttribute(): string
    {
        return $this->localizedValue($this->badge_ka, $this->badge_en);
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        $path = trim((string) $this->cover_image_path);
        if ($path === '') {
            return null;
        }

        return Storage::disk('public')->url(ltrim($path, '/'));
    }

    public function getHeroImageUrlAttribute(): ?string
    {
        return $this->cover_image_url;
    }

    private function localizedValue(?string $ka, ?string $en, string $fallback = ''): string
    {
        if (app()->getLocale() === 'en') {
            return trim((string) ($en ?: $ka ?: $fallback));
        }

        return trim((string) ($ka ?: $en ?: $fallback));
    }
}
