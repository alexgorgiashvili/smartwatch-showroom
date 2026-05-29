<?php

namespace App\Services\Chatbot;

use App\Models\ChatbotTestRun;
use App\Models\ChatbotTestResult;
use App\Models\ChatbotTrainingCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatbotLabRunService
{
    public function selectableCases()
    {
        return ChatbotTrainingCase::where('is_active', true)
            ->orderBy('title')
            ->orderBy('prompt')
            ->get();
    }

    public function runsReady()
    {
        return true;
    }

    public function casesReady()
    {
        return true;
    }

    public function queueStatus()
    {
        return [
            'can_dispatch' => true,
            'background_capable' => true,
            'driver' => config('queue.default'),
            'message' => 'Queue is ready',
        ];
    }

    public function observabilitySummary()
    {
        return [
            'recent_errors' => 0,
            'queue_depth' => 0,
        ];
    }

    public function queueRun(array $caseIds, bool $useLlmJudge = false)
    {
        return $this->startNewRun($caseIds);
    }

    public function labRunDetail($runId)
    {
        return ChatbotTestRun::with(['results.trainingCase'])
            ->findOrFail($runId);
    }

    public function statusSnapshot($run)
    {
        return [
            'id' => $run->id,
            'status' => $run->status,
            'started_at' => $run->started_at,
            'completed_at' => $run->completed_at,
            'total' => $run->results->count(),
            'passed' => $run->results->where('status', 'passed')->count(),
            'failed' => $run->results->where('status', 'failed')->count(),
            'pending' => $run->results->where('status', 'pending')->count(),
        ];
    }

    public function filteredResults($run, array $filters = [])
    {
        $query = $run->results()->with('trainingCase');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $query->where('question', 'like', '%' . $filters['search'] . '%');
        }

        return $query->paginate(20);
    }

    public function summarizeResultSignal($result)
    {
        return [
            'has_issues' => $result->status === 'failed',
            'quality_score' => $result->llm_overall ?? 0,
        ];
    }

    public function runObservabilitySnapshot($run)
    {
        return [
            'avg_response_time' => $run->results->avg('response_time_ms') ?? 0,
            'errors_count' => $run->results->where('status', 'failed')->count(),
        ];
    }

    public function cancelRun($run)
    {
        $run->update(['status' => 'cancelled']);
    }

    public function startNewRun(array $caseIds = [])
    {
        DB::beginTransaction();
        try {
            $run = ChatbotTestRun::create([
                'status' => 'running',
                'started_at' => now(),
            ]);

            $cases = empty($caseIds)
                ? ChatbotTrainingCase::where('is_active', true)->get()
                : ChatbotTrainingCase::whereIn('id', $caseIds)->get();

            foreach ($cases as $case) {
                ChatbotTestResult::create([
                    'test_run_id' => $run->id,
                    'training_case_id' => $case->id,
                    'status' => 'pending',
                ]);
            }

            DB::commit();

            return ['success' => true, 'run_id' => $run->id];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ChatbotLabRunService startNewRun failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function recentRuns($limit = 10)
    {
        return ChatbotTestRun::with(['results'])
            ->latest()
            ->limit($limit)
            ->get();
    }
}
