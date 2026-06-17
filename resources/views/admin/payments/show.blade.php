@extends('admin.layout')

@section('title', 'Payment Log #' . $paymentLog->id . ' — Admin')

@section('content')
@fragment('content')
<div data-page-title="Payment Log დეტალები">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div>
            <h4 class="mb-3 mb-md-0">გადახდის ჩანაწერი #{{ $paymentLog->id }}</h4>
            <p class="text-muted small mb-0">{{ $paymentLog->created_at->format('M d, Y H:i') }}</p>
        </div>
        <div>
            <a href="{{ route('admin.payments.index') }}" class="btn btn-outline-secondary btn-sm" data-pjax>
                <i data-feather="arrow-left" style="width:14px;height:14px;"></i> უკან
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Payment Information -->
        <div class="col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <h6 class="mb-0">გადახდის ინფორმაცია</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted small" style="width:150px;">BOG Order ID:</td>
                                <td class="font-monospace">{{ $paymentLog->bog_order_id ?: '—' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted small">გარე შეკვეთის ID:</td>
                                <td class="font-monospace">{{ $paymentLog->external_order_id ?: '—' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted small">სტატუსი:</td>
                                <td>
                                    @if($paymentLog->status === 'SUCCESS')
                                        <span class="badge bg-success">SUCCESS</span>
                                    @elseif($paymentLog->status === 'TIMEOUT')
                                        <span class="badge bg-warning">TIMEOUT</span>
                                    @elseif($paymentLog->status === 'CREATED')
                                        <span class="badge bg-info">CREATED</span>
                                    @else
                                        <span class="badge bg-secondary">{{ $paymentLog->status ?: 'N/A' }}</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted small">შიდა სტატუსი:</td>
                                <td>
                                    @if($paymentLog->chveni_statusi === 'completed')
                                        <span class="badge bg-success">დასრულებული</span>
                                    @elseif($paymentLog->chveni_statusi === 'pending')
                                        <span class="badge bg-warning">მოლოდინში</span>
                                    @elseif($paymentLog->chveni_statusi === 'rejected')
                                        <span class="badge bg-danger">უარყოფილი</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted small">თარიღი:</td>
                                <td>{{ $paymentLog->created_at->format('M d, Y H:i:s') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Order Information -->
        <div class="col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <h6 class="mb-0">შეკვეთის ინფორმაცია</h6>
                </div>
                <div class="card-body">
                    @if($paymentLog->order)
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                <tr>
                                    <td class="text-muted small" style="width:150px;">შეკვეთის №:</td>
                                    <td class="font-monospace fw-medium">{{ $paymentLog->order->order_number }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted small">მომხმარებელი:</td>
                                    <td>{{ $paymentLog->order->customer_name }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted small">ტელეფონი:</td>
                                    <td>{{ $paymentLog->order->customer_phone }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted small">თანხა:</td>
                                    <td class="fw-bold">{{ number_format($paymentLog->order->total_amount, 2) }} {{ $paymentLog->order->currency }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted small">გადახდის სტატუსი:</td>
                                    <td>
                                        @if($paymentLog->order->payment_status === 'completed')
                                            <span class="badge bg-success">Completed</span>
                                        @elseif($paymentLog->order->payment_status === 'pending')
                                            <span class="badge bg-warning">Pending</span>
                                        @elseif($paymentLog->order->payment_status === 'rejected')
                                            <span class="badge bg-danger">Rejected</span>
                                        @else
                                            <span class="badge bg-secondary">{{ $paymentLog->order->payment_status }}</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted small">გადახდის მეთოდი:</td>
                                    <td>{{ $paymentLog->order->payment_type ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted small">წყარო:</td>
                                    <td>{{ $paymentLog->order->order_source ?: '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="mt-3">
                            <a href="{{ route('admin.orders.show', $paymentLog->order) }}" class="btn btn-sm btn-outline-primary" data-pjax>
                                <i data-feather="external-link" style="width:14px;height:14px;"></i> შეკვეთის ნახვა
                            </a>
                        </div>
                    @else
                        <div class="text-center text-muted py-4">
                            <i data-feather="shopping-cart" style="width:48px;height:48px;"></i>
                            <p class="mt-2 mb-0">შეკვეთა არ არის დაკავშირებული</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Detail JSON -->
    @if($paymentLog->payment_detail)
    <div class="card">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h6 class="mb-0">გადახდის დეტალები (JSON)</h6>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="copyJsonBtn">
                <i data-feather="copy" style="width:14px;height:14px;"></i> კოპირება
            </button>
        </div>
        <div class="card-body">
            <pre class="bg-dark text-light p-3 rounded" style="max-height: 500px; overflow-y: auto;"><code id="jsonContent">{{ json_encode($paymentLog->payment_detail, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</code></pre>
        </div>
    </div>
    @endif
</div>

@if($paymentLog->payment_detail)
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const copyBtn = document.getElementById('copyJsonBtn');
    const jsonContent = document.getElementById('jsonContent');
    
    if (copyBtn && jsonContent) {
        copyBtn.addEventListener('click', function () {
            navigator.clipboard.writeText(jsonContent.textContent).then(() => {
                const originalHtml = copyBtn.innerHTML;
                copyBtn.innerHTML = '<i data-feather="check" style="width:14px;height:14px;"></i> დაკოპირდა!';
                feather.replace();
                setTimeout(() => {
                    copyBtn.innerHTML = originalHtml;
                    feather.replace();
                }, 2000);
            }).catch(err => {
                console.error('Copy failed:', err);
            });
        });
    }
});
</script>
@endpush
@endif
@endfragment
@endsection
