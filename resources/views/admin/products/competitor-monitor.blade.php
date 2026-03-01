@extends('admin.layout')

@section('title', 'Competitor Monitor')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
        <div>
            <h3 class="mb-0">Competitor Monitor</h3>
            <p class="text-muted mb-0">Track competitor products and price history across multiple source pages.</p>
        </div>

        @if ($source)
            <form method="POST" action="{{ route('admin.competitors.refresh', $source) }}">
                @csrf
                <button type="submit" class="btn btn-primary">Refresh Selected Source</button>
            </form>
        @endif
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-lg-4">
                    <div class="text-muted small mb-1">Source</div>
                    <form method="GET" action="{{ route('admin.competitors.index') }}">
                        <select name="source_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            @foreach ($sources as $availableSource)
                                <option value="{{ $availableSource->id }}" @selected(optional($source)->id === $availableSource->id)>
                                    {{ $availableSource->name }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                </div>

                <div class="col-lg-5">
                    <div class="text-muted small">Category URL</div>
                    @if ($source)
                        <a href="{{ $source->category_url }}" target="_blank" rel="noopener" class="fw-semibold">Open source page</a>
                    @else
                        <div class="text-muted">No source selected</div>
                    @endif
                </div>

                <div class="col-lg-3">
                    <div class="text-muted small">Last Refresh</div>
                    <div class="fw-semibold">{{ $source?->last_synced_at?->format('Y-m-d H:i') ?? 'Never' }}</div>
                    @if (optional($source)->last_status === 'failed' && optional($source)->last_error)
                        <div class="text-danger small mt-1">{{ $source->last_error }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="fw-semibold mb-2">Add Source Page</div>
            <form method="POST" action="{{ route('admin.competitors.sources.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-4">
                    <label class="form-label form-label-sm">Name</label>
                    <input type="text" name="name" class="form-control form-control-sm" placeholder="Source name" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label form-label-sm">Category URL</label>
                    <input type="url" name="category_url" class="form-control form-control-sm" placeholder="https://example.com/category" required>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-outline-primary btn-sm">Add Source</button>
                </div>
                <input type="hidden" name="is_active" value="1">
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="min-width: 260px;">Competitor Product</th>
                            <th style="width: 140px;">Price</th>
                            <th style="width: 140px;">Old Price</th>
                            <th style="width: 120px;">Stock</th>
                            <th style="width: 120px;">SKU/ID</th>
                            <th style="width: 130px;">History</th>
                            <th style="min-width: 320px;">Map to Our Product</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $item)
                            <tr>
                                <td>
                                    <div class="d-flex gap-2 align-items-start">
                                        <img
                                            src="{{ $item->image_url ?: asset('assets/images/others/placeholder.jpg') }}"
                                            alt="{{ $item->title }}"
                                            class="rounded"
                                            style="width: 56px; height: 56px; object-fit: cover;"
                                        >
                                        <div>
                                            <a href="{{ $item->product_url }}" target="_blank" rel="noopener" class="fw-semibold d-block">
                                                {{ $item->title }}
                                            </a>
                                            <div class="text-muted small">Last seen: {{ $item->last_seen_at?->format('Y-m-d H:i') ?? '-' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if ($item->current_price !== null)
                                        <span class="fw-semibold">{{ number_format((float) $item->current_price, 2) }} {{ $item->currency }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($item->old_price !== null)
                                        <span class="text-muted text-decoration-line-through">{{ number_format((float) $item->old_price, 2) }} {{ $item->currency }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($item->is_in_stock === true)
                                        <span class="badge bg-success">In Stock</span>
                                    @elseif ($item->is_in_stock === false)
                                        <span class="badge bg-danger">Out</span>
                                    @else
                                        <span class="badge bg-secondary">Unknown</span>
                                    @endif
                                </td>
                                <td>{{ $item->external_product_id ?: '-' }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $item->snapshots_count }}</div>
                                    <div class="text-muted small">entries</div>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.competitors.mapping', $item) }}" class="d-flex gap-2">
                                        @csrf
                                        <input type="hidden" name="source_id" value="{{ optional($source)->id }}">
                                        <select name="product_id" class="form-select form-select-sm">
                                            <option value="">Not mapped</option>
                                            @foreach ($localProducts as $local)
                                                <option value="{{ $local->id }}" @selected(optional($item->mapping)->product_id === $local->id)>
                                                    #{{ $local->id }} — {{ $local->name_ka ?: $local->name_en }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn btn-outline-primary btn-sm">Save</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">No competitor products yet. Click "Refresh Now" to scrape data.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($products->hasPages())
                <div class="mt-3">
                    {{ $products->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
