@extends('admin.layout')

@section('title', 'AI Traffic Analytics — Admin')

@section('content')
@fragment('content')
<div data-page-title="AI ტრაფიკის ანალიტიკა">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div><h4 class="mb-3 mb-md-0">AI ტრაფიკის ანალიტიკა</h4></div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="text-muted small">სულ ვიზიტები</div>
                    <div class="h3 mb-0 mt-1">{{ number_format($stats['total_visits']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <div class="text-muted small">დღეს</div>
                    <div class="h3 mb-0 mt-1 text-primary">{{ number_format($stats['today_visits']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <div class="text-muted small">კვირაში</div>
                    <div class="h3 mb-0 mt-1 text-info">{{ number_format($stats['week_visits']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <div class="text-muted small">თვეში</div>
                    <div class="h3 mb-0 mt-1 text-success">{{ number_format($stats['month_visits']) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Visits by Family -->
        <div class="col-md-6 mb-3">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0">ვიზიტები AI ოჯახების მიხედვით</h6>
                </div>
                <div class="card-body">
                    @if(empty($visitsByFamily))
                        <div class="text-center text-muted py-3">მონაცემები არ არის</div>
                    @else
                        <canvas id="visitsByFamilyChart"></canvas>
                    @endif
                </div>
            </div>
        </div>

        <!-- Top Bots -->
        <div class="col-md-6 mb-3">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0">ტოპ 10 AI ბოტი</h6>
                </div>
                <div class="card-body">
                    @if(empty($topBots))
                        <div class="text-center text-muted py-3">მონაცემები არ არის</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th class="small">AI ბოტი</th>
                                        <th class="small">ოჯახი</th>
                                        <th class="small text-end">ვიზიტები</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($topBots as $bot)
                                    <tr>
                                        <td class="small">{{ $bot->ai_bot }}</td>
                                        <td class="small"><span class="badge bg-secondary">{{ $bot->ai_family }}</span></td>
                                        <td class="small text-end fw-bold">{{ number_format($bot->count) }}</td>
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

    <!-- Top Paths -->
    <div class="card mb-3">
        <div class="card-header bg-light">
            <h6 class="mb-0">ტოპ 10 მონახულებული გვერდი</h6>
        </div>
        <div class="card-body">
            @if(empty($topPaths))
                <div class="text-center text-muted py-3">მონაცემები არ არის</div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="small">გვერდი</th>
                                <th class="small text-end">ვიზიტები</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($topPaths as $path)
                            <tr>
                                <td class="small font-monospace">{{ $path->path }}</td>
                                <td class="small text-end fw-bold">{{ number_format($path->count) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- Recent Visits -->
    <div class="card">
        <div class="card-header bg-light">
            <h6 class="mb-0">ბოლო 20 ვიზიტი</h6>
        </div>
        <div class="card-body">
            @if(empty($recentVisits))
                <div class="text-center text-muted py-3">მონაცემები არ არის</div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="small">დრო</th>
                                <th class="small">AI ბოტი</th>
                                <th class="small">ოჯახი</th>
                                <th class="small">მისამართი</th>
                                <th class="small">IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentVisits as $visit)
                            <tr>
                                <td class="small text-nowrap">{{ \Carbon\Carbon::parse($visit->created_at)->format('M d, H:i') }}</td>
                                <td class="small">{{ $visit->ai_bot }}</td>
                                <td class="small"><span class="badge bg-secondary-subtle text-secondary border">{{ $visit->ai_family }}</span></td>
                                <td class="small font-monospace">{{ Str::limit($visit->path, 50) }}</td>
                                <td class="small font-monospace">{{ $visit->ip_address ?? '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

@if(!empty($visitsByFamily))
@php
    $chartData = [
        'labels' => array_keys($visitsByFamily),
        'data' => array_values($visitsByFamily),
    ];
@endphp
<script id="ai-analytics-chart-data" type="application/json">{!! json_encode($chartData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endif
@endfragment
@endsection

@push('scripts')
@if(!empty($visitsByFamily))
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!document.getElementById('ai-analytics-chart-data')) return;

    const chartData = JSON.parse(document.getElementById('ai-analytics-chart-data').textContent);
    const ctx = document.getElementById('visitsByFamilyChart');

    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: chartData.labels,
            datasets: [{
                data: chartData.data,
                backgroundColor: [
                    '#0d6efd', '#6610f2', '#6f42c1', '#d63384', '#dc3545',
                    '#fd7e14', '#ffc107', '#198754', '#20c997', '#0dcaf0'
                ],
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            }
        }
    });
});
</script>
@endif
@endpush
