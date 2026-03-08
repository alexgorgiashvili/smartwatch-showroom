@extends('admin.layout')

@section('title', 'Chatbot Lab Run #' . $run->id)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1">Evaluation Run #{{ $run->id }}</h4>
        <p class="text-muted mb-0">Simplified run detail for training-case based chatbot QA.</p>
    </div>
    <div class="d-flex gap-2">
        @if (in_array($run->status, ['pending', 'running'], true))
            <form method="POST" action="{{ route('admin.chatbot-lab.runs.cancel', $run) }}">
                @csrf
                <button type="submit" class="btn btn-outline-danger">Cancel Run</button>
            </form>
        @endif
        <a href="{{ route('admin.chatbot-lab.runs.index') }}" class="btn btn-outline-secondary">← Back to Runs</a>
        <a href="{{ route('admin.chatbot-lab.runs.export', $run) }}" class="btn btn-outline-primary">Export CSV</a>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success" role="alert">{{ session('status') }}</div>
@endif

@if (session('warning'))
    <div class="alert alert-warning" role="alert">{{ session('warning') }}</div>
@endif

@if (!$queueStatus['background_capable'])
    <div class="alert {{ $queueStatus['can_dispatch'] ? 'alert-warning' : 'alert-danger' }}" role="alert">
        <strong>Queue status:</strong> Driver <code>{{ $queueStatus['driver'] }}</code>. {{ $queueStatus['message'] }}
    </div>
@endif

@include('admin.chatbot-lab.partials.score-cards', ['run' => $run])

@if (in_array($run->status, ['pending', 'running'], true))
    <div class="alert alert-info" role="alert" id="run-status-alert">
        This run is currently {{ $run->status }}. Refresh this page to see new results as execution progresses.
    </div>
@endif

@if ($run->status === 'failed' && $run->error_message)
    <div class="alert alert-danger" role="alert">
        Run failed: {{ $run->error_message }}
    </div>
@endif

@if ($run->status === 'cancelled')
    <div class="alert alert-warning" role="alert">
        This run was cancelled. {{ $run->error_message ?: '' }}
    </div>
@endif

<div class="card mb-3">
    <div class="card-body py-2">
        <div class="small text-muted">
            <strong>Run Scope:</strong>
            Selected training cases ({{ count($run->filters['case_ids'] ?? []) }}),
            LLM judge: {{ !empty($run->filters['use_llm_judge']) ? 'enabled' : 'disabled' }}
        </div>
    </div>
</div>

<div class="card mb-3" id="run-progress-card" data-status-url="{{ route('admin.chatbot-lab.runs.status', $run) }}" data-terminal="{{ $runSnapshot['is_terminal'] ? '1' : '0' }}">
    <div class="card-body py-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">Run Progress</h6>
            <span class="badge {{ $run->status === 'completed' ? 'bg-success' : ($run->status === 'failed' ? 'bg-danger' : ($run->status === 'cancelled' ? 'bg-warning' : ($run->status === 'running' ? 'bg-primary' : 'bg-secondary'))) }}" id="run-progress-badge">{{ strtoupper($runSnapshot['status']) }}</span>
        </div>
        <div class="small text-muted mb-2" id="run-progress-summary">
            Processed {{ $runSnapshot['processed_cases'] }}/{{ $runSnapshot['total_cases'] }} cases.
            Passed: {{ $runSnapshot['passed_cases'] }}.
            Failed: {{ $runSnapshot['failed_cases'] }}.
            Skipped: {{ $runSnapshot['skipped_cases'] }}.
        </div>
        <div class="progress" style="height: 6px;">
            <div class="progress-bar {{ $run->status === 'completed' ? 'bg-success' : ($run->status === 'failed' ? 'bg-danger' : ($run->status === 'cancelled' ? 'bg-warning' : 'bg-primary')) }}" id="run-progress-bar" role="progressbar" style="width: {{ $run->status === 'cancelled' ? 100 : $runSnapshot['percent_complete'] }}%"></div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">Run Health Snapshot</h6>
            <span class="badge bg-light text-dark border">Observed results: {{ $runObservability['result_count'] ?? 0 }}</span>
        </div>
        <div class="row g-3 small">
            <div class="col-md-3 col-sm-6">
                <div class="border rounded p-3 h-100">
                    <div class="text-muted mb-1">Average Response Time</div>
                    <div class="fw-semibold">{{ $runObservability['avg_response_time_ms'] ?? 0 }} ms</div>
                    <div class="text-muted">Slow cases: {{ $runObservability['slow_response_count'] ?? 0 }}</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="border rounded p-3 h-100">
                    <div class="text-muted mb-1">Fallbacks</div>
                    <div class="fw-semibold">{{ $runObservability['fallback_count'] ?? 0 }}</div>
                    <div class="text-muted">
                        Top reason: {{ $runObservability['top_fallback_reason_label'] ?? '—' }}
                        @if (!empty($runObservability['top_fallback_reason'] ?? null))
                            <span>({{ $runObservability['top_fallback_reason'] }})</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="border rounded p-3 h-100">
                    <div class="text-muted mb-1">Regeneration</div>
                    <div class="fw-semibold">{{ $runObservability['regeneration_attempt_count'] ?? 0 }} attempts</div>
                    <div class="text-muted">Success: {{ number_format((float) ($runObservability['regeneration_success_rate'] ?? 0), 2) }}%</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="border rounded p-3 h-100">
                    <div class="text-muted mb-1">Provider Issues</div>
                    <div class="fw-semibold">{{ $runObservability['provider_issue_count'] ?? 0 }}</div>
                    <div class="text-muted">Threshold: {{ $runObservability['slow_response_threshold_ms'] ?? 8000 }} ms</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="pass" {{ $filters['status'] === 'pass' ? 'selected' : '' }}>Pass</option>
                    <option value="fail" {{ $filters['status'] === 'fail' ? 'selected' : '' }}>Fail</option>
                    <option value="skip" {{ $filters['status'] === 'skip' ? 'selected' : '' }}>Skip</option>
                    <option value="error" {{ $filters['status'] === 'error' ? 'selected' : '' }}>Error</option>
                </select>
            </div>
            <div class="col-md-7">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" value="{{ $filters['search'] }}" placeholder="Search case id, prompt, or response">
            </div>
            <div class="col-md-2 d-grid">
                <button class="btn btn-primary" type="submit">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Case</th>
                        <th>Prompt</th>
                        <th>Expected</th>
                        <th>Status</th>
                        <th>Checks</th>
                        <th>Time</th>
                        <th>Inspect</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($results as $result)
                        @php
                            $collapseId = 'lab-run-result-' . $result->id;
                            $signal = $resultSignals[$result->id] ?? [
                                'signal_group' => 'healthy',
                                'signal_label' => 'No major issue detected',
                                'signal_severity' => 'low',
                                'recommended_action' => 'Inspect the full row details if you need more context.',
                            ];
                            $statusClass = match ($result->status) {
                                'pass' => 'bg-success',
                                'fail' => 'bg-danger',
                                'error' => 'bg-dark',
                                default => 'bg-warning',
                            };

                            $intentJson = is_array($result->intent_json ?? null) ? $result->intent_json : [];
                            $entities = is_array($intentJson['entities'] ?? null) ? $intentJson['entities'] : [];
                            $reviewStatus = match ($result->retrain_status) {
                                'done' => 'Resolved',
                                'pending' => 'Observed',
                                default => 'Unreviewed',
                            };
                            $reviewBadgeClass = match ($result->retrain_status) {
                                'done' => 'bg-success-subtle text-success border',
                                'pending' => 'bg-warning-subtle text-warning border',
                                default => 'bg-light text-dark border',
                            };
                        @endphp
                        <tr>
                            <td>{{ $result->case_id }}</td>
                            <td style="max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $result->question }}">{{ $result->question }}</td>
                            <td style="max-width: 260px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $result->expected_summary }}">{{ $result->expected_summary }}</td>
                            <td><span class="badge {{ $statusClass }}">{{ strtoupper($result->status) }}</span></td>
                            <td>
                                <span class="badge {{ $result->keyword_match ? 'bg-success-subtle text-success border' : 'bg-danger-subtle text-danger border' }}">Keywords</span>
                                <span class="badge {{ $result->intent_match === null ? 'bg-light text-dark border' : ($result->intent_match ? 'bg-success-subtle text-success border' : 'bg-danger-subtle text-danger border') }}">Intent</span>
                                <span class="badge {{ $result->entity_match === null ? 'bg-light text-dark border' : ($result->entity_match ? 'bg-success-subtle text-success border' : 'bg-danger-subtle text-danger border') }}">Entities</span>
                                <span class="badge {{ $reviewBadgeClass }}">{{ $reviewStatus }}</span>
                            </td>
                            <td>{{ $result->response_time_ms ? $result->response_time_ms . 'ms' : '—' }}</td>
                            <td>
                                <div class="small text-muted mb-1 text-capitalize">{{ $signal['signal_group'] }}</div>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}">Inspect</button>
                            </td>
                        </tr>
                        <tr class="collapse" id="{{ $collapseId }}">
                            <td colspan="7" class="bg-light">
                                <div class="p-3">
                                    <div class="row g-3">
                                        <div class="col-lg-6">
                                            <h6 class="mb-2">Bot Response</h6>
                                            <pre class="small bg-white border rounded p-2 mb-0" style="white-space: pre-wrap;">{{ $result->actual_response }}</pre>
                                        </div>
                                        <div class="col-lg-6">
                                            <h6 class="mb-2">RAG Context</h6>
                                            <pre class="small bg-white border rounded p-2 mb-0" style="white-space: pre-wrap;">{{ $result->rag_context }}</pre>
                                        </div>
                                        <div class="col-lg-6">
                                            <h6 class="mb-2">Intent Snapshot</h6>
                                            <div class="small text-muted mb-1">Intent: {{ $result->intent_type ?: ($intentJson['intent'] ?? '—') }}</div>
                                            <div class="small text-muted mb-1">Standalone: {{ $result->standalone_query ?: ($intentJson['standalone_query'] ?? '—') }}</div>
                                            <div class="small text-muted">Confidence: {{ $result->intent_confidence !== null ? number_format((float) $result->intent_confidence, 2) : (is_numeric($intentJson['confidence'] ?? null) ? number_format((float) $intentJson['confidence'], 2) : '—') }}</div>
                                        </div>
                                        <div class="col-lg-6">
                                            <h6 class="mb-2">Entity Snapshot</h6>
                                            <div class="small text-muted">brand={{ $entities['brand'] ?? '-' }}, model={{ $entities['model'] ?? '-' }}, slug={{ $entities['product_slug_hint'] ?? '-' }}, color={{ $entities['color'] ?? '-' }}, category={{ $entities['category'] ?? '-' }}</div>
                                        </div>
                                        <div class="col-lg-12">
                                            <h6 class="mb-2">Judge Notes</h6>
                                            <div class="small text-muted">{{ $result->llm_notes ?: 'LLM judge disabled or no notes returned.' }}</div>
                                        </div>
                                        <div class="col-lg-12">
                                            <div class="border rounded bg-white p-3">
                                                <h6 class="mb-2">Actionable Signal</h6>
                                                <div class="small text-muted mb-1">Source: <span class="text-capitalize">{{ $signal['signal_group'] }}</span></div>
                                                <div class="small text-muted mb-1">Signal: {{ $signal['signal_label'] }}</div>
                                                <div class="small text-muted mb-1">Severity: <span class="badge {{ $signal['signal_severity'] === 'high' ? 'bg-danger' : ($signal['signal_severity'] === 'medium' ? 'bg-warning text-dark' : 'bg-success') }}">{{ ucfirst($signal['signal_severity']) }}</span></div>
                                                <div class="small"><strong>Recommended next action:</strong> {{ $signal['recommended_action'] }}</div>
                                            </div>
                                        </div>
                                        <div class="col-lg-12">
                                            <div class="border rounded bg-white p-3">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <h6 class="mb-0">Reviewer Workflow</h6>
                                                    <div class="small text-muted">These actions do not mutate the legacy JSON dataset or trigger reindexing.</div>
                                                </div>

                                                <form method="POST" action="{{ route('admin.chatbot-lab.results.observation', $result) }}" class="row g-3">
                                                    @csrf
                                                    <div class="col-lg-12">
                                                        <label class="form-label">Observation</label>
                                                        <textarea name="observation" rows="3" class="form-control" placeholder="What failed, what should change, or why this answer is acceptable.">{{ $result->admin_feedback }}</textarea>
                                                    </div>
                                                    <div class="col-lg-12 d-flex justify-content-between align-items-center">
                                                        <div class="small text-muted">Current review state: {{ $reviewStatus }}</div>
                                                        <div class="d-flex gap-2">
                                                            <button class="btn btn-outline-primary" type="submit" name="action" value="save">Save Observation</button>
                                                            <button class="btn btn-success" type="submit" name="action" value="resolve">Save And Mark Resolved</button>
                                                        </div>
                                                    </div>
                                                </form>

                                                <div class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                    <div class="small text-muted">Retry this exact case in Manual Lab to inspect a fresh response without launching a full run again.</div>
                                                    <div class="d-flex gap-2">
                                                        <form method="POST" action="{{ route('admin.chatbot-lab.results.rerun', $result) }}">
                                                            @csrf
                                                            <input type="hidden" name="retry_strategy" value="same">
                                                            <button type="submit" class="btn btn-outline-primary">Rerun Same Prompt</button>
                                                        </form>
                                                        <form method="POST" action="{{ route('admin.chatbot-lab.results.rerun', $result) }}">
                                                            @csrf
                                                            <input type="hidden" name="retry_strategy" value="constrained">
                                                            <button type="submit" class="btn btn-primary">Rerun With Constraints</button>
                                                        </form>
                                                    </div>
                                                </div>

                                                <form method="POST" action="{{ route('admin.chatbot-lab.results.promote', $result) }}" class="mt-3 d-flex justify-content-end">
                                                    @csrf
                                                    <div class="d-flex gap-2 ms-auto">
                                                        <button type="submit" class="btn btn-outline-secondary">Promote To Training Case</button>
                                                    </div>
                                                </form>

                                                <form method="POST" action="{{ route('admin.chatbot-lab.results.promote-rerun', $result) }}" class="mt-2 d-flex justify-content-end">
                                                    @csrf
                                                    <input type="hidden" name="retry_strategy" value="constrained">
                                                    <button type="submit" class="btn btn-secondary">Promote And Rerun</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">No results found for the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $results->links() }}
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const card = document.getElementById('run-progress-card');
    if (!card || card.dataset.terminal === '1') {
        return;
    }

    const statusUrl = card.dataset.statusUrl;
    const badge = document.getElementById('run-progress-badge');
    const summary = document.getElementById('run-progress-summary');
    const progressBar = document.getElementById('run-progress-bar');
    const alertBox = document.getElementById('run-status-alert');
    let terminalReached = false;

    const badgeClassFor = (status) => {
        if (status === 'completed') return 'bg-success';
        if (status === 'failed') return 'bg-danger';
        if (status === 'cancelled') return 'bg-warning';
        if (status === 'running') return 'bg-primary';
        return 'bg-secondary';
    };

    const syncStatus = async () => {
        if (terminalReached) {
            return;
        }

        try {
            const response = await fetch(statusUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            const run = payload.run || {};
            const status = run.status || 'pending';
            const percent = Number(run.percent_complete || 0);

            badge.textContent = String(status).toUpperCase();
            badge.className = 'badge ' + badgeClassFor(status);
            summary.textContent = `Processed ${run.processed_cases || 0}/${run.total_cases || 0} cases. Passed: ${run.passed_cases || 0}. Failed: ${run.failed_cases || 0}. Skipped: ${run.skipped_cases || 0}.`;
            progressBar.style.width = `${percent}%`;
            progressBar.className = 'progress-bar ' + badgeClassFor(status);

            if (alertBox && (status === 'pending' || status === 'running')) {
                alertBox.textContent = `This run is currently ${status}. The page updates progress automatically.`;
            }

            if (run.is_terminal) {
                terminalReached = true;
                window.location.reload();
            }
        } catch (error) {
        }
    };

    window.setInterval(syncStatus, 5000);
});
</script>
@endpush
