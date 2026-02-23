<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inquiry extends Model
{
    protected $fillable = [
        'product_id',
        'selected_color',
        'name',
        'phone',
        'email',
        'message',
        'preferred_contact',
        'locale',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
