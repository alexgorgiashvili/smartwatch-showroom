<?php

namespace App\Services\Chatbot;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class ChatbotFlowInspectorService
{
    public function sessions(int $hours = 24, string $search = '', int $limit = 50): array
    {
        $safeHours = max(1, min($hours, 168));
        $safeLimit = max(10, min($limit, 200));
        $normalizedSearch = mb_strtolower(trim($search));

        $until = now();
        $since = $until->copy()->subHours($safeHours);
        $entries = $this->readEntries($since, $until);
        $groups = [];

        foreach ($entries as $entry) {
            $traceId = trim((string) ($entry['trace_id'] ?? ''));
            if ($traceId === '') {
                continue;
            }

            if ($normalizedSearch !== '') {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    (string) ($entry['step'] ?? ''),
                    (string) ($entry['context_pretty'] ?? ''),
                    (string) ($entry['trace_id'] ?? ''),
                ])));

                if (!str_contains($haystack, $normalizedSearch)) {
                    continue;
                }
            }

            $groups[$traceId][] = $entry;
        }

        $sessions = [];
        foreach ($groups as $traceId => $groupEntries) {
            usort($groupEntries, static fn (array $left, array $right): int => ((int) $left['timestamp_unix']) <=> ((int) $right['timestamp_unix']));
            $sessions[] = $this->buildSessionSummary($traceId, $groupEntries);
        }

        usort($sessions, static fn (array $left, array $right): int => strcmp((string) ($right['last_seen_at'] ?? ''), (string) ($left['last_seen_at'] ?? '')));

        return [
            'sessions' => array_slice($sessions, 0, $safeLimit),
            'meta' => [
                'hours' => $safeHours,
                'limit' => $safeLimit,
                'session_count' => count($sessions),
                'window_start' => $since->toIso8601String(),
                'window_end' => $until->toIso8601String(),
            ],
        ];
    }

    public function detail(string $traceId, int $hours = 72): ?array
    {
        $safeTraceId = trim($traceId);
        if ($safeTraceId === '') {
            return null;
        }

        $until = now();
        $since = $until->copy()->subHours(max(1, min($hours, 168)));
        $entries = array_values(array_filter(
            $this->readEntries($since, $until),
            static fn (array $entry): bool => (string) ($entry['trace_id'] ?? '') === $safeTraceId
        ));

        if ($entries === []) {
            return null;
        }

        usort($entries, static fn (array $left, array $right): int => ((int) $left['timestamp_unix']) <=> ((int) $right['timestamp_unix']));

        return [
            'trace_id' => $safeTraceId,
            'summary' => $this->buildSessionSummary($safeTraceId, $entries),
            'entries' => array_values(array_map(fn (array $entry): array => $this->decorateEntry($entry), $entries)),
        ];
    }

    private function buildSessionSummary(string $traceId, array $entries): array
    {
        $first = $entries[0] ?? [];
        $last = $entries[count($entries) - 1] ?? [];
        $agents = [];
        $question = '';
        $finalResponse = '';
        $instructions = [];
        $hasError = false;

        foreach ($entries as $entry) {
            $context = is_array($entry['context'] ?? null) ? $entry['context'] : [];

            foreach (['agent', 'agent_used'] as $key) {
                $agentValue = trim((string) ($context[$key] ?? ''));
                if ($agentValue !== '') {
                    $agents[$agentValue] = true;
                }
            }

            foreach (['incoming_message', 'sanitized_message', 'message_for_pipeline', 'question'] as $key) {
                $value = trim((string) ($context[$key] ?? ''));
                if ($question === '' && $value !== '') {
                    $question = $value;
                }
            }

            foreach (['response_message', 'bot_reply', 'supervisor_reply', 'response', 'model_reply'] as $key) {
                $value = trim((string) ($context[$key] ?? ''));
                if ($value !== '') {
                    $finalResponse = $value;
                }
            }

            foreach (['mode_instruction', 'routing_reason', 'routing_rules', 'system_prompt', 'user_context'] as $key) {
                $value = $context[$key] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    $instructions[$key] = trim($value);
                }
            }

            if (str_contains((string) ($entry['step'] ?? ''), 'failed') || array_key_exists('error', $context) || array_key_exists('fallback_reason', $context)) {
                $hasError = true;
            }
        }

        return [
            'trace_id' => $traceId,
            'conversation_id' => $last['conversation_id'] ?? $first['conversation_id'] ?? null,
            'first_seen_at' => $first['timestamp_label'] ?? null,
            'last_seen_at' => $last['timestamp_label'] ?? null,
            'step_count' => count($entries),
            'question_preview' => $question,
            'final_response_preview' => $finalResponse,
            'agents' => array_keys($agents),
            'instruction_count' => count($instructions),
            'instruction_preview' => array_slice($instructions, 0, 3, true),
            'has_error' => $hasError,
            'last_step' => $last['step'] ?? null,
        ];
    }

    private function decorateEntry(array $entry): array
    {
        $context = is_array($entry['context'] ?? null) ? $entry['context'] : [];
        $highlights = [];

        foreach (['agent', 'agent_used', 'intent', 'intent_confidence', 'model', 'fallback_reason', 'validation_passed', 'next_step'] as $key) {
            if (array_key_exists($key, $context)) {
                $highlights[$key] = $context[$key];
            }
        }

        foreach (['mode_instruction', 'routing_reason', 'routing_rules', 'system_prompt', 'user_context', 'model_reply', 'response', 'supervisor_reply', 'bot_reply'] as $key) {
            $value = $context[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $highlights[$key] = trim($value);
            }
        }

        if (is_array($context['request_payload'] ?? null)) {
            $highlights['request_payload'] = $context['request_payload'];
        }

        if (is_array($context['selected_products'] ?? null)) {
            $highlights['selected_products'] = $context['selected_products'];
        }

        return array_merge($entry, [
            'stage_group' => $this->stageGroup((string) ($entry['step'] ?? '')),
            'highlights' => $highlights,
            'highlights_pretty' => json_encode($highlights, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function stageGroup(string $step): string
    {
        return match (true) {
            str_starts_with($step, 'training.') => 'training',
            str_starts_with($step, 'widget.respond.') => 'widget',
            str_starts_with($step, 'intent.') => 'intent',
            str_starts_with($step, 'supervisor.') => 'supervisor',
            str_starts_with($step, 'inventory_agent.') => 'inventory_agent',
            str_starts_with($step, 'comparison_agent.') => 'comparison_agent',
            str_starts_with($step, 'general_agent.') => 'general_agent',
            default => 'other',
        };
    }

    private function readEntries(CarbonInterface $since, CarbonInterface $until): array
    {
        $entries = [];

        foreach ($this->resolveTraceFiles($since, $until) as $filePath) {
            $handle = @fopen($filePath, 'rb');
            if ($handle === false) {
                continue;
            }

            while (($line = fgets($handle)) !== false) {
                if (!str_contains($line, 'chatbot.widget.trace')) {
                    continue;
                }

                $entry = $this->parseTraceLine($line);
                if ($entry === null) {
                    continue;
                }

                $timestamp = Carbon::createFromTimestamp((int) $entry['timestamp_unix']);
                if ($timestamp->lt($since) || $timestamp->gt($until)) {
                    continue;
                }

                $entries[] = $entry;
            }

            fclose($handle);
        }

        return $entries;
    }

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
        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'log';
        $candidates = [];

        if (is_file($path)) {
            $candidates[] = $path;
        }

        foreach (glob($directory . DIRECTORY_SEPARATOR . $filename . '-*.' . $extension) ?: [] as $dailyFile) {
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
            if ($logDate === null || $logDate->between($windowStartDate, $windowEndDate)) {
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

        return [
            'timestamp_unix' => $timestamp->getTimestamp(),
            'timestamp_label' => $timestamp->format('Y-m-d H:i:s'),
            'step' => $step,
            'trace_id' => isset($context['trace_id']) ? (string) $context['trace_id'] : null,
            'conversation_id' => isset($context['conversation_id']) && is_numeric($context['conversation_id']) ? (int) $context['conversation_id'] : null,
            'context' => $context,
            'context_pretty' => json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ];
    }
}
