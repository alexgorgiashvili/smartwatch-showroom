<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadyGiftBoxItem extends Model
{
    protected $fillable = [
        'ready_gift_box_id',
        'product_id',
        'default_variant_id',
        'role',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function box(): BelongsTo
    {
        return $this->belongsTo(ReadyGiftBox::class, 'ready_gift_box_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function defaultVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'default_variant_id');
    }
}
