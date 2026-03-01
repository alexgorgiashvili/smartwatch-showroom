<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    protected $fillable = [
        'slug',
        'title_ka',
        'title_en',
        'excerpt_ka',
        'excerpt_en',
        'body_ka',
        'body_en',
        'meta_title_ka',
        'meta_title_en',
        'meta_description_ka',
        'meta_description_en',
        'cover_image',
        'schema_type',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected $appends = ['title', 'excerpt', 'body', 'meta_title', 'meta_description'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function getTitleAttribute(): ?string
    {
        return app()->getLocale() === 'ka'
            ? ($this->title_ka ?: $this->title_en)
            : ($this->title_en ?: $this->title_ka);
    }

    public function getExcerptAttribute(): ?string
    {
        return app()->getLocale() === 'ka'
            ? ($this->excerpt_ka ?: $this->excerpt_en)
            : ($this->excerpt_en ?: $this->excerpt_ka);
    }

    public function getBodyAttribute(): ?string
    {
        return app()->getLocale() === 'ka'
            ? ($this->body_ka ?: $this->body_en)
            : ($this->body_en ?: $this->body_ka);
    }

    public function getMetaTitleAttribute(): ?string
    {
        $custom = app()->getLocale() === 'ka'
            ? ($this->meta_title_ka ?: $this->meta_title_en)
            : ($this->meta_title_en ?: $this->meta_title_ka);

        return $custom ?: ($this->title . ' — MyTechnic');
    }

    public function getMetaDescriptionAttribute(): ?string
    {
        $custom = app()->getLocale() === 'ka'
            ? ($this->meta_description_ka ?: $this->meta_description_en)
            : ($this->meta_description_en ?: $this->meta_description_ka);

        return $custom ?: $this->excerpt;
    }
}
