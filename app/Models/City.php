<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    protected $fillable = [
        'name',
        'state_id',
        'country_id',
        'cost',
        'latitude',
        'longitude',
        'status',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
