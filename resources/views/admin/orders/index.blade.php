@extends('admin.layout')

@section('title', 'Orders')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-0">Orders</h3>
            <p class="text-muted">Manage all customer orders.</p>
        </div>
        <a href="{{ route('admin.orders.create') }}" class="btn btn-primary">Create Order</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.orders.index') }}" class="row g-2 align-items-end mb-3">
                <div class="col-md-4">
                    <label for="payment_status" class="form-label mb-1">Payment Status</label>
                    <select name="payment_status" id="payment_status" class="form-select">
                        <option value="">All</option>
                        <option value="pending" @selected($paymentStatus === 'pending')>Pending</option>
                        <option value="completed" @selected($paymentStatus === 'completed')>Completed</option>
                        <option value="rejected" @selected($paymentStatus === 'rejected')>Rejected</option>
                    </select>
                </div>
                <div class="col-md-8 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>

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
                            <th>Payment</th>
                            <th>Date</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            <tr>
                                <td class="fw-semibold">{{ $order->order_number }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $order->customer_name }}</div>
                                    <small class="text-muted">{{ $order->customer_phone }}</small>
                                </td>
                                <td>{{ $order->items->count() }} item(s)</td>
                                <td class="fw-semibold">{{ number_format($order->total_amount, 2) }} {{ $order->currency }}</td>
                                <td>
                                    <span class="badge bg-secondary">{{ $order->order_source }}</span>
                                </td>
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
                                <td>
                                    @if(is_null($order->payment_type))
                                        <span class="text-muted">-</span>
                                    @elseif($order->payment_status === 'completed')
                                        <span class="badge bg-success">Completed</span>
                                    @elseif($order->payment_status === 'rejected')
                                        <span class="badge bg-danger">Rejected</span>
                                    @else
                                        <span class="badge bg-warning">Pending</span>
                                    @endif
                                </td>
                                <td>
                                    <small>{{ $order->created_at->format('M d, Y') }}</small>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-sm btn-outline-primary">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">No orders yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($orders->hasPages())
            <div class="card-footer">
                {{ $orders->links() }}
            </div>
        @endif
    </div>
@endsection
