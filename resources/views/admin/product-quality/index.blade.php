@extends('admin.layout')

@section('title', 'Product Quality Intelligence — Admin')

@section('content')
@fragment('content')
<div data-page-title="Product Quality Intelligence">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div>
            <h4 class="mb-1">Product Quality Intelligence</h4>
            <p class="text-muted mb-0">Catalog and ad-hoc research runs backed by stored evidence.</p>
        </div>
        <div>
            <a href="{{ route('admin.product-quality.create') }}" class="btn btn-primary btn-sm d-flex align-items-center gap-1" data-pjax>
                <i data-feather="plus" style="width:16px;height:16px;"></i> Start Research
            </a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Research Targets</div>
                    <div class="h4 mb-0">{{ number_format($stats['total_targets']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Completed</div>
                    <div class="h4 mb-0">{{ number_format($stats['completed_targets']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Queued / Running</div>
                    <div class="h4 mb-0">{{ number_format($stats['queued_or_running_targets']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Ad-hoc Targets</div>
                    <div class="h4 mb-0">{{ number_format($stats['ad_hoc_targets']) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            @if($targets->count() === 0)
                <div class="text-center py-5">
                    <i data-feather="shield" class="text-muted" style="width:40px;height:40px;"></i>
                    <h5 class="mt-3">No research targets yet</h5>
                    <p class="text-muted mb-3">Start with a catalog product or an ad-hoc source URL/model.</p>
                    <a href="{{ route('admin.product-quality.create') }}" class="btn btn-primary btn-sm" data-pjax>Start Research</a>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Target</th>
                                <th>Mode</th>
                                <th>Latest Status</th>
                                <th>Verdict</th>
                                <th>Evidence</th>
                                <th>Updated</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($targets as $target)
                                @php $analysis = $target->latestAnalysis; @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $target->display_name }}</div>
                                        <div class="small text-muted">
                                            @if($target->product)
                                                Catalog product
                                            @elseif($target->brand || $target->model)
                                                {{ trim(($target->brand ?? '') . ' ' . ($target->model ?? '')) }}
                                            @else
                                                {{ $target->source_url ?: 'Ad-hoc target' }}
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge {{ $target->mode === 'catalog' ? 'bg-primary' : 'bg-secondary' }}">
                                            {{ $target->mode === 'catalog' ? 'Catalog' : 'Ad-hoc' }}
                                        </span>
                                    </td>
                                    <td>
                                        @if(!$analysis)
                                            <span class="badge bg-light text-dark border">Not run</span>
                                        @elseif($analysis->status === 'completed')
                                            <span class="badge bg-success">Completed</span>
                                        @elseif($analysis->status === 'failed')
                                            <span class="badge bg-danger">Failed</span>
                                        @elseif($analysis->status === 'running')
                                            <span class="badge bg-warning text-dark">Running</span>
                                        @else
                                            <span class="badge bg-info text-dark">Queued</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($analysis?->verdict)
                                            <span class="small fw-semibold">{{ str_replace('_', ' ', $analysis->verdict) }}</span>
                                            <div class="text-muted small">{{ $analysis->confidence_score }}/100</div>
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $analysis?->evidence_count ?? 0 }}</td>
                                    <td class="small text-muted">{{ $target->updated_at?->diffForHumans() }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.product-quality.show', $target) }}" class="btn btn-outline-primary btn-sm" data-pjax>
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $targets->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endfragment
@endsection
