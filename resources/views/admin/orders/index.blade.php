@extends('admin.layout')

@section('title', 'Orders - Admin')

@section('content')
@fragment('content')
<div data-page-title="შეკვეთები">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div>
            <h4 class="mb-3 mb-md-0">შეკვეთები</h4>
        </div>
        <div class="d-flex gap-2">
            <div class="btn-group btn-group-sm" role="group">
                <a href="{{ route('admin.orders.index') }}" class="btn {{ !$paymentStatus ? 'btn-primary' : 'btn-outline-primary' }}" data-pjax>ყველა</a>
                <a href="{{ route('admin.orders.index', ['payment_status' => 'pending']) }}" class="btn {{ $paymentStatus === 'pending' ? 'btn-warning' : 'btn-outline-warning' }}" data-pjax>Pending</a>
                <a href="{{ route('admin.orders.index', ['payment_status' => 'completed']) }}" class="btn {{ $paymentStatus === 'completed' ? 'btn-success' : 'btn-outline-success' }}" data-pjax>Completed</a>
                <a href="{{ route('admin.orders.index', ['payment_status' => 'rejected']) }}" class="btn {{ $paymentStatus === 'rejected' ? 'btn-danger' : 'btn-outline-danger' }}" data-pjax>Rejected</a>
            </div>
            <a href="{{ route('admin.orders.create') }}" class="btn btn-primary btn-sm d-flex align-items-center gap-1" data-pjax>
                <i data-feather="plus" style="width:16px;height:16px;"></i> ახალი შეკვეთა
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th>შეკვეთა #</th>
                            <th>კლიენტი</th>
                            <th>ნივთები</th>
                            <th>ჯამი</th>
                            <th>წყარო</th>
                            <th>Order Status</th>
                            <th>Payment</th>
                            <th>Bridge</th>
                            <th>თარიღი</th>
                            <th style="width:80px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                        @php
                            $statusColors = ['pending' => 'warning', 'confirmed' => 'info', 'shipped' => 'primary', 'delivered' => 'success', 'completed' => 'success', 'cancelled' => 'danger'];
                            $payColors = ['pending' => 'warning', 'completed' => 'success', 'rejected' => 'danger'];
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('admin.orders.show', $order) }}" class="fw-bold text-decoration-none" data-pjax>{{ $order->order_number }}</a>
                            </td>
                            <td>
                                {{ $order->customer_name }}
                                <div class="small">
                                    <a href="tel:{{ $order->dialable_phone }}" class="text-decoration-none" title="დარეკვა">
                                        <i data-feather="phone-call" style="width:12px;height:12px;"></i> {{ $order->customer_phone }}
                                    </a>
                                </div>
                            </td>
                            <td>
                                {{ $order->items->count() }}
                                @if($order->requiresBridgePush())
                                    <div class="small text-muted">{{ $order->dropshipItems()->count() }} bridge</div>
                                @endif
                            </td>
                            <td class="fw-bold">{{ $order->currency }} {{ number_format($order->total_amount, 2) }}</td>
                            <td><span class="badge bg-secondary">{{ $order->order_source }}</span></td>
                            <td><span class="badge bg-{{ $statusColors[$order->status] ?? 'secondary' }}">{{ ucfirst($order->status) }}</span></td>
                            <td><span class="badge bg-{{ $payColors[$order->payment_status] ?? 'secondary' }}">{{ ucfirst($order->payment_status) }}</span></td>
                            <td>
                                <span class="badge bg-light text-dark">{{ $order->bridge_sync_status ?? '—' }}</span>
                                @if($order->bridge_order_number)
                                    <div class="small text-muted">#{{ $order->bridge_order_number }}</div>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $order->created_at->format('M d, Y') }}</td>
                            <td>
                                <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-outline-primary btn-sm p-1" data-pjax title="ნახვა">
                                    <i data-feather="eye" style="width:14px;height:14px;"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-3">შეკვეთები ვერ მოიძებნა</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($orders->hasPages())
            <div class="mt-3">
                {{ $orders->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endfragment
@endsection
