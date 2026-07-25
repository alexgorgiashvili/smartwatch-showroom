@extends('admin.layout')

@section('title', 'Order ' . $order->order_number . ' - Admin')

@section('content')
@fragment('content')
<div data-page-title="შეკვეთა {{ $order->order_number }}">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div>
            <h4 class="mb-3 mb-md-0">შეკვეთა {{ $order->order_number }}</h4>
        </div>
        <div class="d-flex gap-2">
            @if($canEditItems)
                <a href="{{ route('admin.orders.edit', $order) }}" class="btn btn-primary btn-sm" data-pjax>
                    <i data-feather="edit-2" style="width:14px;height:14px;"></i> რედაქტირება
                </a>
            @endif
            <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary btn-sm" data-pjax>
                <i data-feather="arrow-left" style="width:14px;height:14px;"></i> უკან
            </a>
            @if($order->isCancelled() || $order->canBeCancelled())
                <form method="POST" action="{{ route('admin.orders.destroy', $order) }}" onsubmit="return confirm('წავშალოთ ეს შეკვეთა? ეს მოქმედება შეუქცევადია.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i data-feather="trash-2" style="width:14px;height:14px;"></i> წაშლა
                    </button>
                </form>
            @endif
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

    @php
        $statusColors = ['pending' => 'warning', 'confirmed' => 'info', 'shipped' => 'primary', 'delivered' => 'success', 'completed' => 'success', 'cancelled' => 'danger'];
        $payColors = ['pending' => 'warning', 'completed' => 'success', 'rejected' => 'danger'];
    @endphp

    <div class="row">
        <div class="col-xl-8 mb-3">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-title mb-3">შეკვეთის დეტალები</h6>
                    <div class="row g-3">
                        <div class="col-sm-6 col-md-4">
                            <div class="text-muted small">კლიენტი</div>
                            <div class="fw-bold">{{ $order->customer_name }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="text-muted small">ტელეფონი</div>
                            <a href="tel:{{ $order->dialable_phone }}" class="fw-bold text-decoration-none d-inline-flex align-items-center gap-1" title="დარეკვა">
                                <i data-feather="phone-call" style="width:14px;height:14px;"></i>{{ $order->customer_phone }}
                            </a>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="text-muted small">პირადი ნომერი</div>
                            <div class="fw-bold">{{ $order->personal_number ?? '—' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="text-muted small">ქალაქი</div>
                            <div class="fw-bold">{{ $order->city ?? '—' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-8">
                            <div class="text-muted small">მისამართი</div>
                            <div class="fw-bold">{{ $order->delivery_address ?? '—' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="text-muted small">წყარო</div>
                            <div><span class="badge bg-secondary">{{ $order->order_source }}</span></div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="text-muted small">გადახდის ტიპი</div>
                            <div class="fw-bold">{{ $order->payment_type == 1 ? 'ონლაინ' : ($order->payment_type == 2 ? 'კურიერთან' : '—') }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="text-muted small">Fulfillment</div>
                            <div class="fw-bold">{{ $order->fulfillment_mode ?? '—' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="text-muted small">შექმნის დრო</div>
                            <div class="fw-bold">{{ $order->created_at->format('M d, Y H:i') }}</div>
                        </div>
                        @if($order->notes)
                        <div class="col-12">
                            <div class="text-muted small">შენიშვნა</div>
                            <div>{{ $order->notes }}</div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 mb-3">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-title mb-3">სტატუსი და ქმედებები</h6>
                    <div class="mb-3">
                        <div class="text-muted small mb-1">Order Status</div>
                        <span class="badge bg-{{ $statusColors[$order->status] ?? 'secondary' }} fs-6">{{ ucfirst($order->status) }}</span>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small mb-1">Payment Status</div>
                        <span class="badge bg-{{ $payColors[$order->payment_status] ?? 'secondary' }} fs-6">{{ ucfirst($order->payment_status) }}</span>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small mb-1">Bridge Sync</div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <span class="badge bg-light text-dark fs-6">{{ $order->bridge_sync_status ?? '—' }}</span>
                            @if($order->bridge_order_number)
                                <span class="badge bg-info text-dark">#{{ $order->bridge_order_number }}</span>
                            @endif
                        </div>
                        @if($order->tracking_number)
                            <div class="small text-muted mt-2">
                                Tracking: {{ $order->tracking_number }}{{ $order->tracking_carrier ? ' • ' . $order->tracking_carrier : '' }}
                            </div>
                        @endif
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small mb-1">ჯამი</div>
                        <div class="fw-bold fs-4 text-primary">{{ $order->currency }} {{ number_format($order->total_amount, 2) }}</div>
                    </div>

                    <hr>

                    @if($order->status !== 'cancelled')
                    <div class="mb-3">
                        <label class="form-label small text-muted">Order Status Update</label>
                        <form method="POST" action="{{ route('admin.orders.update-status', $order) }}" class="d-flex gap-1 flex-wrap">
                            @csrf
                            @method('PATCH')
                            @foreach(['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'] as $status)
                                @if($status !== $order->status)
                                <button type="submit" name="status" value="{{ $status }}"
                                        class="btn btn-sm btn-outline-{{ $statusColors[$status] ?? 'secondary' }}"
                                        @if($status === 'cancelled') onclick="return confirm('გავაუქმოთ ეს შეკვეთა? Local stock აღდგება.')" @endif>
                                    {{ ucfirst($status) }}
                                </button>
                                @endif
                            @endforeach
                        </form>
                    </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label small text-muted">Payment Status Update</label>
                        <form method="POST" action="{{ route('admin.orders.update-payment-status', $order) }}" class="d-flex gap-1 flex-wrap">
                            @csrf
                            @method('PATCH')
                            @foreach(['pending', 'completed', 'rejected'] as $paymentStatusOption)
                                @if($paymentStatusOption !== $order->payment_status)
                                <button type="submit" name="payment_status" value="{{ $paymentStatusOption }}"
                                        class="btn btn-sm btn-outline-{{ $payColors[$paymentStatusOption] ?? 'secondary' }}">
                                    {{ ucfirst($paymentStatusOption) }}
                                </button>
                                @endif
                            @endforeach
                        </form>
                    </div>

                    @if($order->requiresBridgePush())
                    <hr>
                    <div class="d-flex gap-2 flex-wrap">
                        <form method="POST" action="{{ route('admin.orders.bridge.push', $order) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-primary" {{ $order->bridge_order_id ? 'disabled' : '' }}>
                                Push to Bridge
                            </button>
                        </form>
                        @if($order->bridge_order_id)
                        <form method="POST" action="{{ route('admin.orders.bridge.refresh', $order) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                Refresh Bridge Status
                            </button>
                        </form>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <h6 class="card-title mb-3">ნივთები</h6>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>პროდუქტი</th>
                            <th>ვარიანტი</th>
                            <th>რაოდ.</th>
                            <th>Routing</th>
                            <th>Gift</th>
                            <th>ერთეულის ფასი</th>
                            <th>ქვეჯამი</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $item)
                        @php
                            $modelName = trim((string) ($item->variant?->product?->model ?? ''));
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-bold">{{ $modelName !== '' ? $modelName : $item->product_name }}</div>
                                @if($modelName !== '' && $item->product_name !== $modelName)
                                    <div class="small text-muted text-truncate" style="max-width: 420px;" title="{{ $item->product_name }}">{{ $item->product_name }}</div>
                                @endif
                            </td>
                            <td>{{ $item->variant_name }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td><span class="badge bg-light text-dark">{{ $item->fulfillment_mode ?? '—' }}</span></td>
                            <td>
                                @if($item->gift_group_id)
                                    <span class="badge bg-primary-subtle text-primary">{{ $item->gift_role ?: 'gift' }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>GEL {{ number_format($item->unit_price, 2) }}</td>
                            <td class="fw-bold">GEL {{ number_format($item->subtotal, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        @foreach($order->adjustments as $adjustment)
                        <tr>
                            <td colspan="6" class="text-end">{{ $adjustment->title }}</td>
                            <td class="fw-bold {{ (float) $adjustment->amount < 0 ? 'text-success' : 'text-primary' }}">
                                {{ $order->currency }} {{ number_format($adjustment->amount, 2) }}
                            </td>
                        </tr>
                        @endforeach
                        <tr>
                            <td colspan="6" class="text-end fw-bold">ჯამი:</td>
                            <td class="fw-bold text-primary">{{ $order->currency }} {{ number_format($order->total_amount, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    @if($order->paymentLogs && $order->paymentLogs->count())
    <div class="card mb-3">
        <div class="card-body">
            <h6 class="card-title mb-3">გადახდის ჩანაწერები</h6>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Amount</th>
                            <th>Provider</th>
                            <th>Transaction ID</th>
                            <th>თარიღი</th>
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
