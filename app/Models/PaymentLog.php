<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentLog extends Model
{
    protected $fillable = [
        'order_id',
        'bog_order_id',
        'external_order_id',
        'status',
        'chveni_statusi',
        'payment_detail',
    ];

    protected $casts = [
        'payment_detail' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
