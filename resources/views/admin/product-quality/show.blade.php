@extends('admin.layout')

@section('title', $target->display_name . ' — Product Quality — Admin')

@section('content')
@fragment('content')
@php $analysis = $target->latestAnalysis; @endphp
<div data-page-title="Product Quality — {{ $target->display_name }}">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div>
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                <h4 class="mb-0">{{ $target->display_name }}</h4>
                <span class="badge {{ $target->mode === 'catalog' ? 'bg-primary' : 'bg-secondary' }}">
                    {{ $target->mode === 'catalog' ? 'Catalog' : 'Ad-hoc' }}
                </span>
            </div>
            <p class="text-muted mb-0">
                @if($target->product)
                    Catalog product linked to <a href="{{ route('admin.products.edit', $target->product) }}" data-pjax>{{ $target->product->name_ka ?: $target->product->name_en }}</a>
                @elseif($target->source_url)
                    {{ $target->source_url }}
                @else
                    Research target without catalog linkage
                @endif
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.product-quality.index') }}" class="btn btn-outline-secondary btn-sm" data-pjax>Back</a>
            <form method="POST" action="{{ route('admin.product-quality.run', $target) }}">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm">Queue Re-run</button>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Latest Status</div>
                    <div class="fw-semibold">
                        @if(!$analysis)
                            Not run
                        @else
                            {{ ucfirst($analysis->status) }}
                        @endif
                    </div>
                    @if($analysis?->error_message)
                        <div class="text-danger small mt-1">{{ $analysis->error_message }}</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Verdict</div>
                    <div class="fw-semibold">{{ $analysis?->verdict ? str_replace('_', ' ', $analysis->verdict) : '—' }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Confidence</div>
                    <div class="fw-semibold">{{ $analysis?->confidence_score ? $analysis->confidence_score . '/100' : '—' }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Evidence</div>
                    <div class="fw-semibold">{{ $analysis?->evidence_count ?? $target->evidenceItems->count() }}</div>
                    <div class="small text-muted">End-user {{ $analysis?->end_user_evidence_count ?? 0 }} / Supplier {{ $analysis?->supplier_evidence_count ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="text-muted small">Brand</div>
                    <div>{{ $target->brand ?: '—' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Model</div>
                    <div>{{ $target->model ?: '—' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">External Source</div>
                    <div>{{ $target->external_source ?: '—' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">External Product ID</div>
                    <div>{{ $target->external_product_id ?: '—' }}</div>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3" id="product-quality-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#raw-evidence" type="button">Raw Evidence</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#normalized-evidence" type="button">Normalized</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#ai-summary" type="button">AI Summary</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#comparison" type="button">Comparison</button>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="raw-evidence">
            <div class="card">
                <div class="card-body">
                    @if($target->evidenceItems->isEmpty())
                        <p class="text-muted mb-0">No evidence stored yet. Queue a run with manual evidence, Apify JSON, or an Alibaba source URL.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Source</th>
                                        <th>Author</th>
                                        <th>Rating</th>
                                        <th>Evidence</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($target->evidenceItems as $item)
                                        <tr>
                                            <td class="small">
                                                <div class="fw-semibold">{{ $item->source_type }}</div>
                                                @if($item->source_url)
                                                    <div><a href="{{ $item->source_url }}" target="_blank" rel="noopener noreferrer">{{ \Illuminate\Support\Str::limit($item->source_url, 40) }}</a></div>
                                                @endif
                                            </td>
                                            <td class="small">
                                                <div>{{ $item->author_name ?: 'Unknown' }}</div>
                                                <div class="text-muted">{{ $item->author_type }}</div>
                                            </td>
                                            <td class="small">{{ $item->rating_raw ?? '—' }}</td>
                                            <td class="small">
                                                @if($item->title)
                                                    <div class="fw-semibold">{{ $item->title }}</div>
                                                @endif
                                                <div>{{ $item->body_text }}</div>
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

        <div class="tab-pane fade" id="normalized-evidence">
            <div class="card">
                <div class="card-body">
                    @if($target->evidenceItems->isEmpty())
                        <p class="text-muted mb-0">Normalized evidence will appear after ingestion.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Sentiment</th>
                                        <th>Features</th>
                                        <th>Issues</th>
                                        <th>Excerpt</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($target->evidenceItems as $item)
                                        @php $normalized = $item->normalized_payload ?? []; @endphp
                                        <tr>
                                            <td class="small">{{ $normalized['sentiment_label'] ?? '—' }}</td>
                                            <td class="small">{{ implode(', ', $normalized['feature_mentions'] ?? []) ?: '—' }}</td>
                                            <td class="small">{{ implode(', ', $normalized['issue_tags'] ?? []) ?: '—' }}</td>
                                            <td class="small">{{ $normalized['excerpt'] ?? \Illuminate\Support\Str::limit($item->body_text, 180) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="ai-summary">
            <div class="card">
                <div class="card-body">
                    @if(!$analysis || !$analysis->summary_json)
                        <p class="text-muted mb-0">No completed analysis yet.</p>
                    @else
                        @php $summary = $analysis->summary_json; @endphp
                        <div class="row g-4">
                            <div class="col-md-6">
                                <h6>Strengths</h6>
                                <ul class="mb-3">
                                    @forelse($summary['strengths'] ?? [] as $item)
                                        <li>{{ $item }}</li>
                                    @empty
                                        <li class="text-muted">No repeated strengths detected.</li>
                                    @endforelse
                                </ul>

                                <h6>Recurring Praise</h6>
                                <ul class="mb-0">
                                    @forelse($summary['recurring_praise'] ?? [] as $item)
                                        <li>{{ $item }}</li>
                                    @empty
                                        <li class="text-muted">No repeated praise detected.</li>
                                    @endforelse
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Weaknesses</h6>
                                <ul class="mb-3">
                                    @forelse($summary['weaknesses'] ?? [] as $item)
                                        <li>{{ $item }}</li>
                                    @empty
                                        <li class="text-muted">No repeated weaknesses detected.</li>
                                    @endforelse
                                </ul>

                                <h6>Recurring Complaints</h6>
                                <ul class="mb-0">
                                    @forelse($summary['recurring_complaints'] ?? [] as $item)
                                        <li>{{ $item }}</li>
                                    @empty
                                        <li class="text-muted">No repeated complaints detected.</li>
                                    @endforelse
                                </ul>
                            </div>
                        </div>

                        <hr>

                        <div class="row g-4">
                            <div class="col-md-6">
                                <h6>Risk Flags</h6>
                                <ul>
                                    @forelse($summary['risk_flags'] ?? [] as $item)
                                        <li>{{ $item }}</li>
                                    @empty
                                        <li class="text-muted">No major risk flags detected.</li>
                                    @endforelse
                                </ul>

                                <h6>Evidence Gaps</h6>
                                <ul class="mb-0">
                                    @forelse($summary['evidence_gaps'] ?? [] as $item)
                                        <li>{{ $item }}</li>
                                    @empty
                                        <li class="text-muted">No major evidence gaps recorded.</li>
                                    @endforelse
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Rubric Snapshot</h6>
                                @php $rubric = $summary['rubric'] ?? []; @endphp
                                <div class="table-responsive">
                                    <table class="table table-sm mb-3">
                                        <tbody>
                                            @foreach($rubric as $key => $score)
                                                <tr>
                                                    <td class="text-muted">{{ str_replace('_', ' ', ucfirst($key)) }}</td>
                                                    <td class="fw-semibold text-end">{{ $score }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <h6>Verdict Rationale</h6>
                                <p class="mb-0">{{ $summary['verdict_rationale'] ?? '—' }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="comparison">
            <div class="card mb-3">
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.product-quality.show', $target) }}">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-8">
                                <label class="form-label">Compare Against Completed Targets</label>
                                <div class="border rounded p-3" style="max-height: 220px; overflow:auto;">
                                    @forelse($comparisonCandidates as $candidate)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="compare_target_ids[]" id="compare_{{ $candidate->id }}" value="{{ $candidate->id }}" {{ in_array($candidate->id, $selectedCompareIds, true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="compare_{{ $candidate->id }}">
                                                {{ $candidate->display_name }}
                                                <span class="text-muted small">— {{ $candidate->latestAnalysis?->verdict ? str_replace('_', ' ', $candidate->latestAnalysis->verdict) : 'completed' }}</span>
                                            </label>
                                        </div>
                                    @empty
                                        <div class="text-muted small">No other completed analyses available yet.</div>
                                    @endforelse
                                </div>
                            </div>
                            <div class="col-md-4 d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Compare</button>
                                <a href="{{ route('admin.product-quality.show', $target) }}#comparison" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    @if(!$comparisonResult)
                        <p class="text-muted mb-0">Select one or more completed targets to generate a stored-evidence comparison.</p>
                    @else
                        <div class="mb-3">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <h6 class="mb-0">Comparison Summary</h6>
                                <span class="badge {{ $comparisonResult['firmness'] === 'firm' ? 'bg-success' : ($comparisonResult['firmness'] === 'provisional' ? 'bg-warning text-dark' : 'bg-secondary') }}">
                                    {{ ucfirst($comparisonResult['firmness']) }}
                                </span>
                            </div>
                            <p class="mb-0">{{ $comparisonResult['comparison_summary'] }}</p>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-4">
                                <h6>Key Differences</h6>
                                <ul class="mb-0">
                                    @forelse($comparisonResult['key_differences'] ?? [] as $item)
                                        <li>{{ $item }}</li>
                                    @empty
                                        <li class="text-muted">No strong separation detected.</li>
                                    @endforelse
                                </ul>
                            </div>
                            <div class="col-md-4">
                                <h6>Winner Weaker Areas</h6>
                                <ul class="mb-0">
                                    @forelse($comparisonResult['winner_weaker_areas'] ?? [] as $item)
                                        <li>{{ $item }}</li>
                                    @empty
                                        <li class="text-muted">No standout weaker areas recorded.</li>
                                    @endforelse
                                </ul>
                            </div>
                            <div class="col-md-4">
                                <h6>Risk Notes</h6>
                                <ul class="mb-0">
                                    @forelse($comparisonResult['risk_notes'] ?? [] as $item)
                                        <li>{{ $item }}</li>
                                    @empty
                                        <li class="text-muted">No additional risk notes.</li>
                                    @endforelse
                                </ul>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($target->analyses->isNotEmpty())
        <div class="card mt-3">
            <div class="card-body">
                <h6 class="card-title">Recent Analysis History</h6>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Status</th>
                                <th>Verdict</th>
                                <th>Evidence</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($target->analyses as $historyItem)
                                <tr>
                                    <td>#{{ $historyItem->id }}</td>
                                    <td>{{ ucfirst($historyItem->status) }}</td>
                                    <td>{{ $historyItem->verdict ? str_replace('_', ' ', $historyItem->verdict) : '—' }}</td>
                                    <td>{{ $historyItem->evidence_count }}</td>
                                    <td>{{ $historyItem->created_at?->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endfragment
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    window.AdminProductQuality && window.AdminProductQuality.initShow();
});
</script>
@endpush
