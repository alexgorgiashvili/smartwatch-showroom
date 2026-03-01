<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitorMapping extends Model
{
    protected $fillable = [
        'competitor_product_id',
        'product_id',
    ];

    public function competitorProduct(): BelongsTo
    {
        return $this->belongsTo(CompetitorProduct::class, 'competitor_product_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
