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
                        <label for="personal_number" class="form-label">Personal Number <span class="text-danger">*</span></label>
                        <input type="text" name="personal_number" id="personal_number" maxlength="11" pattern="[0-9]{11}" class="form-control @error('personal_number') is-invalid @enderror" value="{{ old('personal_number') }}" required>
                        @error('personal_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-lg-6 mb-3">
                        <label for="exact_address" class="form-label">Exact Address <span class="text-danger">*</span></label>
                        <textarea name="exact_address" id="exact_address" class="form-control @error('exact_address') is-invalid @enderror" rows="2" required>{{ old('exact_address') }}</textarea>
                        @error('exact_address')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-lg-3 mb-3">
                        <label for="city_id" class="form-label">City <span class="text-danger">*</span></label>
                        <div class="position-relative" id="admin-city-picker">
                            <input type="hidden" name="city_id" id="city_id" value="{{ old('city_id') }}" required>
                            <input type="text" id="city_search" class="form-control @error('city_id') is-invalid @enderror" placeholder="Search city..." autocomplete="off">
                            <div id="admin-city-results" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index: 20; max-height: 220px; overflow: auto;"></div>
                        </div>
                        @error('city_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-lg-3 mb-3">
                        <label class="form-label">&nbsp;</label>
                        <input type="text" class="form-control" value="Auto from selected city" disabled>
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
const adminCities = @json($cities->map(fn ($city) => ['id' => $city->id, 'name' => $city->name])->values());

document.addEventListener('DOMContentLoaded', function() {
    initCitySearch();

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

function initCitySearch() {
    const cityInput = document.getElementById('city_search');
    const cityId = document.getElementById('city_id');
    const cityResults = document.getElementById('admin-city-results');
    const oldCityId = cityId.value;

    if (!cityInput || !cityId || !cityResults) {
        return;
    }

    if (oldCityId) {
        const oldCity = adminCities.find(city => String(city.id) === String(oldCityId));
        if (oldCity) {
            cityInput.value = oldCity.name;
        }
    }

    cityInput.addEventListener('input', function () {
        const query = cityInput.value.trim().toLowerCase();
        cityId.value = '';
        cityInput.setCustomValidity('');

        if (!query) {
            cityResults.classList.add('d-none');
            cityResults.innerHTML = '';
            return;
        }

        const matches = adminCities
            .filter(city => city.name.toLowerCase().includes(query))
            .slice(0, 40);

        if (!matches.length) {
            cityResults.innerHTML = '<div class="list-group-item text-muted">City not found</div>';
            cityResults.classList.remove('d-none');
            return;
        }

        cityResults.innerHTML = matches
            .map(city => `<button type="button" class="list-group-item list-group-item-action" data-city-id="${city.id}" data-city-name="${city.name}">${city.name}</button>`)
            .join('');

        cityResults.classList.remove('d-none');
    });

    cityResults.addEventListener('click', function (event) {
        const button = event.target.closest('[data-city-id]');
        if (!button) return;

        cityId.value = button.getAttribute('data-city-id') || '';
        cityInput.value = button.getAttribute('data-city-name') || '';
        cityInput.setCustomValidity('');
        cityResults.classList.add('d-none');
    });

    document.addEventListener('click', function (event) {
        if (!event.target.closest('#admin-city-picker')) {
            cityResults.classList.add('d-none');
        }
    });

    const form = cityInput.closest('form');
    form?.addEventListener('submit', function (event) {
        if (!cityId.value) {
            event.preventDefault();
            cityInput.setCustomValidity('Select city from list');
            cityInput.reportValidity();
        }
    });
}

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
