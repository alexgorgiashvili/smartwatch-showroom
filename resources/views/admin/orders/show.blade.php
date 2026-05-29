@extends('admin.layout')

@section('title', 'Order ' . $order->order_number . ' — Admin')

@section('content')
@fragment('content')
<div data-page-title="Order {{ $order->order_number }}">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div>
            <h4 class="mb-3 mb-md-0">Order {{ $order->order_number }}</h4>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary btn-sm" data-pjax>
                <i data-feather="arrow-left" style="width:14px;height:14px;"></i> Back
            </a>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        {{-- ── Order Details ── --}}
        <div class="col-xl-8 mb-3">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-title mb-3">Order Details</h6>
                    <div class="row g-3">
                        <div class="col-sm-6 col-md-4">
                            <div class="text-muted small">Customer</div>
                            <div class="fw-bold">{{ $order->customer_name }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="text-muted small">Phone</div>
                            <div class="fw-bold">{{ $order->customer_phone }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="text-muted small">Personal #</div>
                            <div class="fw-bold">{{ $order->personal_number ?? '—' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="text-muted small">City</div>
                            <div class="fw-bold">{{ $order->city ?? '—' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="text-muted small">Address</div>
                            <div class="fw-bold">{{ $order->delivery_address ?? '—' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="text-muted small">Source</div>
                            <div><span class="badge bg-secondary">{{ $order->order_source }}</span></div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="text-muted small">Payment Type</div>
                            <div class="fw-bold">{{ $order->payment_type == 1 ? 'Online' : ($order->payment_type == 2 ? 'Courier' : '—') }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="text-muted small">Created</div>
                            <div class="fw-bold">{{ $order->created_at->format('M d, Y H:i') }}</div>
                        </div>
                        @if($order->notes)
                        <div class="col-12">
                            <div class="text-muted small">Notes</div>
                            <div>{{ $order->notes }}</div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Status + Actions ── --}}
        <div class="col-xl-4 mb-3">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-title mb-3">Status</h6>
                    @php
                        $statusColors = ['pending' => 'warning', 'confirmed' => 'info', 'shipped' => 'primary', 'delivered' => 'success', 'completed' => 'success', 'cancelled' => 'danger'];
                        $payColors = ['pending' => 'warning', 'completed' => 'success', 'rejected' => 'danger'];
                    @endphp
                    <div class="mb-3">
                        <div class="text-muted small mb-1">Order Status</div>
                        <span class="badge bg-{{ $statusColors[$order->status] ?? 'secondary' }} fs-6">{{ ucfirst($order->status) }}</span>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small mb-1">Payment Status</div>
                        <span class="badge bg-{{ $payColors[$order->payment_status] ?? 'secondary' }} fs-6">{{ ucfirst($order->payment_status) }}</span>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small mb-1">Total</div>
                        <div class="fw-bold fs-4 text-primary">{{ $order->currency }} {{ number_format($order->total_amount, 2) }}</div>
                    </div>

                    <hr>

                    {{-- Status Actions --}}
                    @if($order->status !== 'cancelled')
                    <div class="mb-2">
                        <label class="form-label small text-muted">Update Order Status</label>
                        <form method="POST" action="{{ route('admin.orders.update-status', $order) }}" class="d-flex gap-1 flex-wrap">
                            @csrf
                            @method('PATCH')
                            @foreach(['pending', 'shipped', 'delivered', 'cancelled'] as $s)
                                @if($s !== $order->status)
                                <button type="submit" name="status" value="{{ $s }}"
                                        class="btn btn-sm btn-outline-{{ $statusColors[$s] ?? 'secondary' }}"
                                        @if($s === 'cancelled') onclick="return confirm('Cancel this order? Stock will be restored.')" @endif>
                                    {{ ucfirst($s) }}
                                </button>
                                @endif
                            @endforeach
                        </form>
                    </div>
                    @endif

                    <div>
                        <label class="form-label small text-muted">Update Payment Status</label>
                        <form method="POST" action="{{ route('admin.orders.update-payment-status', $order) }}" class="d-flex gap-1 flex-wrap">
                            @csrf
                            @method('PATCH')
                            @foreach(['pending', 'completed', 'rejected'] as $ps)
                                @if($ps !== $order->payment_status)
                                <button type="submit" name="payment_status" value="{{ $ps }}"
                                        class="btn btn-sm btn-outline-{{ $payColors[$ps] ?? 'secondary' }}">
                                    {{ ucfirst($ps) }}
                                </button>
                                @endif
                            @endforeach
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Order Items ── --}}
    <div class="card mb-3">
        <div class="card-body">
            <h6 class="card-title mb-3">Items</h6>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Variant</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $item)
                        <tr>
                            <td class="fw-bold">{{ $item->product_name }}</td>
                            <td>{{ $item->variant_name }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>GEL {{ number_format($item->unit_price, 2) }}</td>
                            <td class="fw-bold">GEL {{ number_format($item->subtotal, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-end fw-bold">Total:</td>
                            <td class="fw-bold text-primary">{{ $order->currency }} {{ number_format($order->total_amount, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    {{-- ── Payment Logs ── --}}
    @if($order->paymentLogs && $order->paymentLogs->count())
    <div class="card mb-3">
        <div class="card-body">
            <h6 class="card-title mb-3">Payment Logs</h6>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Amount</th>
                            <th>Provider</th>
                            <th>Transaction ID</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->paymentLogs as $log)
                        <tr>
                            <td><span class="badge bg-{{ $payColors[$log->status] ?? 'secondary' }}">{{ ucfirst($log->status) }}</span></td>
                            <td>GEL {{ number_format($log->amount ?? 0, 2) }}</td>
                            <td>{{ $log->provider ?? '—' }}</td>
                            <td class="text-muted small">{{ $log->transaction_id ?? '—' }}</td>
                            <td class="text-muted small">{{ $log->created_at->format('M d, Y H:i') }}</td>
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
