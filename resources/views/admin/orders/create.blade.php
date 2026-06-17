@extends('admin.layout')

@section('title', 'Create Order — Admin')

@section('content')
@fragment('content')
<div data-page-title="შეკვეთის შექმნა">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div>
            <h4 class="mb-3 mb-md-0">შეკვეთის შექმნა</h4>
        </div>
        <div>
            <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary btn-sm" data-pjax>
                <i data-feather="arrow-left" style="width:14px;height:14px;"></i> უკან
            </a>
        </div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form id="orderForm" method="POST" action="{{ route('admin.orders.store') }}">
        @csrf

        <div class="row">
            {{-- ── Customer Info ── --}}
            <div class="col-xl-6 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="card-title mb-3">Customer Information</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="customer_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('customer_name') is-invalid @enderror"
                                       id="customer_name" name="customer_name" value="{{ old('customer_name') }}" required>
                                @error('customer_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="customer_phone" class="form-label">Phone <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('customer_phone') is-invalid @enderror"
                                       id="customer_phone" name="customer_phone" value="{{ old('customer_phone') }}" placeholder="5XXXXXXXX" required>
                                @error('customer_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="personal_number" class="form-label">Personal Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('personal_number') is-invalid @enderror"
                                       id="personal_number" name="personal_number" value="{{ old('personal_number') }}" placeholder="11 digits" required>
                                @error('personal_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="order_source" class="form-label">Order Source <span class="text-danger">*</span></label>
                                <select class="form-select @error('order_source') is-invalid @enderror" id="order_source" name="order_source" required>
                                    <option value="">Select...</option>
                                    @foreach(['Facebook', 'Instagram', 'Direct', 'Other'] as $src)
                                    <option value="{{ $src }}" {{ old('order_source') === $src ? 'selected' : '' }}>{{ $src }}</option>
                                    @endforeach
                                </select>
                                @error('order_source') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Delivery Info ── --}}
            <div class="col-xl-6 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="card-title mb-3">Delivery & Payment</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="city_id" class="form-label">City <span class="text-danger">*</span></label>
                                <select class="form-select @error('city_id') is-invalid @enderror" id="city_id" name="city_id" required>
                                    <option value="">Select city...</option>
                                    @foreach($cities as $city)
                                    <option value="{{ $city->id }}" {{ old('city_id') == $city->id ? 'selected' : '' }}>{{ $city->name }}</option>
                                    @endforeach
                                </select>
                                @error('city_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="payment_type" class="form-label">Payment Type <span class="text-danger">*</span></label>
                                <select class="form-select @error('payment_type') is-invalid @enderror" id="payment_type" name="payment_type" required>
                                    <option value="">Select...</option>
                                    <option value="1" {{ old('payment_type') == '1' ? 'selected' : '' }}>Online Payment</option>
                                    <option value="2" {{ old('payment_type') == '2' ? 'selected' : '' }}>Courier (Cash on Delivery)</option>
                                </select>
                                @error('payment_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12">
                                <label for="exact_address" class="form-label">Exact Address <span class="text-danger">*</span></label>
                                <textarea class="form-control @error('exact_address') is-invalid @enderror"
                                          id="exact_address" name="exact_address" rows="2" required>{{ old('exact_address') }}</textarea>
                                @error('exact_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Order Items ── --}}
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="card-title mb-0">Order Items</h6>
                    <button type="button" class="btn btn-primary btn-sm" id="btnAddItem">
                        <i data-feather="plus" style="width:14px;height:14px;"></i> Add Item
                    </button>
                </div>

                <div id="orderItemsContainer">
                    <div class="order-item-row row g-2 mb-2 align-items-end" data-index="0">
                        <div class="col-md-5">
                            <label class="form-label small">Product & Variant <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm variant-select" name="items[0][variant_id]" required>
                                <option value="">Select product variant...</option>
                                @foreach($products as $product)
                                    @foreach($product->variants as $variant)
                                    <option value="{{ $variant->id }}" data-price="{{ $product->sale_price ?? $product->price }}" data-stock="{{ $variant->quantity }}">
                                        {{ $product->name_en }} — {{ $variant->name }} (Stock: {{ $variant->quantity }})
                                    </option>
                                    @endforeach
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Qty <span class="text-danger">*</span></label>
                            <input type="number" class="form-control form-control-sm item-qty" name="items[0][quantity]" min="1" value="1" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Price</label>
                            <div class="item-price fw-bold text-muted">—</div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Subtotal</label>
                            <div class="item-subtotal fw-bold text-primary">—</div>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-outline-danger btn-sm p-1 btn-remove-item" title="Remove" style="visibility:hidden;">
                                <i data-feather="x" style="width:14px;height:14px;"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <hr>
                <div class="d-flex justify-content-end">
                    <div>
                        <span class="text-muted">Order Total: </span>
                        <span class="fw-bold fs-5 text-primary" id="orderTotal">GEL 0.00</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <button type="submit" class="btn btn-primary">
                <i data-feather="check" style="width:16px;height:16px;"></i> Create Order
            </button>
        </div>
    </form>
</div>

{{-- Product data for JS --}}
@php
    $orderProductsData = $products->flatMap(fn ($p) => $p->variants->map(fn ($v) => [
        'id' => $v->id,
        'label' => $p->name_en . ' — ' . $v->name . ' (Stock: ' . $v->quantity . ')',
        'price' => (float) ($p->sale_price ?? $p->price),
        'stock' => $v->quantity,
    ]))->values();
@endphp
<script id="order-products-data" type="application/json">{!! json_encode($orderProductsData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endfragment
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    window.AdminOrders && window.AdminOrders.initCreate();
});
</script>
@endpush
