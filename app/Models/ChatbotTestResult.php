<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotTestResult extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'test_run_id',
        'case_id',
        'category',
        'question',
        'expected_summary',
        'actual_response',
        'rag_context',
        'intent_json',
        'standalone_query',
        'intent_type',
        'intent_confidence',
        'intent_latency_ms',
        'status',
        'keyword_match',
        'price_match',
        'stock_match',
        'guardrail_passed',
        'georgian_qa_passed',
        'intent_match',
        'entity_match',
        'llm_accuracy',
        'llm_relevance',
        'llm_grammar',
        'llm_completeness',
        'llm_safety',
        'llm_overall',
        'llm_notes',
        'response_time_ms',
        'fallback_reason',
        'regeneration_attempted',
        'regeneration_succeeded',
        'admin_feedback',
        'retrain_status',
        'created_at',
    ];

    protected $casts = [
        'keyword_match' => 'boolean',
        'price_match' => 'boolean',
        'stock_match' => 'boolean',
        'guardrail_passed' => 'boolean',
        'georgian_qa_passed' => 'boolean',
        'intent_match' => 'boolean',
        'entity_match' => 'boolean',
        'intent_json' => 'array',
        'intent_confidence' => 'decimal:4',
        'intent_latency_ms' => 'integer',
        'llm_accuracy' => 'integer',
        'llm_relevance' => 'integer',
        'llm_grammar' => 'integer',
        'llm_completeness' => 'integer',
        'llm_safety' => 'integer',
        'llm_overall' => 'decimal:1',
        'response_time_ms' => 'integer',
        'regeneration_attempted' => 'boolean',
        'regeneration_succeeded' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function testRun(): BelongsTo
    {
        return $this->belongsTo(ChatbotTestRun::class, 'test_run_id');
    }

    public function scopePassed(Builder $query): Builder
    {
        return $query->where('status', 'pass');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'fail');
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function isPassed(): bool
    {
        return $this->status === 'pass';
    }

    public function isFailed(): bool
    {
        return $this->status === 'fail';
    }

    public function llmScoresArray(): array
    {
        return [
            'accuracy' => $this->llm_accuracy,
            'relevance' => $this->llm_relevance,
            'georgian_grammar' => $this->llm_grammar,
            'completeness' => $this->llm_completeness,
            'safety' => $this->llm_safety,
            'overall' => $this->llm_overall,
            'notes' => $this->llm_notes,
        ];
    }
}
