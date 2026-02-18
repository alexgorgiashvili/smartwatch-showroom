@extends('admin.layout')

@section('title', 'Order Details')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-0">Order #{{ $order->order_number }}</h3>
            <p class="text-muted">View and manage order details.</p>
        </div>
        <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary">Back to Orders</a>
    </div>

    <!-- Order Status and Actions -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1">Status:
                                @if($order->status === 'pending')
                                    <span class="badge bg-warning">Pending</span>
                                @elseif($order->status === 'shipped')
                                    <span class="badge bg-info">Shipped</span>
                                @elseif($order->status === 'delivered')
                                    <span class="badge bg-success">Delivered</span>
                                @elseif($order->status === 'cancelled')
                                    <span class="badge bg-danger">Cancelled</span>
                                @endif
                            </h5>
                            <small class="text-muted">Created: {{ $order->created_at->format('M d, Y H:i') }}</small>
                        </div>
                        @if(!$order->isCancelled())
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#statusModal">Change Status</button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-2">Total Amount</h5>
                    <h3 class="mb-0">{{ number_format($order->total_amount, 2) }} {{ $order->currency }}</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Customer Information -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Customer Information</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-lg-6">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th style="width: 150px;">Name:</th>
                            <td>{{ $order->customer_name }}</td>
                        </tr>
                        <tr>
                            <th>Phone:</th>
                            <td>{{ $order->customer_phone }}</td>
                        </tr>
                        <tr>
                            <th>Email:</th>
                            <td>{{ $order->customer_email ?: '-' }}</td>
                        </tr>
                    </table>
                </div>
                <div class="col-lg-6">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th style="width: 150px;">Delivery Address:</th>
                            <td>{{ $order->delivery_address }}</td>
                        </tr>
                        <tr>
                            <th>City:</th>
                            <td>{{ $order->city ?: '-' }}</td>
                        </tr>
                        <tr>
                            <th>Postal Code:</th>
                            <td>{{ $order->postal_code ?: '-' }}</td>
                        </tr>
                        <tr>
                            <th>Order Source:</th>
                            <td><span class="badge bg-secondary">{{ $order->order_source }}</span></td>
                        </tr>
                    </table>
                </div>
            </div>
            @if($order->notes)
                <div class="mt-3">
                    <strong>Notes:</strong>
                    <p class="mb-0">{{ $order->notes }}</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Order Items -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Order Items</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Variant</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-center">Quantity</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order->items as $item)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.products.edit', $item->variant->product) }}" class="link-primary">
                                        {{ $item->product_name }}
                                    </a>
                                </td>
                                <td>{{ $item->variant_name }}</td>
                                <td class="text-end">{{ number_format($item->unit_price, 2) }} {{ $order->currency }}</td>
                                <td class="text-center">{{ $item->quantity }}</td>
                                <td class="text-end fw-semibold">{{ number_format($item->subtotal, 2) }} {{ $order->currency }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-end">Total:</th>
                            <th class="text-end">{{ number_format($order->total_amount, 2) }} {{ $order->currency }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Actions -->
    @if($order->canBeCancelled())
        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('admin.orders.destroy', $order) }}" onsubmit="return confirm('Are you sure? Stock will be restored.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">Delete Order</button>
            </form>
        </div>
    @endif

    <!-- Status Change Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.orders.update-status', $order) }}">
                    @csrf
                    @method('PATCH')
                    <div class="modal-header">
                        <h5 class="modal-title">Change Order Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select name="status" id="status" class="form-select" required>
                                <option value="pending" @selected($order->status === 'pending')>Pending</option>
                                <option value="shipped" @selected($order->status === 'shipped')>Shipped</option>
                                <option value="delivered" @selected($order->status === 'delivered')>Delivered</option>
                                <option value="cancelled" @selected($order->status === 'cancelled')>Cancelled</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="status_notes" class="form-label">Notes (optional)</label>
                            <textarea name="notes" id="status_notes" class="form-control" rows="2"></textarea>
                            <small class="text-muted">Add notes about this status change (e.g., reason for cancellation)</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
