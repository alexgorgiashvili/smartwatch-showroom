<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotTrainingCase extends Model
{
    protected $fillable = [
        'title',
        'prompt',
        'conversation_context_json',
        'expected_intent',
        'expected_keywords_json',
        'expected_product_slugs_json',
        'expected_price_behavior',
        'expected_stock_behavior',
        'reviewer_notes',
        'tags_json',
        'is_active',
        'source',
        'source_reference',
        'created_by',
    ];

    protected $casts = [
        'conversation_context_json' => 'array',
        'expected_keywords_json' => 'array',
        'expected_product_slugs_json' => 'array',
        'tags_json' => 'array',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }
}
