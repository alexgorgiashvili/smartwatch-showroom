@extends('admin.layout')

@section('title', 'DSers Bridge')

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h3 class="mb-1">DSers Bridge</h3>
        <p class="text-muted mb-0">WooCommerce bridge store to local catalog sync and order control.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ config('services.bridge.base_url') }}/wp-admin/" class="btn btn-outline-primary" target="_blank" rel="noreferrer">
            Bridge WP Admin
        </a>
        <a href="https://www.dsers.com/application/my_products" class="btn btn-outline-secondary" target="_blank" rel="noreferrer">
            Open DSers
        </a>
        <a href="{{ route('admin.bridge.index', ['refresh_remote' => 1]) }}" class="btn btn-outline-dark" data-pjax>
            Refresh bridge data
        </a>
        <form action="{{ route('admin.bridge.sync-all') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-primary">Sync displayed products</button>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="text-muted small mb-1">Bridge URL</div>
                <div class="fw-semibold">{{ $bridgeStatus['base_url'] ?: 'Not set' }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="text-muted small mb-1">Connection</div>
                <div class="fw-semibold {{ $bridgeStatus['reachable'] ? 'text-success' : 'text-danger' }}">
                    {{ $bridgeStatus['reachable'] ? 'Reachable' : 'Unavailable' }}
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="text-muted small mb-1">Authenticated API</div>
                <div class="fw-semibold {{ $bridgeStatus['authenticated'] ? 'text-success' : 'text-danger' }}">
                    {{ $bridgeStatus['authenticated'] ? 'Ready' : 'Failed' }}
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="text-muted small mb-1">Displayed products</div>
                <div class="fw-semibold">{{ $bridgeStatus['product_count'] }}</div>
            </div>
        </div>
    </div>
</div>

@if($bridgeError || $bridgeStatus['error'])
    <div class="alert alert-warning mb-4">
        <strong>Bridge warning:</strong>
        {{ $bridgeError ?: $bridgeStatus['error'] }}
    </div>
@endif

@if(isset($bridgeAlerts) && $bridgeAlerts->isNotEmpty())
    <div class="mb-4 d-grid gap-2">
        @foreach($bridgeAlerts as $alert)
            <div class="alert alert-{{ $alert['level'] ?? 'info' }} mb-0">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <strong>{{ $alert['title'] }}</strong>
                        <div>{{ $alert['message'] }}</div>
                    </div>
                    @if(!empty($alert['href']))
                        <a href="{{ $alert['href'] }}" class="btn btn-sm btn-outline-dark" data-pjax>Open</a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif

<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="card-title mb-1">Bridge products</h5>
                <p class="text-muted mb-0">Displayed bridge catalog is loaded locally; use refresh only when you need a live remote fetch.</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Product</th>
                    <th>Status</th>
                    <th>Type</th>
                    <th>Stock</th>
                    <th>USD Price</th>
                    <th>Local mapping</th>
                    <th class="text-end">Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse($bridgeProducts as $remoteProduct)
                    @php
                        $remoteId = (string) ($remoteProduct['id'] ?? '');
                        $localProduct = $mappedProducts[$remoteId] ?? null;
                    @endphp
                    <tr>
                        <td class="text-muted">{{ $remoteProduct['id'] ?? '—' }}</td>
                        <td style="min-width: 320px;">
                            <div class="fw-semibold">{{ $remoteProduct['name'] ?? 'Untitled' }}</div>
                            <div class="small text-muted">{{ $remoteProduct['permalink'] ?? '' }}</div>
                        </td>
                        <td>
                            <span class="badge {{ ($remoteProduct['status'] ?? '') === 'publish' ? 'bg-success' : 'bg-secondary' }}">
                                {{ $remoteProduct['status'] ?? 'unknown' }}
                            </span>
                        </td>
                        <td>{{ $remoteProduct['type'] ?? 'simple' }}</td>
                        <td>{{ $remoteProduct['stock_quantity'] ?? '—' }}</td>
                        <td>${{ $remoteProduct['price'] ?? '0.00' }}</td>
                        <td>
                            @if($localProduct)
                                <div class="d-flex flex-column gap-1 align-items-start">
                                    <a href="{{ route('admin.products.edit', $localProduct->id) }}" data-pjax class="badge bg-info text-dark text-decoration-none">
                                        Local #{{ $localProduct->id }}
                                    </a>
                                    <span class="badge {{ $localProduct->is_active ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $localProduct->is_active ? 'Storefront active' : 'Pending review' }}
                                    </span>
                                    <span class="badge bg-light text-dark">
                                        {{ $localProduct->fulfillment_mode === 'dropship_bridge' ? 'Dropship' : 'Local stock' }}
                                    </span>
                                    @if($localProduct->product_sync_status)
                                        <small class="text-muted">{{ $localProduct->product_sync_status }}{{ $localProduct->product_synced_at ? ' • ' . $localProduct->product_synced_at->diffForHumans() : '' }}</small>
                                    @endif
                                </div>
                            @else
                                <span class="badge bg-light text-dark">Not synced</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <form action="{{ route('admin.bridge.sync-product', $remoteProduct['id']) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    {{ $localProduct ? 'Update local' : 'Sync to local' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            Bridge products are not loaded yet. Use “Refresh bridge data” to fetch the remote catalog, or sync once remote data is available.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="card-title mb-1">Bridge orders</h5>
                <p class="text-muted mb-0">Eligible local orders, push state, and refresh actions.</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Fulfillment</th>
                    <th>Bridge status</th>
                    <th>Bridge order</th>
                    <th>Tracking</th>
                    <th class="text-end">Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse($bridgeOrders as $bridgeOrder)
                    <tr>
                        <td>
                            <a href="{{ route('admin.orders.show', $bridgeOrder) }}" class="fw-semibold text-decoration-none" data-pjax>
                                {{ $bridgeOrder->order_number }}
                            </a>
                            <div class="small text-muted">{{ $bridgeOrder->dropship_items_count }} dropship item(s)</div>
                        </td>
                        <td>
                            {{ $bridgeOrder->customer_name }}
                            <div class="small text-muted">{{ $bridgeOrder->customer_phone }}</div>
                        </td>
                        <td><span class="badge bg-light text-dark">{{ $bridgeOrder->fulfillment_mode }}</span></td>
                        <td>
                            <span class="badge bg-secondary">{{ $bridgeOrder->bridge_sync_status ?? '—' }}</span>
                            <div class="small text-muted">{{ $bridgeOrder->payment_status }} / {{ $bridgeOrder->status }}</div>
                        </td>
                        <td>{{ $bridgeOrder->bridge_order_number ?: '—' }}</td>
                        <td>{{ $bridgeOrder->tracking_number ?: '—' }}</td>
                        <td class="text-end">
                            <div class="d-flex gap-2 justify-content-end">
                                <form action="{{ route('admin.bridge.push-order', $bridgeOrder) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-primary" {{ $bridgeOrder->bridge_order_id ? 'disabled' : '' }}>
                                        Push
                                    </button>
                                </form>
                                @if($bridgeOrder->bridge_order_id)
                                <form action="{{ route('admin.bridge.refresh-order', $bridgeOrder) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                                        Refresh
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No bridge-eligible orders yet.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
