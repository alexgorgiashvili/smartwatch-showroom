<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'name',
        'color_name',
        'color_hex',
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
