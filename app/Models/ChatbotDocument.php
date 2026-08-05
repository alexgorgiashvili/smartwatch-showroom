<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotDocument extends Model
{
    protected $fillable = [
        'key',
        'type',
        'title',
        'title_en',
        'content_ka',
        'content_en',
        'product_id',
        'metadata',
        'pinecone_id',
        'is_active',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function localizedTitle(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $value = $locale === 'en' ? $this->title_en : $this->title;

        return filled($value) ? (string) $value : null;
    }

    public function localizedContent(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $value = $locale === 'en' ? $this->content_en : $this->content_ka;

        return filled($value) ? (string) $value : null;
    }
}
