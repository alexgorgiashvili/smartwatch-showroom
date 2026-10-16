<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftBuilderSetting extends Model
{
    public $incrementing = false;

    protected $table = 'gift_builder_settings';

    protected $fillable = [
        'id',
        'packaging_options',
        'delivery_fee',
        'free_delivery_threshold',
        'ad_cost_per_order',
        'courier_cost_per_order',
    ];

    protected $casts = [
        'packaging_options' => 'array',
        'delivery_fee' => 'decimal:2',
        'free_delivery_threshold' => 'decimal:2',
        'ad_cost_per_order' => 'decimal:2',
        'courier_cost_per_order' => 'decimal:2',
    ];
}
