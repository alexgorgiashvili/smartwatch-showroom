<?php

namespace App\Services\Chatbot;

use App\Models\ChatbotTestResult;
use Illuminate\Support\Str;

class AdaptiveLearningService
{
    public function buildLessonsText(?int $limit = null): string
    {
        if (!(bool) config('chatbot-learning.enabled', true)) {
            return '';
        }

        $maxLessons = $limit ?? (int) config('chatbot-learning.max_lessons', 6);
        $maxLessons = max(1, min(20, $maxLessons));

        $rows = ChatbotTestResult::query()
            ->where('status', 'fail')
            ->where(function ($query): void {
                $query->whereNotNull('admin_feedback')
                    ->where('admin_feedback', '!=', '')
                    ->orWhere(function ($subQuery): void {
                        $subQuery->whereNotNull('expected_summary')
                            ->where('expected_summary', '!=', '');
                    });
            })
            ->orderByRaw("CASE WHEN admin_feedback IS NOT NULL AND admin_feedback != '' THEN 0 ELSE 1 END")
            ->latest('id')
            ->take($maxLessons)
            ->get(['question', 'admin_feedback', 'expected_summary', 'actual_response']);

        if ($rows->isEmpty()) {
            return '';
        }

        $lines = [
            'SELF-IMPROVEMENT LESSONS (historic failed test cases):',
            'Use these lessons as hard constraints when generating answer quality, style, and factual behavior.',
        ];

        $index = 1;
        foreach ($rows as $row) {
            $question = trim((string) $row->question);
            $correction = trim((string) ($row->admin_feedback ?: $row->expected_summary));
            $previous = trim((string) $row->actual_response);

            if ($correction === '') {
                continue;
            }

            $lines[] = $index . '. User intent: ' . Str::limit($question, 180);
            $lines[] = '   Correct behavior: ' . Str::limit($correction, 260);

            if ($previous !== '') {
                $lines[] = '   Previous wrong output (avoid repeating): ' . Str::limit($previous, 180);
            }

            $index++;
        }

        if ($index === 1) {
            return '';
        }

        return implode("\n", $lines);
    }
}
