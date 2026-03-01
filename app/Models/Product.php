<?php

namespace App\Models;

use App\Models\CompetitorMapping;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    protected $fillable = [
        'name_en',
        'name_ka',
        'slug',
        'external_source',
        'external_source_url',
        'external_product_id',
        'meta_title_ka',
        'meta_title_en',
        'meta_description_ka',
        'meta_description_en',
        'short_description_en',
        'short_description_ka',
        'description_en',
        'description_ka',
        'price',
        'sale_price',
        'currency',
        'sim_support',
        'gps_features',
        'water_resistant',
        'battery_life_hours',
        'warranty_months',
        'brand',
        'model',
        'memory_size',
        'operating_system',
        'screen_size',
        'display_type',
        'screen_resolution',
        'battery_capacity_mah',
        'charging_time_hours',
        'case_material',
        'band_material',
        'camera',
        'functions',
        'is_active',
        'featured',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'charging_time_hours' => 'decimal:1',
        'functions' => 'array',
        'sim_support' => 'boolean',
        'gps_features' => 'boolean',
        'is_active' => 'boolean',
        'featured' => 'boolean',
    ];

    protected $appends = ['name', 'short_description', 'description', 'meta_title', 'meta_description'];

    public function setCurrencyAttribute($value): void
    {
        $this->attributes['currency'] = 'GEL';
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true)->orderBy('sort_order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function competitorMappings(): HasMany
    {
        return $this->hasMany(\App\Models\CompetitorMapping::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('featured', true);
    }

    public function scopePriceRange(Builder $query, ?float $min = null, ?float $max = null): Builder
    {
        if ($min !== null) {
            $query->where(function ($q) use ($min) {
                $q->where('price', '>=', $min)
                    ->orWhere('sale_price', '>=', $min);
            });
        }

        if ($max !== null) {
            $query->where(function ($q) use ($max) {
                $q->where('price', '<=', $max)
                    ->orWhere('sale_price', '<=', $max);
            });
        }

        return $query;
    }

    public function scopeWithSim(Builder $query): Builder
    {
        return $query->where('sim_support', true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getNameAttribute(): ?string
    {
        return $this->localizedValue($this->name_en, $this->name_ka);
    }

    public function getMetaTitleAttribute(): ?string
    {
        $custom = $this->localizedValue($this->meta_title_en, $this->meta_title_ka);
        if ($custom) {
            return $custom;
        }
        $name = $this->localizedValue($this->name_en, $this->name_ka);
        return $name ? $name . ' — MyTechnic' : null;
    }

    public function getMetaDescriptionAttribute(): ?string
    {
        $custom = $this->localizedValue($this->meta_description_en, $this->meta_description_ka);
        if ($custom) {
            return $custom;
        }
        return $this->localizedValue($this->short_description_en, $this->short_description_ka);
    }

    public function getShortDescriptionAttribute(): ?string
    {
        return $this->localizedValue($this->short_description_en, $this->short_description_ka);
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->localizedValue($this->description_en, $this->description_ka);
    }

    private function localizedValue(?string $en, ?string $ka): ?string
    {
        $locale = app()->getLocale();

        if ($locale === 'ka') {
            return $ka ?: $en;
        }

        return $en ?: $ka;
    }
}
