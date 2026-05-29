@extends('admin.layout')

@section('title', 'გადახდების ლოგები — Admin')

@section('content')
@fragment('content')
<div data-page-title="გადახდების ლოგები">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div>
            <h4 class="mb-3 mb-md-0">გადახდების ლოგები (Payment Logs)</h4>
            <p class="text-muted small mb-0">სულ: {{ number_format($totalCount) }} | დღეს: {{ number_format($todayCount) }}</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.payments.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small">ძებნა</label>
                    <input type="text" name="search" class="form-control form-control-sm" value="{{ $filters['search'] }}" placeholder="შეკვეთის №, BOG ID, External ID...">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">სტატუსი</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">ყველა</option>
                        <option value="SUCCESS" {{ $filters['status'] === 'SUCCESS' ? 'selected' : '' }}>SUCCESS</option>
                        <option value="TIMEOUT" {{ $filters['status'] === 'TIMEOUT' ? 'selected' : '' }}>TIMEOUT</option>
                        <option value="CREATED" {{ $filters['status'] === 'CREATED' ? 'selected' : '' }}>CREATED</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">შიდა სტატუსი</label>
                    <select name="chveni_statusi" class="form-select form-select-sm">
                        <option value="">ყველა</option>
                        <option value="completed" {{ $filters['chveni_statusi'] === 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="pending" {{ $filters['chveni_statusi'] === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="rejected" {{ $filters['chveni_statusi'] === 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">თარიღი (დან)</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $filters['date_from'] }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">თარიღი (მდე)</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $filters['date_to'] }}">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm w-100">ძებნა</button>
                </div>
            </form>
            <div class="mt-2">
                <a href="{{ route('admin.payments.index') }}" class="btn btn-outline-secondary btn-sm">გასუფთავება</a>
            </div>
        </div>
    </div>

    <!-- Payment Logs Table -->
    <div class="card">
        <div class="card-body">
            @if($paymentLogs->isEmpty())
                <div class="text-center text-muted py-5">
                    <i data-feather="credit-card" style="width:48px;height:48px;"></i>
                    <p class="mt-2">გადახდების ლოგები არ მოიძებნა</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="small">შეკვეთის №</th>
                                <th class="small">BOG Order ID</th>
                                <th class="small">External ID</th>
                                <th class="small">სტატუსი</th>
                                <th class="small">შიდა სტატუსი</th>
                                <th class="small">თანხა</th>
                                <th class="small">თარიღი</th>
                                <th class="small" style="width:80px;">მოქმედება</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($paymentLogs as $log)
                            <tr>
                                <td class="small font-monospace">
                                    @if($log->order)
                                        {{ $log->order->order_number }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="small font-monospace">{{ $log->bog_order_id ?: '—' }}</td>
                                <td class="small font-monospace">{{ $log->external_order_id ?: '—' }}</td>
                                <td>
                                    @if($log->status === 'SUCCESS')
                                        <span class="badge bg-success">SUCCESS</span>
                                    @elseif($log->status === 'TIMEOUT')
                                        <span class="badge bg-warning">TIMEOUT</span>
                                    @elseif($log->status === 'CREATED')
                                        <span class="badge bg-info">CREATED</span>
                                    @else
                                        <span class="badge bg-secondary">{{ $log->status ?: 'N/A' }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($log->chveni_statusi === 'completed')
                                        <span class="badge bg-success-subtle text-success border">Completed</span>
                                    @elseif($log->chveni_statusi === 'pending')
                                        <span class="badge bg-warning-subtle text-warning border">Pending</span>
                                    @elseif($log->chveni_statusi === 'rejected')
                                        <span class="badge bg-danger-subtle text-danger border">Rejected</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="small">
                                    @if($log->order)
                                        <span class="fw-medium">{{ number_format($log->order->total_amount, 2) }} {{ $log->order->currency }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="small text-nowrap">{{ $log->created_at->format('M d, Y H:i') }}</td>
                                <td>
                                    <a href="{{ route('admin.payments.show', $log) }}" class="btn btn-sm btn-outline-primary" data-pjax>
                                        <i data-feather="eye" style="width:14px;height:14px;"></i>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($paymentLogs->hasPages())
                <div class="mt-3">
                    {{ $paymentLogs->appends($filters)->links() }}
                </div>
                @endif
            @endif
        </div>
    </div>
</div>
@endfragment
@endsection
