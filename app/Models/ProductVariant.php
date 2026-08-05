<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'name',
        'name_en',
        'color_name',
        'color_name_en',
        'color_hex',
        'is_listed_separately',
        'quantity',
        'low_stock_threshold',
        'bridge_variation_id',
        'bridge_sku',
        'bridge_stock_quantity',
        'bridge_stock_status',
        'stock_sync_status',
        'stock_synced_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'low_stock_threshold' => 'integer',
        'is_listed_separately' => 'boolean',
        'bridge_stock_quantity' => 'integer',
        'stock_synced_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'product_variant_id');
    }

    public function images(): BelongsToMany
    {
        return $this->belongsToMany(ProductImage::class, 'product_variant_images')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('product_variant_images.sort_order');
    }

    public function isLowStock(): bool
    {
        return $this->available_quantity <= $this->low_stock_threshold;
    }

    public function isOutOfStock(): bool
    {
        return $this->available_quantity <= 0;
    }

    public function hasColor(): bool
    {
        return filled($this->color_name) && filled($this->color_hex);
    }

    public function localizedName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'en'
            ? ((string) ($this->name_en ?: __('storefront.common.color_variant')))
            : (string) $this->name;
    }

    public function localizedColorName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if ($locale === 'en') {
            return (string) ($this->color_name_en ?: (filled($this->color_hex) ? __('storefront.common.color') : ''));
        }

        return (string) ($this->color_name ?? '');
    }

    public function getAvailableQuantityAttribute(): int
    {
        $product = $this->relationLoaded('product') ? $this->getRelation('product') : $this->product;

        if ($product?->isDropship()) {
            if (($this->bridge_stock_status ?? null) === 'outofstock') {
                return 0;
            }

            return max(0, (int) ($this->bridge_stock_quantity ?? 0));
        }

        return max(0, (int) $this->quantity);
    }

    public function canFulfillQuantity(int $quantity): bool
    {
        return $this->available_quantity >= max(1, $quantity);
    }
}
