<?php

namespace App\Services\Chatbot;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class WidgetTraceReadService
{
    private const DEFAULT_HOURS = 24;

    private const MAX_HOURS = 168;

    private const DEFAULT_LIMIT = 300;

    private const MAX_LIMIT = 1000;

    /**
     * @return array{
     *     entries: array<int, array<string, mixed>>,
     *     summary: array<string, mixed>,
     *     meta: array<string, mixed>
     * }
     */
    public function pipelineSnapshot(
        int $hours = self::DEFAULT_HOURS,
        string $stepSearch = '',
        bool $fallbackOnly = false,
        bool $multiAgentOnly = false,
        int $limit = self::DEFAULT_LIMIT
    ): array {
        $safeHours = max(1, min($hours, self::MAX_HOURS));
        $safeLimit = max(50, min($limit, self::MAX_LIMIT));
        $normalizedSearch = mb_strtolower(trim($stepSearch));

        $until = now();
        $since = $until->copy()->subHours($safeHours);

        $entries = [];
        $matchedLines = 0;
        $files = $this->resolveTraceFiles($since, $until);

        foreach ($files as $filePath) {
            $handle = @fopen($filePath, 'rb');
            if ($handle === false) {
                continue;
            }

            while (($line = fgets($handle)) !== false) {
                if (!str_contains($line, 'chatbot.widget.trace')) {
                    continue;
                }

                $matchedLines++;

                $entry = $this->parseTraceLine($line);
                if ($entry === null) {
                    continue;
                }

                if (!str_starts_with((string) $entry['step'], 'pipeline.')) {
                    continue;
                }

                $entryTimestamp = Carbon::createFromTimestamp((int) $entry['timestamp_unix']);
                if ($entryTimestamp->lt($since) || $entryTimestamp->gt($until)) {
                    continue;
                }

                if ($normalizedSearch !== '' && !str_contains((string) $entry['step_lc'], $normalizedSearch)) {
                    continue;
                }

                if ($fallbackOnly && !$entry['has_fallback']) {
                    continue;
                }

                if ($multiAgentOnly && !$entry['is_multi_agent']) {
                    continue;
                }

                $entries[] = $entry;
            }

            fclose($handle);
        }

        usort($entries, static function (array $left, array $right): int {
            return ((int) $right['timestamp_unix']) <=> ((int) $left['timestamp_unix']);
        });

        if (count($entries) > $safeLimit) {
            $entries = array_slice($entries, 0, $safeLimit);
        }

        return [
            'entries' => $entries,
            'summary' => $this->buildSummary($entries),
            'meta' => [
                'hours' => $safeHours,
                'limit' => $safeLimit,
                'files_count' => count($files),
                'matched_log_lines' => $matchedLines,
                'entries_count' => count($entries),
                'window_start' => $since->toIso8601String(),
                'window_end' => $until->toIso8601String(),
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function resolveTraceFiles(CarbonInterface $since, CarbonInterface $until): array
    {
        $channel = trim((string) config('chatbot-monitoring.widget_trace.channel', 'chatbot_widget_trace'));
        if ($channel === '') {
            $channel = 'chatbot_widget_trace';
        }

        $path = (string) data_get(config('logging.channels'), $channel . '.path', storage_path('logs/chatbot-widget-trace.log'));
        if (trim($path) === '') {
            $path = storage_path('logs/chatbot-widget-trace.log');
        }

        $directory = dirname($path);
        $filename = pathinfo($path, PATHINFO_FILENAME);
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $extension = $extension !== '' ? $extension : 'log';

        $candidates = [];

        if (is_file($path)) {
            $candidates[] = $path;
        }

        $dailyPattern = $directory . DIRECTORY_SEPARATOR . $filename . '-*.' . $extension;
        foreach (glob($dailyPattern) ?: [] as $dailyFile) {
            if (is_file($dailyFile)) {
                $candidates[] = $dailyFile;
            }
        }

        $candidates = array_values(array_unique($candidates));

        $windowStartDate = Carbon::instance($since)->copy()->startOfDay();
        $windowEndDate = Carbon::instance($until)->copy()->endOfDay();
        $result = [];

        foreach ($candidates as $candidate) {
            $logDate = $this->extractDateFromDailyFilename($candidate, $filename, $extension);
            if ($logDate === null) {
                $result[] = $candidate;
                continue;
            }

            if ($logDate->between($windowStartDate, $windowEndDate)) {
                $result[] = $candidate;
            }
        }

        sort($result);

        return $result;
    }

    private function extractDateFromDailyFilename(string $filePath, string $filename, string $extension): ?Carbon
    {
        $basename = basename($filePath);
        $pattern = '/^' . preg_quote($filename, '/') . '-(\d{4}-\d{2}-\d{2})\.' . preg_quote($extension, '/') . '$/';

        if (!preg_match($pattern, $basename, $matches)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', (string) $matches[1])->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseTraceLine(string $line): ?array
    {
        $pattern = '/^\[(?<timestamp>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\].*?chatbot\.widget\.trace\s+(?<payload>\{.*\})\s*$/u';
        if (!preg_match($pattern, trim($line), $matches)) {
            return null;
        }

        try {
            $timestamp = Carbon::createFromFormat('Y-m-d H:i:s', (string) $matches['timestamp']);
        } catch (\Throwable) {
            return null;
        }

        $decoded = json_decode((string) $matches['payload'], true);
        if (!is_array($decoded)) {
            return null;
        }

        $step = trim((string) ($decoded['step'] ?? ''));
        if ($step === '') {
            return null;
        }

        $context = is_array($decoded['context'] ?? null) ? $decoded['context'] : [];
        $responseTimeMs = is_numeric($context['response_time_ms'] ?? null)
            ? (int) round((float) $context['response_time_ms'])
            : null;

        $validationPassed = null;
        if (array_key_exists('validation_passed', $context)) {
            $validationPassed = (bool) $context['validation_passed'];
        }

        $fallbackReason = null;
        if (array_key_exists('fallback_reason', $context)) {
            $fallbackCandidate = trim((string) $context['fallback_reason']);
            $fallbackReason = $fallbackCandidate !== '' ? $fallbackCandidate : null;
        }

        $nextStep = null;
        if (array_key_exists('next_step', $context)) {
            $nextStepCandidate = trim((string) $context['next_step']);
            $nextStep = $nextStepCandidate !== '' ? $nextStepCandidate : null;
        }

        return [
            'timestamp_unix' => $timestamp->getTimestamp(),
            'timestamp_label' => $timestamp->format('Y-m-d H:i:s'),
            'step' => $step,
            'step_lc' => mb_strtolower($step),
            'trace_id' => isset($context['trace_id']) ? (string) $context['trace_id'] : null,
            'conversation_id' => isset($context['conversation_id']) && is_numeric($context['conversation_id'])
                ? (int) $context['conversation_id']
                : null,
            'response_time_ms' => $responseTimeMs,
            'validation_passed' => $validationPassed,
            'fallback_reason' => $fallbackReason,
            'next_step' => $nextStep,
            'is_multi_agent' => str_contains($step, 'multi_agent'),
            'has_fallback' => $fallbackReason !== null,
            'context' => $context,
            'context_pretty' => json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     * @return array<string, mixed>
     */
    private function buildSummary(array $entries): array
    {
        $total = count($entries);
        $traceIds = [];
        $latencyValues = [];
        $completedValidationTotal = 0;
        $completedValidationPassed = 0;
        $multiStarted = 0;
        $multiCompleted = 0;
        $multiFailed = 0;

        foreach ($entries as $entry) {
            $traceId = trim((string) ($entry['trace_id'] ?? ''));
            if ($traceId !== '') {
                $traceIds[$traceId] = true;
            }

            if (($entry['step'] ?? '') === 'pipeline.completed') {
                if (is_int($entry['response_time_ms'] ?? null)) {
                    $latencyValues[] = (int) $entry['response_time_ms'];
                }

                if (array_key_exists('validation_passed', $entry) && $entry['validation_passed'] !== null) {
                    $completedValidationTotal++;
                    if ((bool) $entry['validation_passed']) {
                        $completedValidationPassed++;
                    }
                }
            }

            if (($entry['step'] ?? '') === 'pipeline.multi_agent_started') {
                $multiStarted++;
            }

            if (($entry['step'] ?? '') === 'pipeline.multi_agent_completed') {
                $multiCompleted++;
            }

            if (($entry['step'] ?? '') === 'pipeline.multi_agent_failed') {
                $multiFailed++;
            }
        }

        $avgLatency = $latencyValues !== []
            ? (int) round(array_sum($latencyValues) / count($latencyValues))
            : 0;

        $validationRate = $completedValidationTotal > 0
            ? round(($completedValidationPassed / $completedValidationTotal) * 100, 2)
            : 0.0;

        return [
            'total_pipeline_steps' => $total,
            'unique_trace_ids' => count($traceIds),
            'avg_response_time_ms' => $avgLatency,
            'validation_pass_rate' => $validationRate,
            'multi_agent_started' => $multiStarted,
            'multi_agent_completed' => $multiCompleted,
            'multi_agent_failed' => $multiFailed,
        ];
    }
}
