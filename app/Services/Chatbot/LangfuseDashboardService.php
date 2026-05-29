<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LangfuseDashboardService
{
    public function snapshot(int $hours = 24, int $limit = 200): array
    {
        $hours = max(1, min($hours, 168));
        $limit = max(20, min($limit, 300));
        $windowEnd = now()->utc();
        $windowStart = $windowEnd->copy()->subHours($hours);

        if (!$this->enabled()) {
            return $this->emptySnapshot($windowStart, $windowEnd, false, false, 'Langfuse არ არის კონფიგურირებული.');
        }

        try {
            $observations = collect($this->fetchObservations($windowStart, $windowEnd, $limit));
            $summary = $this->buildSummary($observations);
            $recentTraces = $this->buildRecentTraces($observations);

            return [
                'enabled' => true,
                'connected' => true,
                'error' => null,
                'base_url' => $this->baseUrl(),
                'summary' => $summary,
                'health' => $this->buildHealth($summary, $observations),
                'recent_traces' => $recentTraces,
                'error_breakdown' => $this->buildErrorBreakdown($observations),
                'top_error_messages' => $this->buildTopErrorMessages($observations),
                'slow_observations' => $this->buildSlowObservations($observations),
                'expensive_observations' => $this->buildExpensiveObservations($observations),
                'top_observations' => $this->buildTopObservationNames($observations),
                'model_breakdown' => $this->buildModelBreakdown($observations),
                'top_models' => $this->buildTopModels($observations),
                'meta' => [
                    'window_start' => $windowStart->toIso8601String(),
                    'window_end' => $windowEnd->toIso8601String(),
                    'observations_count' => $observations->count(),
                    'hours' => $hours,
                    'limit' => $limit,
                ],
            ];
        } catch (\Throwable $exception) {
            Log::warning('Langfuse dashboard fetch failed', [
                'error' => $exception->getMessage(),
            ]);

            return $this->emptySnapshot($windowStart, $windowEnd, true, false, 'Langfuse API-დან მონაცემების წამოღება ვერ მოხერხდა.');
        }
    }

    private function fetchObservations(Carbon $windowStart, Carbon $windowEnd, int $limit): array
    {
        $results = [];
        $cursor = null;

        do {
            $remaining = $limit - count($results);

            if ($remaining <= 0) {
                break;
            }

            $query = [
                'fields' => 'core,basic,time,model,usage,metrics',
                'fromStartTime' => $windowStart->toIso8601String(),
                'toStartTime' => $windowEnd->toIso8601String(),
                'limit' => min(100, $remaining),
            ];

            if ($cursor) {
                $query['cursor'] = $cursor;
            }

            $response = Http::withBasicAuth($this->publicKey(), $this->secretKey())
                ->acceptJson()
                ->timeout($this->timeout())
                ->get($this->baseUrl() . '/api/public/v2/observations', $query);

            if (!$response->successful()) {
                throw new \RuntimeException('Langfuse observations request failed with status ' . $response->status());
            }

            $payload = $response->json();
            $page = data_get($payload, 'data', []);

            if (!is_array($page)) {
                break;
            }

            $results = [...$results, ...$page];
            $cursor = data_get($payload, 'meta.cursor');
        } while ($cursor);

        return array_slice($results, 0, $limit);
    }

    private function buildSummary(Collection $observations): array
    {
        $latencies = $observations
            ->map(fn (array $observation) => $this->normalizeLatencyMs($observation))
            ->filter(fn (?int $latency) => $latency !== null)
            ->values();
        $generationCount = $observations->where('type', 'GENERATION')->count();
        $errorCount = $observations->filter(fn (array $observation) => $this->hasError($observation))->count();
        $totalObservations = $observations->count();
        $successCount = max(0, $totalObservations - $errorCount);

        $totalTokens = $observations->sum(fn (array $observation) => $this->normalizeTokenUsage($observation));
        $totalCost = $observations->sum(fn (array $observation) => $this->normalizeCost($observation));

        return [
            'total_observations' => $totalObservations,
            'unique_traces' => $observations->pluck('traceId')->filter()->unique()->count(),
            'generation_count' => $generationCount,
            'error_count' => $errorCount,
            'success_count' => $successCount,
            'error_rate' => $totalObservations > 0 ? round(($errorCount / $totalObservations) * 100, 1) : 0.0,
            'success_rate' => $totalObservations > 0 ? round(($successCount / $totalObservations) * 100, 1) : 0.0,
            'avg_latency_ms' => $latencies->isNotEmpty() ? (int) round($latencies->avg()) : 0,
            'p95_latency_ms' => $this->percentileLatency($latencies, 95),
            'total_tokens' => (int) round($totalTokens),
            'total_cost' => round((float) $totalCost, 4),
            'avg_cost_per_generation' => $generationCount > 0 ? round((float) $totalCost / $generationCount, 6) : 0.0,
            'slow_observation_count' => $observations->filter(fn (array $observation) => ($this->normalizeLatencyMs($observation) ?? 0) >= 3000)->count(),
        ];
    }

    private function buildHealth(array $summary, Collection $observations): array
    {
        $status = 'healthy';
        $label = 'ჯანმრთელი';
        $reasons = [];

        if ($observations->isEmpty()) {
            $status = 'warning';
            $label = 'მონაცემი არ ჩანს';
            $reasons[] = 'ამ ფანჯარაში observation-ები არ დაბრუნდა.';
        }

        if (($summary['error_rate'] ?? 0) >= 15) {
            $status = 'critical';
            $label = 'საჭიროა სწრაფი რეაგირება';
            $reasons[] = 'შეცდომების წილი მაღალია.';
        } elseif (($summary['error_rate'] ?? 0) >= 5 && $status !== 'critical') {
            $status = 'warning';
            $label = 'საჭიროა ყურადღება';
            $reasons[] = 'შეცდომების წილი გაზრდილია.';
        }

        if (($summary['p95_latency_ms'] ?? 0) >= 5000) {
            $status = 'critical';
            $label = 'ძალიან ნელია';
            $reasons[] = 'P95 latency ძალიან მაღალია.';
        } elseif (($summary['avg_latency_ms'] ?? 0) >= 2500 && $status === 'healthy') {
            $status = 'warning';
            $label = 'ოდნავ ნელია';
            $reasons[] = 'საშუალო latency გაზრდილია.';
        }

        if (($summary['slow_observation_count'] ?? 0) >= 5 && $status === 'healthy') {
            $status = 'warning';
            $label = 'ნელი ნაბიჯები დაფიქსირდა';
            $reasons[] = 'რამდენიმე observation 3 წამზე ნელია.';
        }

        if ($reasons === []) {
            $reasons[] = 'მთავარი სიგნალები ნორმის ფარგლებშია.';
        }

        return [
            'status' => $status,
            'label' => $label,
            'reasons' => array_slice($reasons, 0, 3),
        ];
    }

    private function buildRecentTraces(Collection $observations): array
    {
        return $observations
            ->filter(fn (array $observation) => !empty($observation['traceId']))
            ->groupBy('traceId')
            ->map(function (Collection $items, string $traceId): array {
                $sorted = $items->sortByDesc(fn (array $observation) => $observation['startTime'] ?? $observation['createdAt'] ?? '');
                $latest = $sorted->first() ?? [];
                $latencies = $items
                    ->map(fn (array $observation) => $this->normalizeLatencyMs($observation))
                    ->filter(fn (?int $latency) => $latency !== null)
                    ->values();
                $models = $items
                    ->map(fn (array $observation) => (string) ($observation['model'] ?? $observation['providedModelName'] ?? ''))
                    ->filter()
                    ->unique()
                    ->values();
                $names = $items
                    ->map(fn (array $observation) => (string) ($observation['name'] ?? ''))
                    ->filter()
                    ->unique()
                    ->values();
                $latestError = $sorted
                    ->map(fn (array $observation) => trim((string) ($observation['statusMessage'] ?? '')))
                    ->filter()
                    ->first();
                $projectId = (string) ($latest['projectId'] ?? $items->pluck('projectId')->filter()->first() ?? '');

                return [
                    'trace_id' => $traceId,
                    'project_id' => $projectId,
                    'latest_at' => (string) ($latest['startTime'] ?? $latest['createdAt'] ?? ''),
                    'latest_at_label' => $this->formatTimestamp($latest['startTime'] ?? $latest['createdAt'] ?? null),
                    'primary_name' => $names->first() ?: 'trace',
                    'observation_names' => $names->take(3)->all(),
                    'user_id' => (string) ($items->pluck('userId')->filter()->first() ?? ''),
                    'session_id' => (string) ($items->pluck('sessionId')->filter()->first() ?? ''),
                    'observation_count' => $items->count(),
                    'generation_count' => $items->where('type', 'GENERATION')->count(),
                    'has_error' => $items->contains(fn (array $observation) => $this->hasError($observation)),
                    'latest_error_message' => $latestError ?: '',
                    'avg_latency_ms' => $latencies->isNotEmpty() ? (int) round($latencies->avg()) : null,
                    'max_latency_ms' => $latencies->isNotEmpty() ? (int) $latencies->max() : null,
                    'total_tokens' => (int) round($items->sum(fn (array $observation) => $this->normalizeTokenUsage($observation))),
                    'total_cost' => round((float) $items->sum(fn (array $observation) => $this->normalizeCost($observation)), 4),
                    'models' => $models->take(3)->all(),
                    'trace_url' => $this->traceUrl($traceId, $projectId),
                ];
            })
            ->sortByDesc('latest_at')
            ->take(20)
            ->values()
            ->all();
    }

    private function buildErrorBreakdown(Collection $observations): array
    {
        return $observations
            ->filter(fn (array $observation) => $this->hasError($observation))
            ->groupBy(fn (array $observation) => (string) ($observation['name'] ?? 'unknown'))
            ->map(function (Collection $items, string $name) use ($observations): array {
                $allForName = $observations->filter(fn (array $observation) => (string) ($observation['name'] ?? 'unknown') === $name);
                $latest = $items->sortByDesc(fn (array $observation) => $observation['startTime'] ?? $observation['createdAt'] ?? '')->first() ?? [];

                return [
                    'name' => $name,
                    'count' => $items->count(),
                    'affected_traces' => $items->pluck('traceId')->filter()->unique()->count(),
                    'error_rate' => $allForName->count() > 0 ? round(($items->count() / $allForName->count()) * 100, 1) : 0.0,
                    'latest_at_label' => $this->formatTimestamp($latest['startTime'] ?? $latest['createdAt'] ?? null),
                    'latest_message' => trim((string) ($latest['statusMessage'] ?? '')),
                ];
            })
            ->sortByDesc('count')
            ->take(6)
            ->values()
            ->all();
    }

    private function buildTopErrorMessages(Collection $observations): array
    {
        return $observations
            ->map(fn (array $observation) => trim((string) ($observation['statusMessage'] ?? '')))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(5)
            ->map(fn (int $count, string $message) => [
                'message' => $message,
                'count' => $count,
            ])
            ->values()
            ->all();
    }

    private function buildTopObservationNames(Collection $observations): array
    {
        return $observations
            ->map(fn (array $observation) => (string) ($observation['name'] ?? ''))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(5)
            ->map(fn (int $count, string $name) => [
                'name' => $name,
                'count' => $count,
            ])
            ->values()
            ->all();
    }

    private function buildModelBreakdown(Collection $observations): array
    {
        return $observations
            ->filter(fn (array $observation) => (string) ($observation['model'] ?? $observation['providedModelName'] ?? '') !== '')
            ->groupBy(fn (array $observation) => (string) ($observation['model'] ?? $observation['providedModelName'] ?? ''))
            ->map(function (Collection $items, string $model): array {
                $latencies = $items
                    ->map(fn (array $observation) => $this->normalizeLatencyMs($observation))
                    ->filter(fn (?int $latency) => $latency !== null)
                    ->values();
                $errorCount = $items->filter(fn (array $observation) => $this->hasError($observation))->count();
                $cost = (float) $items->sum(fn (array $observation) => $this->normalizeCost($observation));

                return [
                    'model' => $model,
                    'count' => $items->count(),
                    'generation_count' => $items->where('type', 'GENERATION')->count(),
                    'error_count' => $errorCount,
                    'error_rate' => $items->count() > 0 ? round(($errorCount / $items->count()) * 100, 1) : 0.0,
                    'avg_latency_ms' => $latencies->isNotEmpty() ? (int) round($latencies->avg()) : null,
                    'total_tokens' => (int) round($items->sum(fn (array $observation) => $this->normalizeTokenUsage($observation))),
                    'total_cost' => round($cost, 6),
                    'avg_cost' => $items->count() > 0 ? round($cost / $items->count(), 6) : 0.0,
                ];
            })
            ->sort(function (array $left, array $right): int {
                return [$right['total_cost'], $right['count']] <=> [$left['total_cost'], $left['count']];
            })
            ->take(6)
            ->values()
            ->all();
    }

    private function buildTopModels(Collection $observations): array
    {
        return $observations
            ->map(fn (array $observation) => (string) ($observation['model'] ?? $observation['providedModelName'] ?? ''))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(5)
            ->map(fn (int $count, string $model) => [
                'model' => $model,
                'count' => $count,
            ])
            ->values()
            ->all();
    }

    private function buildSlowObservations(Collection $observations): array
    {
        return $observations
            ->map(fn (array $observation) => $this->mapObservationRow($observation))
            ->filter(fn (array $row) => $row['latency_ms'] !== null)
            ->sortByDesc('latency_ms')
            ->take(8)
            ->values()
            ->all();
    }

    private function buildExpensiveObservations(Collection $observations): array
    {
        return $observations
            ->map(fn (array $observation) => $this->mapObservationRow($observation))
            ->filter(fn (array $row) => $row['total_cost'] > 0)
            ->sortByDesc('total_cost')
            ->take(8)
            ->values()
            ->all();
    }

    private function mapObservationRow(array $observation): array
    {
        $traceId = (string) ($observation['traceId'] ?? '');
        $projectId = (string) ($observation['projectId'] ?? '');

        return [
            'id' => (string) ($observation['id'] ?? ''),
            'trace_id' => $traceId,
            'project_id' => $projectId,
            'name' => (string) ($observation['name'] ?? 'unknown'),
            'type' => (string) ($observation['type'] ?? '—'),
            'model' => (string) ($observation['model'] ?? $observation['providedModelName'] ?? ''),
            'user_id' => (string) ($observation['userId'] ?? ''),
            'session_id' => (string) ($observation['sessionId'] ?? ''),
            'latency_ms' => $this->normalizeLatencyMs($observation),
            'total_tokens' => (int) round($this->normalizeTokenUsage($observation)),
            'total_cost' => round($this->normalizeCost($observation), 6),
            'latest_at' => (string) ($observation['startTime'] ?? $observation['createdAt'] ?? ''),
            'latest_at_label' => $this->formatTimestamp($observation['startTime'] ?? $observation['createdAt'] ?? null),
            'has_error' => $this->hasError($observation),
            'error_message' => trim((string) ($observation['statusMessage'] ?? '')),
            'trace_url' => $this->traceUrl($traceId, $projectId),
        ];
    }

    private function normalizeLatencyMs(array $observation): ?int
    {
        $latency = $observation['latency'] ?? null;

        if ($latency === null || $latency === '') {
            return null;
        }

        return (int) round(((float) $latency) * 1000);
    }

    private function normalizeTokenUsage(array $observation): float
    {
        return (float) ($observation['totalUsage']
            ?? data_get($observation, 'usageDetails.total')
            ?? 0);
    }

    private function normalizeCost(array $observation): float
    {
        return (float) ($observation['totalCost']
            ?? $observation['totalPrice']
            ?? data_get($observation, 'costDetails.total')
            ?? 0);
    }

    private function hasError(array $observation): bool
    {
        return trim((string) ($observation['statusMessage'] ?? '')) !== '';
    }

    private function percentileLatency(Collection $latencies, int $percentile): int
    {
        if ($latencies->isEmpty()) {
            return 0;
        }

        $sorted = $latencies->sort()->values();
        $index = (int) ceil(($percentile / 100) * $sorted->count()) - 1;
        $index = max(0, min($index, $sorted->count() - 1));

        return (int) $sorted[$index];
    }

    private function traceUrl(string $traceId, string $projectId): string
    {
        if ($traceId === '' || $projectId === '') {
            return '';
        }

        return $this->baseUrl() . '/project/' . rawurlencode($projectId) . '/traces/' . rawurlencode($traceId);
    }

    private function formatTimestamp(?string $timestamp): string
    {
        if (!$timestamp) {
            return '—';
        }

        try {
            return Carbon::parse($timestamp)->timezone(config('app.timezone'))->format('Y-m-d H:i');
        } catch (\Throwable) {
            return '—';
        }
    }

    private function emptySnapshot(Carbon $windowStart, Carbon $windowEnd, bool $enabled, bool $connected, string $error): array
    {
        return [
            'enabled' => $enabled,
            'connected' => $connected,
            'error' => $error,
            'base_url' => $this->baseUrl(),
            'summary' => [
                'total_observations' => 0,
                'unique_traces' => 0,
                'generation_count' => 0,
                'error_count' => 0,
                'success_count' => 0,
                'error_rate' => 0.0,
                'success_rate' => 0.0,
                'avg_latency_ms' => 0,
                'p95_latency_ms' => 0,
                'total_tokens' => 0,
                'total_cost' => 0,
                'avg_cost_per_generation' => 0.0,
                'slow_observation_count' => 0,
            ],
            'health' => [
                'status' => 'warning',
                'label' => 'მონაცემი არ ჩანს',
                'reasons' => [$error],
            ],
            'recent_traces' => [],
            'error_breakdown' => [],
            'top_error_messages' => [],
            'slow_observations' => [],
            'expensive_observations' => [],
            'top_observations' => [],
            'model_breakdown' => [],
            'top_models' => [],
            'meta' => [
                'window_start' => $windowStart->toIso8601String(),
                'window_end' => $windowEnd->toIso8601String(),
                'observations_count' => 0,
            ],
        ];
    }

    private function enabled(): bool
    {
        return (bool) config('services.langfuse.enabled', false)
            && $this->publicKey() !== ''
            && $this->secretKey() !== ''
            && $this->baseUrl() !== '';
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.langfuse.base_url', 'https://cloud.langfuse.com'), '/');
    }

    private function publicKey(): string
    {
        return trim((string) config('services.langfuse.public_key', ''));
    }

    private function secretKey(): string
    {
        return trim((string) config('services.langfuse.secret_key', ''));
    }

    private function timeout(): int
    {
        return (int) config('services.langfuse.timeout', 5);
    }
}
