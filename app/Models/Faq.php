<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    protected $fillable = [
        'question',
        'question_en',
        'answer',
        'answer_en',
        'category',
        'category_en',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function localizedQuestion(?string $locale = null): ?string
    {
        return $this->localizedValue('question', $locale);
    }

    public function localizedAnswer(?string $locale = null): ?string
    {
        return $this->localizedValue('answer', $locale);
    }

    public function localizedCategory(?string $locale = null): ?string
    {
        return $this->localizedValue('category', $locale);
    }

    private function localizedValue(string $attribute, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $value = $locale === 'en'
            ? $this->getAttribute($attribute . '_en')
            : $this->getAttribute($attribute);

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
