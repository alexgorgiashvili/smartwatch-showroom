<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacebookCompetitorAnalysis extends Model
{
    protected $table = 'facebook_competitor_analyses';

    protected $fillable = [
        'analysis_date',
        'analysis_type',
        'competitor_page_ids_json',
        'posts_analyzed_count',
        'competitive_intelligence_json',
        'content_strategy_json',
        'sentiment_analysis_json',
        'trend_analysis_json',
        'recommendations_json',
        'ai_model_used',
        'tokens_used',
    ];

    protected $casts = [
        'analysis_date' => 'date',
        'competitor_page_ids_json' => 'array',
        'competitive_intelligence_json' => 'array',
        'content_strategy_json' => 'array',
        'sentiment_analysis_json' => 'array',
        'trend_analysis_json' => 'array',
        'recommendations_json' => 'array',
    ];

    public function scopeLatest($query)
    {
        return $query->orderByDesc('analysis_date');
    }

    public function scopeWeekly($query)
    {
        return $query->where('analysis_type', 'weekly');
    }
}
