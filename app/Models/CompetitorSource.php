<?php

namespace App\Models;

use App\Models\CompetitorProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetitorSource extends Model
{
    protected $fillable = [
        'name',
        'domain',
        'category_url',
        'is_active',
        'last_synced_at',
        'last_status',
        'last_error',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(CompetitorProduct::class);
    }
}
