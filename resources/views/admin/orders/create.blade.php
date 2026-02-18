@extends('admin.layout')

@section('title', 'Create Order')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-0">Create Order</h3>
            <p class="text-muted">Add a new order manually.</p>
        </div>
        <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary">Back to Orders</a>
    </div>

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.orders.store') }}">
        @csrf

        <!-- Customer Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Customer Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-4 mb-3">
                        <label for="customer_name" class="form-label">Customer Name <span class="text-danger">*</span></label>
                        <input type="text" name="customer_name" id="customer_name" class="form-control @error('customer_name') is-invalid @enderror" value="{{ old('customer_name') }}" required>
                        @error('customer_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-lg-4 mb-3">
                        <label for="customer_phone" class="form-label">Phone <span class="text-danger">*</span></label>
                        <input type="text" name="customer_phone" id="customer_phone" class="form-control @error('customer_phone') is-invalid @enderror" value="{{ old('customer_phone') }}" required>
                        @error('customer_phone')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-lg-4 mb-3">
                        <label for="customer_email" class="form-label">Email</label>
                        <input type="email" name="customer_email" id="customer_email" class="form-control @error('customer_email') is-invalid @enderror" value="{{ old('customer_email') }}">
                        @error('customer_email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-lg-6 mb-3">
                        <label for="delivery_address" class="form-label">Delivery Address <span class="text-danger">*</span></label>
                        <textarea name="delivery_address" id="delivery_address" class="form-control @error('delivery_address') is-invalid @enderror" rows="2" required>{{ old('delivery_address') }}</textarea>
                        @error('delivery_address')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-lg-3 mb-3">
                        <label for="city" class="form-label">City</label>
                        <input type="text" name="city" id="city" class="form-control @error('city') is-invalid @enderror" value="{{ old('city') }}">
                        @error('city')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-lg-3 mb-3">
                        <label for="postal_code" class="form-label">Postal Code</label>
                        <input type="text" name="postal_code" id="postal_code" class="form-control @error('postal_code') is-invalid @enderror" value="{{ old('postal_code') }}">
                        @error('postal_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-lg-6 mb-3">
                        <label for="order_source" class="form-label">Order Source <span class="text-danger">*</span></label>
                        <select name="order_source" id="order_source" class="form-select @error('order_source') is-invalid @enderror" required>
                            <option value="">Select source...</option>
                            <option value="Facebook" @selected(old('order_source') === 'Facebook')>Facebook</option>
                            <option value="Instagram" @selected(old('order_source') === 'Instagram')>Instagram</option>
                            <option value="Direct" @selected(old('order_source') === 'Direct')>Direct</option>
                            <option value="Other" @selected(old('order_source') === 'Other')>Other</option>
                        </select>
                        @error('order_source')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-lg-6 mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea name="notes" id="notes" class="form-control @error('notes') is-invalid @enderror" rows="2">{{ old('notes') }}</textarea>
                        @error('notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Items -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Order Items</h5>
            </div>
            <div class="card-body">
                <div id="order-items">
                    <div class="order-item-row row align-items-end mb-3">
                        <div class="col-lg-5">
                            <label class="form-label">Product Variant <span class="text-danger">*</span></label>
                            <select name="items[0][variant_id]" class="form-select variant-selector" required>
                                <option value="">Select variant...</option>
                                @foreach($products as $product)
                                    @foreach($product->variants as $variant)
                                        <option value="{{ $variant->id }}" data-price="{{ $product->sale_price ?? $product->price }}" data-stock="{{ $variant->quantity }}" data-name="{{ $product->name_en }} - {{ $variant->name }}">
                                            {{ $product->name_en }} - {{ $variant->name }} (Stock: {{ $variant->quantity }})
                                        </option>
                                    @endforeach
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label">Quantity <span class="text-danger">*</span></label>
                            <input type="number" name="items[0][quantity]" class="form-control quantity-input" min="1" value="1" required>
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label">Price</label>
                            <input type="text" class="form-control item-price" readonly>
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label">Subtotal</label>
                            <input type="text" class="form-control item-subtotal" readonly>
                        </div>
                        <div class="col-lg-1">
                            <button type="button" class="btn btn-danger btn-sm remove-item" disabled><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                </div>
                <button type="button" id="add-item" class="btn btn-sm btn-secondary"><i class="bi bi-plus"></i> Add Item</button>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-8"></div>
                    <div class="col-lg-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="fw-semibold">Total Amount:</span>
                            <span class="fw-bold fs-5" id="total-amount">0.00 GEL</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Create Order</button>
            <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
@endsection

@push('scripts')
<script>
let itemIndex = 1;

document.addEventListener('DOMContentLoaded', function() {
    // Add item
    document.getElementById('add-item').addEventListener('click', function() {
        const container = document.getElementById('order-items');
        const newRow = container.querySelector('.order-item-row').cloneNode(true);

        // Update names and reset values
        newRow.querySelectorAll('select, input').forEach(input => {
            const name = input.getAttribute('name');
            if (name) {
                input.setAttribute('name', name.replace(/\[\d+\]/, `[${itemIndex}]`));
            }
            if (input.classList.contains('variant-selector')) {
                input.value = '';
            } else if (input.classList.contains('quantity-input')) {
                input.value = 1;
            } else {
                input.value = '';
            }
        });

        newRow.querySelector('.remove-item').disabled = false;
        container.appendChild(newRow);
        itemIndex++;

        attachEventListeners();
    });

    // Attach event listeners
    attachEventListeners();
});

function attachEventListeners() {
    // Remove item
    document.querySelectorAll('.remove-item').forEach(btn => {
        btn.onclick = function() {
            if (document.querySelectorAll('.order-item-row').length > 1) {
                this.closest('.order-item-row').remove();
                calculateTotal();
            }
        };
    });

    // Variant change
    document.querySelectorAll('.variant-selector').forEach(select => {
        select.onchange = function() {
            const row = this.closest('.order-item-row');
            const option = this.options[this.selectedIndex];
            const price = option.dataset.price || 0;
            const quantity = row.querySelector('.quantity-input').value || 1;

            row.querySelector('.item-price').value = parseFloat(price).toFixed(2);
            row.querySelector('.item-subtotal').value = (price * quantity).toFixed(2);

            calculateTotal();
        };
    });

    // Quantity change
    document.querySelectorAll('.quantity-input').forEach(input => {
        input.oninput = function() {
            const row = this.closest('.order-item-row');
            const select = row.querySelector('.variant-selector');
            const option = select.options[select.selectedIndex];
            const price = option.dataset.price || 0;
            const stock = option.dataset.stock || 0;
            const quantity = this.value || 1;

            if (parseInt(quantity) > parseInt(stock)) {
                alert(`Only ${stock} items available in stock!`);
                this.value = stock;
                return;
            }

            row.querySelector('.item-subtotal').value = (price * quantity).toFixed(2);
            calculateTotal();
        };
    });
}

function calculateTotal() {
    let total = 0;
    document.querySelectorAll('.item-subtotal').forEach(input => {
        total += parseFloat(input.value || 0);
    });
    document.getElementById('total-amount').textContent = total.toFixed(2) + ' GEL';
}
</script>
@endpush
