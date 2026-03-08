@extends('admin.layout')

@section('title', 'ჩატბოტ ლაბი - გაშვებები')

@section('content')
@if (session('status'))
    <div class="alert alert-success" role="alert">{{ session('status') }}</div>
@endif

@if (session('warning'))
    <div class="alert alert-warning" role="alert">{{ session('warning') }}</div>
@endif

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="mb-1">სატესტო გაშვებები</h4>
        <p class="text-muted mb-0">აირჩიეთ შენახული ქეისები, გაუშვით ერთიანად და ჯერ პრობლემური შედეგები გადაამოწმეთ.</p>
    </div>
    <div class="d-flex gap-2">
        <span class="badge bg-light text-dark border">ქეისები: {{ $labStats['total'] ?? 0 }}</span>
        <span class="badge bg-light text-dark border">ბოლო გაშვებები: {{ $recentRuns->count() }}</span>
    </div>
</div>

<div class="d-flex gap-2 mb-4">
    <a href="{{ route('admin.chatbot-lab.index') }}" class="btn btn-outline-primary btn-sm">ხელით ტესტი</a>
    <a href="{{ route('admin.chatbot-lab.cases.index') }}" class="btn btn-outline-primary btn-sm">სატესტო ქეისები</a>
    <a href="{{ route('admin.chatbot-lab.runs.index') }}" class="btn btn-primary btn-sm">სატესტო გაშვებები</a>
</div>

@unless ($casesReady)
    <div class="alert alert-warning" role="alert">The training cases table does not exist yet. Run <code>php artisan migrate</code> before starting evaluation runs.</div>
@endunless

@unless ($runStorageReady)
    <div class="alert alert-warning" role="alert">Run storage tables are missing. Create the existing chatbot test run tables before using Evaluation Runs.</div>
@endunless

@if ($casesReady && $runStorageReady)
    <div class="alert {{ $queueStatus['background_capable'] ? 'alert-success' : ($queueStatus['can_dispatch'] ? 'alert-warning' : 'alert-danger') }}" role="alert">
        <strong>Queue status:</strong> Driver <code>{{ $queueStatus['driver'] }}</code>. {{ $queueStatus['message'] }}
    </div>
@endif

@if (($selectionPreflight['blocking_count'] ?? 0) > 0 || ($selectionPreflight['warning_count'] ?? 0) > 0)
    <div class="alert {{ ($selectionPreflight['blocking_count'] ?? 0) > 0 ? 'alert-warning' : 'alert-info' }}" role="alert">
        <strong>Pre-run case validation:</strong>
        {{ $selectionPreflight['blocking_count'] ?? 0 }} blocking,
        {{ $selectionPreflight['warning_count'] ?? 0 }} warning.
        @if (($selectionPreflight['blocking_case_titles'] ?? []) !== [])
            <div class="small mt-2"><strong>Blocking cases:</strong> {{ implode(', ', array_slice($selectionPreflight['blocking_case_titles'], 0, 4)) }}</div>
        @endif
        @if (($selectionPreflight['warning_case_titles'] ?? []) !== [])
            <div class="small mt-1"><strong>Warning cases:</strong> {{ implode(', ', array_slice($selectionPreflight['warning_case_titles'], 0, 4)) }}</div>
        @endif
        @if (($selectionPreflight['blocking_messages'] ?? []) !== [])
            <div class="small mt-2">
                <strong>Top blocking issues:</strong>
                <ul class="mb-0 mt-1 ps-3">
                    @foreach (array_slice($selectionPreflight['blocking_messages'], 0, 3) as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @elseif (($selectionPreflight['warning_messages'] ?? []) !== [])
            <div class="small mt-2">
                <strong>Top warnings:</strong>
                <ul class="mb-0 mt-1 ps-3">
                    @foreach (array_slice($selectionPreflight['warning_messages'], 0, 3) as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endif

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <h5 class="card-title mb-1">Operational Snapshot</h5>
                <div class="small text-muted">Last {{ $observabilitySummary['days'] ?? 7 }} days across Chatbot Lab runs and cached chatbot quality counters.</div>
            </div>
            <span class="badge bg-light text-dark border">Observed results: {{ $observabilitySummary['result_count'] ?? 0 }}</span>
        </div>

        <div class="row g-3">
            <div class="col-md-3 col-sm-6">
                <div class="border rounded p-3 h-100">
                    <div class="small text-muted mb-1">Average Response Time</div>
                    <div class="fw-semibold">{{ $observabilitySummary['avg_response_time_ms'] ?? 0 }} ms</div>
                    <div class="small text-muted">Slow: {{ $observabilitySummary['slow_response_count'] ?? 0 }} cases ({{ number_format((float) ($observabilitySummary['slow_response_rate'] ?? 0), 2) }}%)</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="border rounded p-3 h-100">
                    <div class="small text-muted mb-1">Fallback Pressure</div>
                    <div class="fw-semibold">{{ $observabilitySummary['fallback_count'] ?? 0 }}</div>
                    <div class="small text-muted">Rate: {{ number_format((float) ($observabilitySummary['fallback_rate'] ?? 0), 2) }}%</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="border rounded p-3 h-100">
                    <div class="small text-muted mb-1">Regeneration Recovery</div>
                    <div class="fw-semibold">{{ $observabilitySummary['regeneration_attempt_count'] ?? 0 }} attempts</div>
                    <div class="small text-muted">Success: {{ number_format((float) ($observabilitySummary['regeneration_success_rate'] ?? 0), 2) }}%</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="border rounded p-3 h-100">
                    <div class="small text-muted mb-1">Provider Issues</div>
                    <div class="fw-semibold">{{ $observabilitySummary['provider_issue_count'] ?? 0 }}</div>
                    <div class="small text-muted">Rate: {{ number_format((float) ($observabilitySummary['provider_issue_rate'] ?? 0), 2) }}%</div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-lg-6">
                <div class="border rounded p-3 h-100">
                    <h6 class="mb-2">Run Duration By Case Count</h6>
                    <div class="small text-muted mb-1">Completed runs: {{ $observabilitySummary['completed_run_count'] ?? 0 }}</div>
                    <div class="small text-muted mb-1">Average duration: {{ number_format((float) ($observabilitySummary['avg_run_duration_seconds'] ?? 0), 2) }}s</div>
                    <div class="small text-muted">Average per case: {{ number_format((float) ($observabilitySummary['avg_seconds_per_case'] ?? 0), 2) }}s</div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="border rounded p-3 h-100">
                    <h6 class="mb-2">Top Fallback Reasons</h6>
                    @if (($observabilitySummary['top_fallback_reasons'] ?? []) === [])
                        <div class="small text-muted mb-0">No fallback-heavy cases recorded in the selected window.</div>
                    @else
                        <ul class="small mb-0 ps-3">
                            @foreach ($observabilitySummary['top_fallback_reasons'] as $reason)
                                <li>{{ $reason['label'] ?? $reason['reason'] }} ({{ $reason['reason'] }}): {{ $reason['count'] }}</li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="small text-muted mt-2">Global chatbot fallback rate: {{ number_format((float) ($observabilitySummary['global_quality']['fallback_rate'] ?? 0), 2) }}%</div>
                    <div class="small text-muted">Global provider fallback rate: {{ number_format((float) ($observabilitySummary['global_quality']['provider_fallback_rate'] ?? 0), 2) }}%</div>
                    <div class="small text-muted">Global provider incident rate: {{ number_format((float) ($observabilitySummary['global_quality']['provider_incident_rate'] ?? 0), 2) }}%</div>
                    <div class="small text-muted">Global validator fallback rate: {{ number_format((float) ($observabilitySummary['global_quality']['validator_fallback_rate'] ?? 0), 2) }}%</div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-lg-6">
                <div class="border rounded p-3 h-100">
                    <h6 class="mb-2">Alert Thresholds</h6>
                    <div class="small text-muted mb-1">Fallback alert: {{ number_format((float) ($observabilitySummary['thresholds']['fallback_alert_rate'] ?? 0), 2) }}%</div>
                    <div class="small text-muted mb-1">Slow-response alert: {{ number_format((float) ($observabilitySummary['thresholds']['slow_response_alert_rate'] ?? 0), 2) }}%</div>
                    <div class="small text-muted mb-1">Provider alert: {{ number_format((float) ($observabilitySummary['thresholds']['provider_fallback_alert_rate'] ?? 0), 2) }}%</div>
                    <div class="small text-muted mb-1">Provider incident alert: {{ number_format((float) ($observabilitySummary['thresholds']['provider_incident_alert_rate'] ?? 0), 2) }}%</div>
                    <div class="small text-muted mb-1">Validator alert: {{ number_format((float) ($observabilitySummary['thresholds']['validator_fallback_alert_rate'] ?? 0), 2) }}%</div>
                    <div class="small text-muted">Slow-response threshold: {{ $observabilitySummary['thresholds']['slow_response_ms'] ?? 0 }} ms</div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="border rounded p-3 h-100">
                    <h6 class="mb-2">Daily Quality Trend</h6>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Responses</th>
                                    <th>Fallback</th>
                                    <th>Slow</th>
                                    <th>Provider Incidents</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (($observabilitySummary['daily_trend'] ?? []) as $day)
                                    <tr>
                                        <td>{{ $day['date'] }}</td>
                                        <td>{{ $day['response_total'] }}</td>
                                        <td>{{ number_format((float) ($day['fallback_rate'] ?? 0), 2) }}%</td>
                                        <td>{{ number_format((float) ($day['slow_response_rate'] ?? 0), 2) }}%</td>
                                        <td>{{ number_format((float) ($day['provider_incident_rate'] ?? 0), 2) }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        @if (($observabilitySummary['alerts'] ?? []) !== [])
            <div class="alert alert-warning mt-3 mb-0" role="alert">
                <strong>Monitoring alerts:</strong>
                <ul class="mb-0 mt-2 ps-3">
                    @foreach ($observabilitySummary['alerts'] as $alert)
                        <li>{{ $alert }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-3">Start New Run</h5>

                @if (!$casesReady || !$runStorageReady)
                    <p class="text-muted mb-0">Evaluation runs are disabled until the required tables exist.</p>
                @elseif (!$queueStatus['can_dispatch'])
                    <p class="text-muted mb-0">Evaluation runs are disabled until the queue infrastructure is ready.</p>
                @elseif ($selectableCases->isEmpty())
                    <p class="text-muted mb-0">No active training cases found. Activate at least one case before starting a run.</p>
                @else
                    <form method="POST" action="{{ route('admin.chatbot-lab.runs.start') }}">
                        @csrf

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="small text-muted">
                                Active cases are preselected.
                                @if ($queueStatus['background_capable'])
                                    Runs are queued and continue in the background while you inspect the run page.
                                @else
                                    Runs will execute inline until a background queue worker is configured.
                                @endif
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="use_llm_judge" value="1" {{ old('use_llm_judge') ? 'checked' : '' }}>
                                <label class="form-check-label">Enable LLM judge</label>
                            </div>
                        </div>

                        <div class="table-responsive border rounded">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 60px;">Run</th>
                                        <th>Case</th>
                                        <th>Intent</th>
                                        <th>Tags</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($selectableCases as $case)
                                        @php
                                            $diagnostic = $caseDiagnostics[$case->id] ?? ['health' => 'healthy', 'blocking_issues' => [], 'warning_issues' => []];
                                            $healthBadgeClass = match ($diagnostic['health']) {
                                                'blocking' => 'bg-danger-subtle text-danger border',
                                                'warning' => 'bg-warning-subtle text-warning border',
                                                default => 'bg-success-subtle text-success border',
                                            };
                                            $healthLabel = match ($diagnostic['health']) {
                                                'blocking' => 'Blocking',
                                                'warning' => 'Warning',
                                                default => 'Healthy',
                                            };
                                        @endphp
                                        <tr>
                                            <td>
                                                <input class="form-check-input" type="checkbox" name="case_ids[]" value="{{ $case->id }}" checked>
                                            </td>
                                            <td>
                                                <div class="fw-semibold">{{ $case->title }}</div>
                                                <div class="small text-muted">{{ \Illuminate\Support\Str::limit($case->prompt, 120) }}</div>
                                                @if (($diagnostic['blocking_issues'] ?? []) !== [] || ($diagnostic['warning_issues'] ?? []) !== [])
                                                    <div class="small {{ $diagnostic['health'] === 'blocking' ? 'text-danger' : 'text-warning' }}">
                                                        {{ ($diagnostic['blocking_issues'][0] ?? $diagnostic['warning_issues'][0] ?? '') }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td>{{ $case->expected_intent ?: '—' }}</td>
                                            <td>
                                                <div>{{ implode(', ', $case->tags_json ?? []) ?: '—' }}</div>
                                                <span class="badge {{ $healthBadgeClass }} mt-1">{{ $healthLabel }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">Queue Evaluation Run</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-3">Recent Runs</h5>

                @forelse ($recentRuns as $run)
                    <div class="border rounded p-3 mb-3"
                        data-run-card="1"
                        data-run-status-url="{{ route('admin.chatbot-lab.runs.status', $run) }}"
                        data-terminal="{{ in_array($run->status, ['completed', 'failed', 'cancelled'], true) ? '1' : '0' }}">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="fw-semibold">Run #{{ $run->id }}</div>
                                <div class="small text-muted" data-run-timing>
                                    {{ optional($run->completed_at)->diffForHumans() ?? optional($run->started_at)->diffForHumans() ?? 'Not finished' }}
                                </div>
                            </div>
                            <span class="badge {{ $run->status === 'completed' ? 'bg-success' : ($run->status === 'failed' ? 'bg-danger' : ($run->status === 'cancelled' ? 'bg-warning' : ($run->status === 'running' ? 'bg-primary' : 'bg-secondary'))) }}" data-run-badge>{{ strtoupper($run->status) }}</span>
                        </div>
                        <div class="small text-muted mb-2" data-run-summary>
                            Cases: {{ $run->total_cases ?? 0 }} |
                            Accuracy: {{ $run->accuracy_pct !== null ? number_format((float) $run->accuracy_pct, 2) . '%' : 'N/A' }} |
                            LLM judge: {{ !empty($run->filters['use_llm_judge']) ? 'on' : 'off' }}
                        </div>
                        <div class="progress mb-2" style="height: 6px;">
                            <div class="progress-bar {{ $run->status === 'completed' ? 'bg-success' : ($run->status === 'failed' ? 'bg-danger' : ($run->status === 'cancelled' ? 'bg-warning' : 'bg-primary')) }}"
                                data-run-progress
                                role="progressbar"
                                style="width: {{ in_array($run->status, ['completed', 'failed', 'cancelled'], true) ? 100 : 0 }}%"></div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('admin.chatbot-lab.runs.show', $run) }}" class="btn btn-sm btn-outline-primary">View Run</a>
                            @if (in_array($run->status, ['pending', 'running'], true))
                                <form method="POST" action="{{ route('admin.chatbot-lab.runs.cancel', $run) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Cancel</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0">No Chatbot Lab runs yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const cards = Array.from(document.querySelectorAll('[data-run-card="1"]'));
    const activeCards = cards.filter((card) => card.dataset.terminal !== '1');

    if (activeCards.length === 0) {
        return;
    }

    const badgeClassFor = (status) => {
        if (status === 'completed') return 'bg-success';
        if (status === 'failed') return 'bg-danger';
        if (status === 'cancelled') return 'bg-warning';
        if (status === 'running') return 'bg-primary';
        return 'bg-secondary';
    };

    const renderCard = (card, run) => {
        const badge = card.querySelector('[data-run-badge]');
        const summary = card.querySelector('[data-run-summary]');
        const progress = card.querySelector('[data-run-progress]');
        const timing = card.querySelector('[data-run-timing]');
        const status = run.status || 'pending';
        const accuracy = run.accuracy_pct === null || run.accuracy_pct === undefined
            ? 'N/A'
            : `${Number(run.accuracy_pct).toFixed(2)}%`;

        if (badge) {
            badge.textContent = String(status).toUpperCase();
            badge.className = 'badge ' + badgeClassFor(status);
        }

        if (summary) {
            summary.textContent = `Processed ${run.processed_cases || 0}/${run.total_cases || 0} | Passed: ${run.passed_cases || 0} | Failed: ${run.failed_cases || 0} | Accuracy: ${accuracy}`;
        }

        if (progress) {
            progress.style.width = `${Number(run.percent_complete || 0)}%`;
            progress.className = 'progress-bar ' + badgeClassFor(status);
        }

        if (timing) {
            if (status === 'running') {
                timing.textContent = 'Execution in progress';
            } else if (status === 'pending') {
                timing.textContent = 'Waiting for background worker';
            } else if (status === 'cancelled') {
                timing.textContent = 'Cancelled by admin';
            } else if (status === 'failed') {
                timing.textContent = run.error_message || 'Run failed';
            } else if (run.duration_seconds !== null && run.duration_seconds !== undefined) {
                timing.textContent = `Completed in ${Number(run.duration_seconds).toFixed(2)}s`;
            }
        }

        if (run.is_terminal) {
            card.dataset.terminal = '1';
        }
    };

    const syncCards = async () => {
        for (const card of activeCards) {
            if (card.dataset.terminal === '1') {
                continue;
            }

            try {
                const response = await fetch(card.dataset.runStatusUrl, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) {
                    continue;
                }

                const payload = await response.json();
                if (payload.run) {
                    renderCard(card, payload.run);
                }
            } catch (error) {
            }
        }
    };

    void syncCards();
    window.setInterval(syncCards, 5000);
});
</script>
@endpush
