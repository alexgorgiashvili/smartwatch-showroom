@extends('admin.layout')

@section('title', 'Payments')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-0">Payments</h3>
            <p class="text-muted">Track BOG payment events and statuses.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.payments.index') }}" class="row g-2 align-items-end mb-3">
                <div class="col-lg-2 col-md-6">
                    <label for="status" class="form-label mb-1">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All</option>
                        <option value="pending" @selected($status === 'pending')>Pending</option>
                        <option value="completed" @selected($status === 'completed')>Completed</option>
                        <option value="rejected" @selected($status === 'rejected')>Rejected</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label for="date_from" class="form-label mb-1">From</label>
                    <input type="date" id="date_from" name="date_from" value="{{ $dateFrom }}" class="form-control">
                </div>
                <div class="col-lg-2 col-md-6">
                    <label for="date_to" class="form-label mb-1">To</label>
                    <input type="date" id="date_to" name="date_to" value="{{ $dateTo }}" class="form-control">
                </div>
                <div class="col-lg-4 col-md-6">
                    <label for="search" class="form-label mb-1">Search</label>
                    <input
                        type="text"
                        id="search"
                        name="search"
                        value="{{ $search }}"
                        class="form-control"
                        placeholder="Order #, BOG Order ID, External ID"
                    >
                </div>
                <div class="col-lg-2 col-md-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('admin.payments.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>BOG Order ID</th>
                            <th>External ID</th>
                            <th>Status</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $payment)
                            <tr>
                                <td>
                                    @if($payment->order)
                                        <a href="{{ route('admin.orders.show', $payment->order) }}" class="fw-semibold link-primary">
                                            {{ $payment->order->order_number }}
                                        </a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>{{ $payment->bog_order_id ?: '-' }}</td>
                                <td>{{ $payment->external_order_id ?: '-' }}</td>
                                <td>
                                    @if($payment->chveni_statusi === 'completed')
                                        <span class="badge bg-success">Completed</span>
                                    @elseif($payment->chveni_statusi === 'rejected')
                                        <span class="badge bg-danger">Rejected</span>
                                    @elseif($payment->chveni_statusi === 'pending')
                                        <span class="badge bg-warning">Pending</span>
                                    @else
                                        <span class="badge bg-secondary">{{ $payment->chveni_statusi ?: ($payment->status ?: '-') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($payment->order)
                                        {{ number_format($payment->order->total_amount, 2) }} {{ $payment->order->currency }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td><small>{{ $payment->created_at->format('M d, Y H:i') }}</small></td>
                                <td>
                                    @if(is_array($payment->payment_detail) && !empty($payment->payment_detail))
                                        <details>
                                            <summary class="text-primary">View JSON</summary>
                                            <pre class="mb-0 mt-2 small">{{ json_encode($payment->payment_detail, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </details>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No payment logs found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($payments->hasPages())
            <div class="card-footer">
                {{ $payments->links() }}
            </div>
        @endif
    </div>
@endsection
