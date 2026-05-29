@extends('admin.layout')

@section('title', 'Competitor Monitor — Admin')

@section('content')
@fragment('content')
<div data-page-title="Competitor Monitor">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div><h4 class="mb-3 mb-md-0">Competitor Monitor</h4></div>
        <div>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSourceModal">
                <i data-feather="plus" style="width:16px;height:16px;"></i> Add Source
            </button>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('status') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    {{-- Source Tabs --}}
    @if($sources->count())
    <ul class="nav nav-tabs mb-3">
        @foreach($sources as $s)
        <li class="nav-item">
            <a class="nav-link {{ $source && $source->id === $s->id ? 'active' : '' }}"
               href="{{ route('admin.competitors.index', ['source_id' => $s->id]) }}" data-pjax>
                {{ $s->name }}
                <span class="badge bg-secondary ms-1">{{ $s->products_count ?? '' }}</span>
            </a>
        </li>
        @endforeach
    </ul>
    @endif

    {{-- Products Table --}}
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Price</th>
                            <th>Snapshots</th>
                            <th>Mapped To</th>
                            <th>Last Seen</th>
                            <th style="width:120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $cp)
                        <tr>
                            <td>
                                <a href="{{ $cp->url }}" target="_blank" class="text-decoration-none">{{ Str::limit($cp->name, 50) }}</a>
                            </td>
                            <td class="fw-bold">{{ $cp->currency ?? '' }} {{ $cp->price ? number_format($cp->price, 2) : '—' }}</td>
                            <td>{{ $cp->snapshots_count }}</td>
                            <td>
                                @if($cp->mapping && $cp->mapping->product)
                                    <span class="badge bg-success">{{ Str::limit($cp->mapping->product->name_en, 30) }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $cp->last_seen_at?->diffForHumans() ?? '—' }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.competitors.mapping', $cp) }}" class="d-flex gap-1">
                                    @csrf
                                    <select class="form-select form-select-sm" name="product_id" style="max-width:150px;">
                                        <option value="">— Map —</option>
                                        @foreach($localProducts as $lp)
                                        <option value="{{ $lp->id }}" {{ $cp->mapping?->product_id == $lp->id ? 'selected' : '' }}>
                                            {{ Str::limit($lp->name_en ?: $lp->name_ka, 25) }}
                                        </option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-outline-primary btn-sm p-1" title="Save">
                                        <i data-feather="check" style="width:14px;height:14px;"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">No competitor products found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($products->hasPages())
            <div class="mt-3">{{ $products->links() }}</div>
            @endif
        </div>
    </div>
</div>

{{-- Add Source Modal --}}
<div class="modal fade" id="addSourceModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.competitors.sources.store') }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Add Competitor Source</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Category URL <span class="text-danger">*</span></label>
                    <input type="url" class="form-control" name="category_url" required>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                    <label class="form-check-label">Active</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Add Source</button>
            </div>
        </form>
    </div>
</div>
@endfragment
@endsection
