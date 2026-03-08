<?php

namespace App\Models;

use App\Models\ChatbotTestResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatbotTestRun extends Model
{
    protected $fillable = [
        'status',
        'total_cases',
        'passed_cases',
        'failed_cases',
        'skipped_cases',
        'accuracy_pct',
        'avg_llm_score',
        'guardrail_pass_rate',
        'duration_seconds',
        'triggered_by',
        'filters',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function results(): HasMany
    {
        return $this->hasMany(ChatbotTestResult::class, 'test_run_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isTerminal(): bool
    {
        return $this->isCompleted() || $this->isFailed() || $this->isCancelled();
    }
}
