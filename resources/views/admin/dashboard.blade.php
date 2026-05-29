@extends('admin.layout')

@section('title', 'Dashboard — Admin')

@section('content')
@fragment('content')
<div data-page-title="Dashboard">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div>
            <h4 class="mb-3 mb-md-0">Dashboard</h4>
        </div>
        <div>
            <span class="text-muted">{{ now()->format('l, M d Y') }}</span>
        </div>
    </div>

    {{-- ── Row 1: Overview Stats ── --}}
    <div class="row mb-4">
        <div class="col-sm-6 col-xl-3 mb-3 mb-xl-0">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-value text-primary">{{ number_format($totalProducts) }}</div>
                        <div class="stat-label">Products</div>
                    </div>
                    <div class="stat-icon bg-primary bg-opacity-10">
                        <i data-feather="tag" class="text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3 mb-3 mb-xl-0">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-value text-info">{{ number_format($totalOrders) }}</div>
                        <div class="stat-label">Orders</div>
                    </div>
                    <div class="stat-icon bg-info bg-opacity-10">
                        <i data-feather="shopping-cart" class="text-info"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3 mb-3 mb-xl-0">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-value text-success">GEL {{ number_format($totalRevenue, 2) }}</div>
                        <div class="stat-label">Revenue</div>
                    </div>
                    <div class="stat-icon bg-success bg-opacity-10">
                        <i data-feather="dollar-sign" class="text-success"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3 mb-3 mb-xl-0">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-value text-warning">{{ number_format($unreadConversations) }}</div>
                        <div class="stat-label">Unread Inbox</div>
                    </div>
                    <div class="stat-icon bg-warning bg-opacity-10">
                        <i data-feather="message-circle" class="text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Row 2: Orders Chart + Quick Actions ── --}}
    <div class="row mb-4">
        <div class="col-xl-8 mb-3 mb-xl-0">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-title">Orders &amp; Revenue — Last 30 Days</h6>
                    <div id="ordersChart"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-title mb-3">Quick Actions</h6>
                    <div class="d-grid gap-2">
                        <a href="{{ route('admin.products.create') }}" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-2" data-pjax>
                            <i data-feather="plus" style="width:16px;height:16px;"></i> Add Product
                        </a>
                        <a href="{{ route('admin.orders.create') }}" class="btn btn-outline-success btn-sm d-flex align-items-center gap-2" data-pjax>
                            <i data-feather="shopping-cart" style="width:16px;height:16px;"></i> Create Order
                        </a>
                        <a href="{{ route('admin.articles.create') }}" class="btn btn-outline-info btn-sm d-flex align-items-center gap-2" data-pjax>
                            <i data-feather="edit" style="width:16px;height:16px;"></i> Write Article
                        </a>
                        <a href="{{ route('admin.inbox.index') }}" class="btn btn-outline-warning btn-sm d-flex align-items-center gap-2" data-pjax>
                            <i data-feather="message-circle" style="width:16px;height:16px;"></i> Open Inbox
                        </a>
                        <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2" data-pjax>
                            <i data-feather="list" style="width:16px;height:16px;"></i> View All Orders
                        </a>
                        <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2" data-pjax>
                            <i data-feather="tag" style="width:16px;height:16px;"></i> View All Products
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Row 3: Commerce + Inventory Detail Stats ── --}}
    <div class="row mb-4">
        <div class="col-xl-6 mb-3 mb-xl-0">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-title mb-3">Commerce</h6>
                    <div class="row g-3">
                        <div class="col-6 col-md-4">
                            <div class="text-muted small">Pending Orders</div>
                            <div class="fw-bold text-warning fs-5">{{ number_format($pendingOrders) }}</div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="text-muted small">Completed Payments</div>
                            <div class="fw-bold text-success fs-5">{{ number_format($completedPayments) }}</div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="text-muted small">Pending Payments</div>
                            <div class="fw-bold text-info fs-5">{{ number_format($pendingPayments) }}</div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="text-muted small">Rejected Payments</div>
                            <div class="fw-bold text-danger fs-5">{{ number_format($rejectedPayments) }}</div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="text-muted small">Inquiries</div>
                            <div class="fw-bold text-info fs-5">{{ number_format($totalInquiries) }}</div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="text-muted small">Users / Admins</div>
                            <div class="fw-bold text-secondary fs-5">{{ number_format($totalUsers) }} / {{ number_format($totalAdmins) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-title mb-3">Inventory</h6>
                    <div class="row g-3">
                        <div class="col-6 col-md-3">
                            <div class="text-muted small">Total Units</div>
                            <div class="fw-bold text-success fs-5">{{ number_format($totalInventory) }}</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-muted small">Low Stock</div>
                            <div class="fw-bold text-warning fs-5">{{ number_format($lowStockCount) }}</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-muted small">Out of Stock</div>
                            <div class="fw-bold text-danger fs-5">{{ number_format($outOfStockCount) }}</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-muted small">7d Changes</div>
                            <div class="fw-bold text-info fs-5">{{ number_format($recentAdjustments) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Row 4: Chatbot Quality ── --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title mb-3">Chatbot Quality — Today</h6>
                    <div class="row g-3">
                        <div class="col-6 col-md">
                            <div class="text-muted small">Responses</div>
                            <div class="fw-bold text-primary fs-5">{{ number_format($chatbotStats['responses_today']) }}</div>
                        </div>
                        <div class="col-6 col-md">
                            <div class="text-muted small">Fallback Rate</div>
                            <div class="fw-bold fs-5 {{ $chatbotStats['fallback_rate'] >= 0.2 ? 'text-danger' : ($chatbotStats['fallback_rate'] >= 0.1 ? 'text-warning' : 'text-success') }}">
                                {{ number_format($chatbotStats['fallback_rate'] * 100, 1) }}%
                            </div>
                        </div>
                        <div class="col-6 col-md">
                            <div class="text-muted small">Non-Georgian</div>
                            <div class="fw-bold fs-5 {{ $chatbotStats['non_georgian_rate'] >= 0.2 ? 'text-danger' : ($chatbotStats['non_georgian_rate'] >= 0.1 ? 'text-warning' : 'text-success') }}">
                                {{ number_format($chatbotStats['non_georgian_rate'] * 100, 1) }}%
                            </div>
                        </div>
                        <div class="col-6 col-md">
                            <div class="text-muted small">Auto-Reply Accept</div>
                            <div class="fw-bold fs-5 {{ $chatbotStats['auto_reply_accept_rate'] >= 0.8 ? 'text-success' : ($chatbotStats['auto_reply_accept_rate'] >= 0.6 ? 'text-warning' : 'text-danger') }}">
                                {{ number_format($chatbotStats['auto_reply_accept_rate'] * 100, 1) }}%
                            </div>
                        </div>
                        <div class="col-6 col-md">
                            <div class="text-muted small">Provider Incidents</div>
                            <div class="fw-bold fs-5 {{ $chatbotStats['provider_incidents'] > 0 ? 'text-danger' : 'text-success' }}">
                                {{ number_format($chatbotStats['provider_incidents']) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Row 5: Recent Orders ── --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="card-title mb-0">Recent Orders</h6>
                        <a href="{{ route('admin.orders.index') }}" class="btn btn-sm btn-outline-primary" data-pjax>View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Source</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentOrders as $order)
                                <tr style="cursor:pointer" onclick="window.AdminRouter.navigate('{{ route('admin.orders.show', $order) }}')">
                                    <td class="fw-bold">{{ $order->order_number }}</td>
                                    <td>
                                        {{ $order->customer_name }}
                                        <div class="text-muted small">{{ $order->customer_phone }}</div>
                                    </td>
                                    <td>{{ $order->items_count ?? $order->items->count() }}</td>
                                    <td>{{ $order->currency }} {{ number_format($order->total_amount, 2) }}</td>
                                    <td><span class="badge bg-secondary">{{ $order->order_source }}</span></td>
                                    <td>
                                        @php
                                            $statusColors = ['pending' => 'warning', 'confirmed' => 'info', 'completed' => 'success', 'cancelled' => 'danger', 'shipped' => 'primary'];
                                        @endphp
                                        <span class="badge bg-{{ $statusColors[$order->status] ?? 'secondary' }}">{{ ucfirst($order->status) }}</span>
                                    </td>
                                    <td>
                                        @php
                                            $payColors = ['pending' => 'warning', 'completed' => 'success', 'rejected' => 'danger'];
                                        @endphp
                                        <span class="badge bg-{{ $payColors[$order->payment_status] ?? 'secondary' }}">{{ ucfirst($order->payment_status) }}</span>
                                    </td>
                                    <td class="text-muted small">{{ $order->created_at->format('M d, Y') }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="8" class="text-center text-muted py-3">No orders yet</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Row 6: Recent Inquiries + Recent Stock Adjustments ── --}}
    <div class="row mb-4">
        <div class="col-xl-6 mb-3 mb-xl-0">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="card-title mb-0">Recent Inquiries</h6>
                        <a href="{{ route('admin.inquiries.index') }}" class="btn btn-sm btn-outline-primary" data-pjax>View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Product</th>
                                    <th>Contact</th>
                                    <th>Message</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentInquiries as $inquiry)
                                <tr>
                                    <td class="fw-bold">{{ $inquiry->name }}</td>
                                    <td class="text-muted small">{{ $inquiry->product->name ?? '—' }}</td>
                                    <td><span class="badge bg-info">{{ $inquiry->preferred_contact ?? '—' }}</span></td>
                                    <td class="text-muted small" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $inquiry->message }}</td>
                                    <td class="text-muted small">{{ $inquiry->created_at->diffForHumans() }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="5" class="text-center text-muted py-3">No inquiries yet</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="card-title mb-0">Recent Stock Adjustments</h6>
                        <a href="{{ route('admin.products.index') }}" class="btn btn-sm btn-outline-primary" data-pjax>Manage</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Variant</th>
                                    <th>Change</th>
                                    <th>Reason</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentStockAdjustments as $adj)
                                <tr>
                                    <td class="fw-bold">{{ $adj->variant->product->name_en ?? '—' }}</td>
                                    <td class="text-muted small">{{ $adj->variant->name ?? '—' }}</td>
                                    <td>
                                        <span class="badge bg-{{ $adj->quantity_change >= 0 ? 'success' : 'danger' }}">
                                            {{ $adj->quantity_change > 0 ? '+' : '' }}{{ $adj->quantity_change }}
                                        </span>
                                    </td>
                                    <td class="text-muted small" style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $adj->reason }}</td>
                                    <td class="text-muted small">{{ $adj->created_at->diffForHumans() }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="5" class="text-center text-muted py-3">No adjustments yet</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Orders chart data for JS --}}
<script id="orders-chart-data" type="application/json">@json($ordersChart)</script>
@endfragment
@endsection

@push('scripts')
<script src="{{ asset('assets/vendors/apexcharts/apexcharts.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    window.AdminDashboard && window.AdminDashboard.init();
});
</script>
@endpush
