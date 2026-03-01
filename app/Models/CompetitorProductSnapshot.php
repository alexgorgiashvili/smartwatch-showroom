<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitorProductSnapshot extends Model
{
    protected $fillable = [
        'competitor_product_id',
        'title',
        'image_url',
        'price',
        'old_price',
        'currency',
        'availability',
        'is_in_stock',
        'captured_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'old_price' => 'decimal:2',
        'is_in_stock' => 'boolean',
        'captured_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(CompetitorProduct::class, 'competitor_product_id');
    }
}
