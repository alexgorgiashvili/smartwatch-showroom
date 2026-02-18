@extends('admin.layout')

@section('title', 'Dashboard')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-0">Dashboard</h3>
            <p class="text-muted">Welcome back to your admin panel.</p>
        </div>
    </div>

    <!-- Metrics Row -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-2">Total Products</p>
                            <h4 class="mb-0">{{ $totalProducts }}</h4>
                        </div>
                        <div>
                            <i class="bi bi-box2 text-primary" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-2">Total Inquiries</p>
                            <h4 class="mb-0">{{ $totalInquiries }}</h4>
                        </div>
                        <div>
                            <i class="bi bi-chat-dots text-warning" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-2">Total Users</p>
                            <h4 class="mb-0">{{ $totalUsers }}</h4>
                        </div>
                        <div>
                            <i class="bi bi-people text-success" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-2">Admin Users</p>
                            <h4 class="mb-0">{{ $totalAdmins }}</h4>
                        </div>
                        <div>
                            <i class="bi bi-shield-check text-danger" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stock Metrics Row -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-warning">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-2">Low Stock Items</p>
                            <h4 class="mb-0 text-warning">{{ $lowStockCount }}</h4>
                        </div>
                        <div>
                            <i class="bi bi-exclamation-triangle text-warning" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-danger">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-2">Out of Stock</p>
                            <h4 class="mb-0 text-danger">{{ $outOfStockCount }}</h4>
                        </div>
                        <div>
                            <i class="bi bi-exclamation-circle text-danger" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-info">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-2">Total Inventory</p>
                            <h4 class="mb-0 text-info">{{ $totalInventory }}</h4>
                        </div>
                        <div>
                            <i class="bi bi-boxes text-info" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <a href="{{ route('admin.products.index') }}" class="text-decoration-none">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted mb-2">Manage Products</p>
                                <h4 class="mb-0">View All</h4>
                            </div>
                            <div>
                                <i class="bi bi-arrow-right" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Metrics Row -->
    <div class="row mb-4">
        <div class="col-lg-4 col-md-6 mb-3">
            <div class="card border-primary">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-2">Total Orders</p>
                            <h4 class="mb-0 text-primary">{{ $totalOrders }}</h4>
                        </div>
                        <div>
                            <i class="bi bi-cart-check text-primary" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6 mb-3">
            <div class="card border-warning">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-2">Pending Orders</p>
                            <h4 class="mb-0 text-warning">{{ $pendingOrders }}</h4>
                        </div>
                        <div>
                            <i class="bi bi-hourglass-split text-warning" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6 mb-3">
            <div class="card border-success">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-2">Total Revenue</p>
                            <h4 class="mb-0 text-success">{{ number_format($totalRevenue, 2) }} GEL</h4>
                        </div>
                        <div>
                            <i class="bi bi-currency-exchange text-success" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Inquiries and Stock Section -->
    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recent Inquiries</h5>
                </div>
                <div class="card-body">
                    @if($recentInquiries->count())
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Contact</th>
                                        <th>Product</th>
                                        <th>Date</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentInquiries as $inquiry)
                                        <tr>
                                            <td class="fw-semibold">{{ $inquiry->name }}</td>
                                            <td>
                                                <small>{{ $inquiry->email }}<br>{{ $inquiry->phone }}</small>
                                            </td>
                                            <td>
                                                @if($inquiry->product)
                                                    <a href="{{ route('admin.products.edit', $inquiry->product) }}" class="link-primary">
                                                        {{ $inquiry->product->name_en }}
                                                    </a>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                <small>{{ $inquiry->created_at->format('M d, Y') }}</small>
                                            </td>
                                            <td class="text-end">
                                                <a href="{{ route('admin.inquiries.show', $inquiry) }}" class="btn btn-sm btn-outline-primary">
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted text-center py-4">No inquiries yet.</p>
                    @endif
                </div>
                @if($totalInquiries > 5)
                    <div class="card-footer">
                        <a href="{{ route('admin.inquiries.index') }}" class="btn btn-sm btn-primary">View All Inquiries</a>
                    </div>
                @endif
            </div>
        </div>

        <!-- Quick Actions Section -->
        <div class="col-lg-4">
            <!-- Quick Actions Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('admin.products.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-lg"></i> Add Product
                        </a>
                        <a href="{{ route('admin.orders.create') }}" class="btn btn-primary">
                            <i class="bi bi-cart-plus"></i> Create Order
                        </a>
                        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
                            <i class="bi bi-person-plus"></i> Add User
                        </a>
                        <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-primary">
                            <i class="bi bi-cart-check"></i> View Orders
                        </a>
                        <a href="{{ route('admin.inquiries.index') }}" class="btn btn-outline-primary">
                            <i class="bi bi-chat-dots"></i> View Inquiries
                        </a>
                        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-primary">
                            <i class="bi bi-people"></i> Manage Users
                        </a>
                        <a href="{{ route('admin.products.index') }}" class="btn btn-outline-primary">
                            <i class="bi bi-box2"></i> View Products
                        </a>
                    </div>
                </div>
            </div>

            <!-- Recent Stock Adjustments Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recent Stock Changes</h5>
                </div>
                <div class="card-body">
                    @if($recentAdjustments->count())
                        <div style="max-height: 400px; overflow-y: auto;">
                            @foreach($recentAdjustments as $adjustment)
                                <div class="mb-3 pb-3 border-bottom">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <small class="fw-semibold">{{ $adjustment->variant->product->name_en }}</small>
                                        <span class="badge @if($adjustment->quantity_change > 0) bg-success @else bg-danger @endif">
                                            {{ $adjustment->quantity_change > 0 ? '+' : '' }}{{ $adjustment->quantity_change }}
                                        </span>
                                    </div>
                                    <small class="text-muted d-block">{{ $adjustment->variant->name }}</small>
                                    <small class="text-muted d-block">{{ $adjustment->reason }} • {{ $adjustment->created_at->diffForHumans() }}</small>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted text-center py-4 mb-0">No recent adjustments.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Orders Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recent Orders</h5>
                </div>
                <div class="card-body">
                    @if($recentOrders->count())
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Source</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentOrders as $order)
                                        <tr>
                                            <td class="fw-semibold">{{ $order->order_number }}</td>
                                            <td>
                                                <div class="fw-semibold">{{ $order->customer_name }}</div>
                                                <small class="text-muted">{{ $order->customer_phone }}</small>
                                            </td>
                                            <td>{{ $order->items->count() }} item(s)</td>
                                            <td class="fw-semibold">{{ number_format($order->total_amount, 2) }} {{ $order->currency }}</td>
                                            <td><span class="badge bg-secondary">{{ $order->order_source }}</span></td>
                                            <td>
                                                @if($order->status === 'pending')
                                                    <span class="badge bg-warning">Pending</span>
                                                @elseif($order->status === 'shipped')
                                                    <span class="badge bg-info">Shipped</span>
                                                @elseif($order->status === 'delivered')
                                                    <span class="badge bg-success">Delivered</span>
                                                @elseif($order->status === 'cancelled')
                                                    <span class="badge bg-danger">Cancelled</span>
                                                @endif
                                            </td>
                                            <td><small>{{ $order->created_at->format('M d, Y') }}</small></td>
                                            <td class="text-end">
                                                <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-sm btn-outline-primary">View</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted text-center py-4 mb-0">No orders yet.</p>
                    @endif
                </div>
                @if($totalOrders > 5)
                    <div class="card-footer">
                        <a href="{{ route('admin.orders.index') }}" class="btn btn-sm btn-primary">View All Orders</a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Old Quick Actions (to be removed) -->
    <div class="row d-none">
        <div class="col-lg-8 mb-4">
    </div>
@endsection
