@extends('admin.layout')

@section('title', 'ჩატბოტის ტრეისები — Admin')

@section('content')
@fragment('content')
<div data-page-title="ჩატბოტის ტრეისები">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div><h4 class="mb-3 mb-md-0">ჩატბოტის ტრეისები</h4></div>
        <div>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                <i data-feather="refresh-cw" style="width:14px;height:14px;"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.chatbot-traces') }}" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label small">დროის ფანჯარა</label>
                    <select name="hours" class="form-select form-select-sm">
                        @foreach($hourOptions as $value => $label)
                            <option value="{{ $value }}" {{ $filters['hours'] == $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">ნაბიჯის ძებნა</label>
                    <input type="text" name="step" class="form-control form-control-sm" value="{{ $filters['step_search'] }}" placeholder="pipeline.intent...">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">ლიმიტი</label>
                    <select name="limit" class="form-select form-select-sm">
                        @foreach($limitOptions as $value => $label)
                            <option value="{{ $value }}" {{ $filters['limit'] == $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <div class="form-check">
                        <input type="checkbox" name="fallback" value="1" class="form-check-input" id="fallbackOnly" {{ $filters['fallback_only'] ? 'checked' : '' }}>
                        <label class="form-check-label small" for="fallbackOnly">Fallback მხოლოდ</label>
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <div class="form-check">
                        <input type="checkbox" name="multi" value="1" class="form-check-input" id="multiAgentOnly" {{ $filters['multi_agent_only'] ? 'checked' : '' }}>
                        <label class="form-check-label small" for="multiAgentOnly">Multi-agent</label>
                    </div>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm w-100">გაფილტვრა</button>
                </div>
            </form>
            <div class="mt-2 text-muted small">
                პერიოდი: {{ \Carbon\Carbon::parse($meta['window_start'])->format('Y-m-d H:i') }} - {{ \Carbon\Carbon::parse($meta['window_end'])->format('Y-m-d H:i') }}
            </div>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="text-muted small">Pipeline ნაბიჯები</div>
                    <div class="h3 mb-0 mt-1">{{ number_format($summary['total_pipeline_steps'] ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="text-muted small">უნიკალური Trace ID</div>
                    <div class="h3 mb-0 mt-1">{{ number_format($summary['unique_trace_ids'] ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="text-muted small">საშუალო პასუხის დრო</div>
                    <div class="h3 mb-0 mt-1">{{ $summary['avg_response_time_ms'] ?? 0 }} <span class="small text-muted">ms</span></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="text-muted small">ვალიდაციის Pass Rate</div>
                    <div class="h3 mb-0 mt-1">{{ number_format((float) ($summary['validation_pass_rate'] ?? 0), 1) }}%</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Multi-Agent Stats -->
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="text-muted small">Multi-Agent დაწყებული</div>
                    <div class="h4 mb-0 mt-1">{{ number_format($summary['multi_agent_started'] ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-success">
                <div class="card-body text-center">
                    <div class="text-muted small">Multi-Agent დასრულებული</div>
                    <div class="h4 mb-0 mt-1 text-success">{{ number_format($summary['multi_agent_completed'] ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-danger">
                <div class="card-body text-center">
                    <div class="text-muted small">Multi-Agent ჩავარდნა</div>
                    <div class="h4 mb-0 mt-1 text-danger">{{ number_format($summary['multi_agent_failed'] ?? 0) }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Traces Table -->
    <div class="card">
        <div class="card-header bg-light">
            <h6 class="mb-0">Pipeline ჩანაწერები</h6>
            <div class="text-muted small mt-1">
                ნაპოვნი ჩანაწერები: {{ $meta['entries_count'] ?? 0 }} | დამუშავებული ლოგ ხაზები: {{ $meta['matched_log_lines'] ?? 0 }}
            </div>
        </div>
        <div class="card-body p-0">
            @if(empty($entries))
                <div class="text-center text-muted py-5">
                    <i data-feather="alert-circle" style="width:48px;height:48px;"></i>
                    <p class="mt-2">ვერ მოიძებნა pipeline ჩანაწერები ამ კრიტერიებით</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="small">დრო</th>
                                <th class="small">Trace</th>
                                <th class="small">Conv</th>
                                <th class="small">ნაბიჯი</th>
                                <th class="small">ტიპი</th>
                                <th class="small">Latency</th>
                                <th class="small">Val</th>
                                <th class="small">FB</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($entries as $entry)
                            <tr>
                                <td class="small text-nowrap">{{ $entry['timestamp_label'] ?? '—' }}</td>
                                <td class="small font-monospace">{{ Str::limit($entry['trace_id'] ?? '—', 12) }}</td>
                                <td class="small">{{ $entry['conversation_id'] ?? '—' }}</td>
                                <td class="small font-monospace">{{ $entry['step'] ?? '—' }}</td>
                                <td class="small">
                                    @if(!empty($entry['is_multi_agent']))
                                        <span class="badge bg-primary-subtle text-primary border">multi-agent</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="small">
                                    @if(isset($entry['response_time_ms']))
                                        <span class="font-monospace">{{ $entry['response_time_ms'] }}ms</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="small">
                                    @if(isset($entry['validation_passed']))
                                        @if($entry['validation_passed'])
                                            <span class="badge bg-success-subtle text-success border">წარმატება</span>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger border">ჩავარდნა</span>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="small">
                                    @if(!empty($entry['fallback_used']))
                                        <span class="badge bg-warning-subtle text-warning border">FB</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endfragment
@endsection

@push('scripts')
<script>
// Auto-refresh every 30 seconds
setTimeout(() => location.reload(), 30000);
</script>
@endpush
