@extends('admin.layout')

@section('title', 'Langfuse Dashboard — ადმინი')

@section('content')
@fragment('content')
<div data-page-title="Langfuse დეშბორდი" data-langfuse-dashboard>
    @php
        $health = $snapshot['health'] ?? ['status' => 'warning', 'label' => 'მონაცემი არ ჩანს', 'reasons' => []];
        $healthCardClass = match($health['status'] ?? 'warning') {
            'critical' => 'border-danger',
            'healthy' => 'border-success',
            default => 'border-warning',
        };
        $healthBadgeClass = match($health['status'] ?? 'warning') {
            'critical' => 'bg-danger-subtle text-danger border-danger',
            'healthy' => 'bg-success-subtle text-success border-success',
            default => 'bg-warning-subtle text-warning border-warning',
        };
    @endphp

    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin gap-2">
        <div>
            <h4 class="mb-1">Langfuse დეშბორდი</h4>
            <p class="text-muted mb-0">ოპერატიული პანელი ჩატბოტის ჯანმრთელობის, ხარჯის, შეცდომებისა და ნელი ნაბიჯების სწრაფად სანახავად.</p>
            <p class="text-muted small mb-0 mt-1">ბოლო განახლება: {{ $filters['generated_at'] ?? '—' }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.langfuse-link') }}" class="btn btn-sm btn-outline-secondary" data-pjax>
                გამართვა
            </a>
            <a href="{{ $snapshot['base_url'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-primary">
                გახსენი სრული Langfuse
            </a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.langfuse-dashboard') }}" class="row g-3 align-items-end" data-langfuse-dashboard-form>
                <div class="col-md-3">
                    <label class="form-label small">დროის ფანჯარა</label>
                    <select name="hours" class="form-select form-select-sm">
                        @foreach($hourOptions as $value => $label)
                            <option value="{{ $value }}" {{ (int) $filters['hours'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Observation ლიმიტი</label>
                    <select name="limit" class="form-select form-select-sm">
                        @foreach($limitOptions as $value => $label)
                            <option value="{{ $value }}" {{ (int) $filters['limit'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted mb-1">კონფიგურირებული endpoint</div>
                    <div class="font-monospace small">{{ $snapshot['base_url'] }}</div>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">განახლება</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-langfuse-dashboard-refresh>განახლება</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="card h-100 {{ $snapshot['enabled'] ? ($snapshot['connected'] ? $healthCardClass : 'border-warning') : 'border-danger' }}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <div class="text-muted small">ჯანმრთელობა</div>
                            @if(!$snapshot['enabled'])
                                <div class="h5 mt-2 text-danger">Langfuse გამორთულია</div>
                            @elseif(!$snapshot['connected'])
                                <div class="h5 mt-2 text-warning">კავშირი ვერ დამყარდა</div>
                            @else
                                <div class="h5 mt-2 mb-1">{{ $health['label'] ?? '—' }}</div>
                            @endif
                        </div>
                        @if($snapshot['enabled'] && $snapshot['connected'])
                            <span class="badge {{ $healthBadgeClass }}">{{ strtoupper((string) ($health['status'] ?? 'warning')) }}</span>
                        @endif
                    </div>

                    <div class="small text-muted mt-3">Observation-ები: {{ number_format($snapshot['meta']['observations_count'] ?? 0) }}</div>
                    <div class="small text-muted">ფანჯარა: {{ \Carbon\Carbon::parse($snapshot['meta']['window_start'])->format('Y-m-d H:i') }} - {{ \Carbon\Carbon::parse($snapshot['meta']['window_end'])->format('Y-m-d H:i') }}</div>

                    @if(!empty($health['reasons']))
                        <div class="mt-3">
                            @foreach($health['reasons'] as $reason)
                                <div class="small text-muted mb-1">{{ $reason }}</div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">ოპერატიული შენიშვნები</div>
                    <div class="mt-2 small">ეს გვერდი განკუთვნილია სწრაფი კონტროლისთვის: ჯანმრთელობა, შეცდომები, ნელი ნაბიჯები, ძვირი generation-ები და ბოლო trace-ები.</div>
                    <div class="mt-2 small text-muted">Recent trace-ები და trace რაოდენობა observation-ებიდან არის აგებული. Langfuse-ის ოფიციალურ UI-სთან შედარებით ახალი მონაცემები შეიძლება რამდენიმე წუთით გვიან გამოჩნდეს.</div>
                    <div class="mt-2 small text-muted">თუ ფასი 0 ჩანს, Langfuse-ს ამ generation-ებისთვის შეიძლება მხოლოდ token usage ჰქონდეს ან cost inference ჯერ არ ჰქონდეს.</div>
                    @if(!empty($snapshot['error']))
                        <div class="alert alert-warning py-2 px-3 mt-3 mb-0 small">{{ $snapshot['error'] }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3 col-6">
            <div class="card"><div class="card-body text-center"><div class="text-muted small">Success rate</div><div class="h4 mt-2 mb-0 text-success">{{ number_format((float) ($snapshot['summary']['success_rate'] ?? 0), 1) }}%</div><div class="small text-muted mt-1">{{ number_format($snapshot['summary']['success_count'] ?? 0) }} observation</div></div></div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card"><div class="card-body text-center"><div class="text-muted small">Error rate</div><div class="h4 mt-2 mb-0 text-danger">{{ number_format((float) ($snapshot['summary']['error_rate'] ?? 0), 1) }}%</div><div class="small text-muted mt-1">{{ number_format($snapshot['summary']['error_count'] ?? 0) }} error</div></div></div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card"><div class="card-body text-center"><div class="text-muted small">საშ. latency</div><div class="h4 mt-2 mb-0">{{ $snapshot['summary']['avg_latency_ms'] ?? 0 }}<span class="small text-muted">ms</span></div><div class="small text-muted mt-1">P95: {{ $snapshot['summary']['p95_latency_ms'] ?? 0 }}ms</div></div></div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card"><div class="card-body text-center"><div class="text-muted small">ნელი ნაბიჯები</div><div class="h4 mt-2 mb-0">{{ number_format($snapshot['summary']['slow_observation_count'] ?? 0) }}</div><div class="small text-muted mt-1">3s-ზე მეტი latency</div></div></div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card"><div class="card-body text-center"><div class="text-muted small">უნიკალური Trace</div><div class="h4 mt-2 mb-0">{{ number_format($snapshot['summary']['unique_traces'] ?? 0) }}</div><div class="small text-muted mt-1">observations-based</div></div></div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card"><div class="card-body text-center"><div class="text-muted small">Generation-ები</div><div class="h4 mt-2 mb-0">{{ number_format($snapshot['summary']['generation_count'] ?? 0) }}</div><div class="small text-muted mt-1">სულ observation-ები: {{ number_format($snapshot['summary']['total_observations'] ?? 0) }}</div></div></div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card"><div class="card-body text-center"><div class="text-muted small">ჯამური ფასი</div><div class="h4 mt-2 mb-0">${{ number_format((float) ($snapshot['summary']['total_cost'] ?? 0), 6) }}</div><div class="small text-muted mt-1">Tokens: {{ number_format($snapshot['summary']['total_tokens'] ?? 0) }}</div></div></div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card"><div class="card-body text-center"><div class="text-muted small">საშ. ფასი / Generation</div><div class="h4 mt-2 mb-0">${{ number_format((float) ($snapshot['summary']['avg_cost_per_generation'] ?? 0), 6) }}</div><div class="small text-muted mt-1">USD</div></div></div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">შეცდომების breakdown</h6>
                    <span class="small text-muted">სად ფუჭდება pipeline</span>
                </div>
                <div class="card-body">
                    @if(empty($snapshot['error_breakdown']))
                        <div class="text-muted small">ამ ფანჯარაში შეცდომები არ ჩანს.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Step</th>
                                        <th>Error count</th>
                                        <th>Rate</th>
                                        <th>ბოლო</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($snapshot['error_breakdown'] as $item)
                                        <tr>
                                            <td class="small">
                                                <div class="font-monospace">{{ $item['name'] }}</div>
                                                @if($item['latest_message'] !== '')
                                                    <div class="text-muted">{{ \Illuminate\Support\Str::limit($item['latest_message'], 70) }}</div>
                                                @endif
                                            </td>
                                            <td class="small fw-semibold text-danger">{{ $item['count'] }}</td>
                                            <td class="small">{{ number_format((float) $item['error_rate'], 1) }}%</td>
                                            <td class="small text-muted">{{ $item['latest_at_label'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">ყველაზე ხშირი error message-ები</h6>
                    <span class="small text-muted">ტოპ მიზეზები</span>
                </div>
                <div class="card-body">
                    @if(empty($snapshot['top_error_messages']))
                        <div class="text-muted small">ამ ფანჯარაში error message-ები არ დაბრუნდა.</div>
                    @else
                        <div class="list-group list-group-flush">
                            @foreach($snapshot['top_error_messages'] as $item)
                                <div class="list-group-item px-0 d-flex justify-content-between align-items-start gap-3">
                                    <div class="small">{{ \Illuminate\Support\Str::limit($item['message'], 120) }}</div>
                                    <span class="badge bg-danger-subtle text-danger border">{{ $item['count'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">მოდელები და ღირებულება</h6>
                    <span class="small text-muted">ვისი ხარჯი/latency მეტია</span>
                </div>
                <div class="card-body">
                    @if(empty($snapshot['model_breakdown']))
                        <div class="text-muted small">Generation მონაცემები ჯერ არ არის.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Model</th>
                                        <th>Count</th>
                                        <th>Latency</th>
                                        <th>Cost</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($snapshot['model_breakdown'] as $item)
                                        <tr>
                                            <td class="small">
                                                <div class="font-monospace">{{ $item['model'] }}</div>
                                                <div class="text-muted">error {{ number_format((float) $item['error_rate'], 1) }}%</div>
                                            </td>
                                            <td class="small">{{ $item['count'] }}<div class="text-muted">gen {{ $item['generation_count'] }}</div></td>
                                            <td class="small font-monospace">{{ $item['avg_latency_ms'] !== null ? $item['avg_latency_ms'].'ms' : '—' }}</td>
                                            <td class="small font-monospace">${{ number_format((float) $item['total_cost'], 6) }}<div class="text-muted">{{ number_format($item['total_tokens']) }} tok</div></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">ყველაზე ხშირი observation-ები</h6>
                    <span class="small text-muted">რომელი ნაბიჯებია აქტიური</span>
                </div>
                <div class="card-body">
                    @if(empty($snapshot['top_observations']))
                        <div class="text-muted small">მონაცემები ჯერ არ არის.</div>
                    @else
                        <div class="list-group list-group-flush">
                            @foreach($snapshot['top_observations'] as $item)
                                <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                    <span class="font-monospace small">{{ $item['name'] }}</span>
                                    <span class="badge bg-primary-subtle text-primary border">{{ $item['count'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">ყველაზე ნელი observation-ები</h6>
                    <span class="small text-muted">სად იკარგება დრო</span>
                </div>
                <div class="card-body p-0">
                    @if(empty($snapshot['slow_observations']))
                        <div class="p-3 text-muted small">ნელი observation-ები არ ჩანს.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Observation</th>
                                        <th>Latency</th>
                                        <th>User / Session</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($snapshot['slow_observations'] as $item)
                                        <tr>
                                            <td class="small">
                                                <div class="font-monospace">{{ $item['name'] }}</div>
                                                <div class="text-muted">{{ \Illuminate\Support\Str::limit($item['trace_id'], 18) }} · {{ $item['latest_at_label'] }}</div>
                                            </td>
                                            <td class="small font-monospace">{{ $item['latency_ms'] !== null ? $item['latency_ms'].'ms' : '—' }}</td>
                                            <td class="small">
                                                <div>{{ $item['user_id'] !== '' ? $item['user_id'] : '—' }}</div>
                                                <div class="text-muted">{{ $item['session_id'] !== '' ? $item['session_id'] : '—' }}</div>
                                            </td>
                                            <td class="text-end small">
                                                @if($item['trace_url'] !== '')
                                                    <a href="{{ $item['trace_url'] }}" target="_blank" rel="noopener" class="btn btn-xs btn-outline-secondary">Trace</a>
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
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">ყველაზე ძვირი observation-ები</h6>
                    <span class="small text-muted">რომელი call-ებია ძვირი</span>
                </div>
                <div class="card-body p-0">
                    @if(empty($snapshot['expensive_observations']))
                        <div class="p-3 text-muted small">ძვირი observation-ები არ ჩანს.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Observation</th>
                                        <th>Cost</th>
                                        <th>Tokens</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($snapshot['expensive_observations'] as $item)
                                        <tr>
                                            <td class="small">
                                                <div class="font-monospace">{{ $item['name'] }}</div>
                                                <div class="text-muted">model {{ $item['model'] !== '' ? $item['model'] : 'model უცნობია' }}</div>
                                            </td>
                                            <td class="small font-monospace">${{ number_format((float) $item['total_cost'], 6) }}</td>
                                            <td class="small font-monospace">{{ number_format($item['total_tokens']) }}</td>
                                            <td class="text-end small">
                                                @if($item['trace_url'] !== '')
                                                    <a href="{{ $item['trace_url'] }}" target="_blank" rel="noopener" class="btn btn-xs btn-outline-secondary">Trace</a>
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
    </div>

    <div class="card">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <div>
                <h6 class="mb-0">ბოლო Trace-ები</h6>
                <div class="small text-muted mt-1">Trace სია observation-ების მიხედვით არის დაჯგუფებული. აქედან იპოვი საეჭვო trace-ს და საჭიროებისას გახსნი Langfuse-ში.</div>
            </div>
        </div>
        <div class="card-body p-0">
            @if(empty($snapshot['recent_traces']))
                <div class="text-center text-muted py-5">
                    <i data-feather="activity" style="width:48px;height:48px;"></i>
                    <p class="mt-2 mb-0">Trace-ები ჯერ არ არის ხელმისაწვდომი ამ ფანჯარაში.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>დრო</th>
                                <th>Trace</th>
                                <th>რა მოხდა</th>
                                <th>User / Session</th>
                                <th>Latency</th>
                                <th>Tokens / Cost</th>
                                <th>სტატუსი</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($snapshot['recent_traces'] as $trace)
                                <tr>
                                    <td class="small text-nowrap">{{ $trace['latest_at_label'] }}</td>
                                    <td class="small">
                                        <div class="font-monospace">{{ \Illuminate\Support\Str::limit($trace['trace_id'], 18) }}</div>
                                        <div class="text-muted">{{ $trace['observation_count'] }} obs · {{ $trace['generation_count'] }} gen</div>
                                    </td>
                                    <td class="small">
                                        <div class="fw-medium">{{ $trace['primary_name'] }}</div>
                                        <div class="text-muted">{{ implode(', ', array_slice($trace['observation_names'], 0, 3)) }}</div>
                                        @if(!empty($trace['latest_error_message']))
                                            <div class="text-danger mt-1">{{ \Illuminate\Support\Str::limit($trace['latest_error_message'], 70) }}</div>
                                        @endif
                                    </td>
                                    <td class="small">
                                        <div>{{ $trace['user_id'] !== '' ? $trace['user_id'] : '—' }}</div>
                                        <div class="text-muted">{{ $trace['session_id'] !== '' ? $trace['session_id'] : '—' }}</div>
                                    </td>
                                    <td class="small font-monospace">
                                        <div>{{ $trace['avg_latency_ms'] !== null ? $trace['avg_latency_ms'].'ms' : '—' }}</div>
                                        <div class="text-muted">max {{ $trace['max_latency_ms'] !== null ? $trace['max_latency_ms'].'ms' : '—' }}</div>
                                    </td>
                                    <td class="small font-monospace">
                                        <div>{{ number_format($trace['total_tokens'] ?? 0) }}</div>
                                        <div class="text-muted">${{ number_format((float) ($trace['total_cost'] ?? 0), 6) }}</div>
                                    </td>
                                    <td class="small">
                                        @if($trace['has_error'])
                                            <span class="badge bg-danger-subtle text-danger border">შეცდომა</span>
                                        @else
                                            <span class="badge bg-success-subtle text-success border">OK</span>
                                        @endif
                                        @if(!empty($trace['models']))
                                            <div class="text-muted mt-1">{{ implode(', ', $trace['models']) }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end small">
                                        @if($trace['trace_url'] !== '')
                                            <a href="{{ $trace['trace_url'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">გახსენი</a>
                                        @else
                                            <span class="text-muted">—</span>
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
