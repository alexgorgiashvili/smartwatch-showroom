<!-- Variants Management Section -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Stock Variants</h5>
    </div>
    <div class="card-body">
        <p class="text-muted mb-3">Add and manage different variants of this product (e.g., size, color combinations) with individual stock levels.</p>

        @if($product->variants->count())
            <div class="table-responsive mb-4">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Variant Name</th>
                            <th>Quantity</th>
                            <th>Low Stock Threshold</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($product->variants as $variant)
                            <tr>
                                <td class="fw-semibold">{{ $variant->name }}</td>
                                <td>
                                    <span class="badge @if($variant->isOutOfStock()) bg-danger @elseif($variant->isLowStock()) bg-warning @else bg-success @endif">
                                        {{ $variant->quantity }}
                                    </span>
                                </td>
                                <td>{{ $variant->low_stock_threshold }}</td>
                                <td>
                                    @if($variant->isOutOfStock())
                                        <span class="badge bg-danger">Out of Stock</span>
                                    @elseif($variant->isLowStock())
                                        <span class="badge bg-warning">Low Stock</span>
                                    @else
                                        <span class="badge bg-success">In Stock</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#adjustStockModal{{ $variant->id }}" title="Adjust Stock">
                                        <i class="bi bi-graph-up"></i> Adjust
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary edit-variant-btn" data-variant-id="{{ $variant->id }}" data-name="{{ $variant->name }}" data-quantity="{{ $variant->quantity }}" data-threshold="{{ $variant->low_stock_threshold }}" title="Edit Variant">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger delete-variant-btn" data-variant-id="{{ $variant->id }}" title="Delete Variant">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>

                            <!-- Adjust Stock Modal -->
                            <div class="modal fade" id="adjustStockModal{{ $variant->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Adjust Stock: {{ $variant->name }}</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form class="adjust-stock-form" data-variant-id="{{ $variant->id }}">
                                            @csrf
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Current Quantity</label>
                                                    <input type="text" class="form-control" value="{{ $variant->quantity }}" disabled>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="quantity{{ $variant->id }}" class="form-label">Quantity Change</label>
                                                    <input type="number" name="quantity_change" id="quantity{{ $variant->id }}" class="form-control" placeholder="Positive (add) or negative (reduce)" required>
                                                    <small class="text-muted">Enter positive number to add stock, negative to reduce.</small>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="reason{{ $variant->id }}" class="form-label">Reason</label>
                                                    <select name="reason" id="reason{{ $variant->id }}" class="form-select" required>
                                                        <option value="">Select reason...</option>
                                                        <option value="Facebook Sale">Facebook Sale</option>
                                                        <option value="Instagram Sale">Instagram Sale</option>
                                                        <option value="Direct Sale">Direct Sale</option>
                                                        <option value="Return">Return</option>
                                                        <option value="Damage">Damage</option>
                                                        <option value="Manual Adjustment">Manual Adjustment</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="notes{{ $variant->id }}" class="form-label">Notes</label>
                                                    <textarea name="notes" id="notes{{ $variant->id }}" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">
                                                    <span class="spinner-border spinner-border-sm d-none me-2" role="status" aria-hidden="true"></span>
                                                    Adjust Stock
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted text-center py-4">No variants added yet. Create your first variant below.</p>
        @endif

        <!-- Add/Edit Variant Form -->
        <form id="variantForm" class="mt-4 pt-4 border-top">
            @csrf
            <input type="hidden" name="variant_id" id="variantId">
            <h6 class="mb-3">Add New Variant</h6>
            <div class="row">
                <div class="col-lg-5 mb-3">
                    <label for="variantName" class="form-label">Variant Name</label>
                    <input type="text" name="name" id="variantName" class="form-control" placeholder="e.g., Size: S, Color: Gold" required>
                </div>
                <div class="col-lg-3 mb-3">
                    <label for="variantQuantity" class="form-label">Quantity</label>
                    <input type="number" name="quantity" id="variantQuantity" class="form-control" min="0" value="0" required>
                </div>
                <div class="col-lg-3 mb-3">
                    <label for="lowStockThreshold" class="form-label">Low Stock Alert</label>
                    <input type="number" name="low_stock_threshold" id="lowStockThreshold" class="form-control" min="0" value="5" required>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <span class="spinner-border spinner-border-sm d-none me-2" role="status" aria-hidden="true"></span>
                        Add Variant
                    </button>
                    <button type="button" class="btn btn-secondary d-none" id="cancelEditBtn">Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const productId = {{ $product->id }};

    // Add/Edit Variant Form
    document.getElementById('variantForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const variantId = document.getElementById('variantId').value;
        const url = variantId
            ? `{{ route('admin.products.variants.update', ['variant' => ':id']) }}`.replace(':id', variantId)
            : `{{ route('admin.products.variants.store', $product) }}`;
        const method = variantId ? 'PATCH' : 'POST';

        const data = new FormData(this);
        const btn = this.querySelector('button[type="submit"]');
        const spinner = btn.querySelector('.spinner-border');

        spinner.classList.remove('d-none');
        btn.disabled = true;

        fetch(url, {
            method: method,
            headers: {
                'X-HTTP-Method-Override': method,
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: data
        })
        .then(res => res.json())
        .then(json => {
            if (json.success) {
                window.location.reload();
            } else {
                alert('Error: ' + (json.message || 'Unknown error'));
            }
        })
        .catch(err => {
            alert('Error: ' + err.message);
        })
        .finally(() => {
            spinner.classList.add('d-none');
            btn.disabled = false;
        });
    });

    // Edit Variant
    document.querySelectorAll('.edit-variant-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('variantId').value = this.dataset.variantId;
            document.getElementById('variantName').value = this.dataset.name;
            document.getElementById('variantQuantity').value = this.dataset.quantity;
            document.getElementById('lowStockThreshold').value = this.dataset.threshold;
            document.querySelector('#variantForm button[type="submit"]').textContent = 'Update Variant';
            document.getElementById('cancelEditBtn').classList.remove('d-none');
        });
    });

    // Cancel Edit
    document.getElementById('cancelEditBtn')?.addEventListener('click', function() {
        document.getElementById('variantForm').reset();
        document.getElementById('variantId').value = '';
        document.querySelector('#variantForm button[type="submit"]').textContent = 'Add Variant';
        this.classList.add('d-none');
    });

    // Delete Variant
    document.querySelectorAll('.delete-variant-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm('Are you sure? This will delete the variant and its stock history.')) {
                const url = `{{ route('admin.products.variants.delete', ['variant' => ':id']) }}`.replace(':id', this.dataset.variantId);
                fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    }
                })
                .then(res => res.json())
                .then(json => {
                    if (json.success) {
                        window.location.reload();
                    }
                })
                .catch(err => alert('Error: ' + err.message));
            }
        });
    });

    // Adjust Stock
    document.querySelectorAll('.adjust-stock-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const variantId = this.dataset.variantId;
            const url = `{{ route('admin.variants.adjust-stock', ['variant' => ':id']) }}`.replace(':id', variantId);
            const btn = this.querySelector('button[type="submit"]');
            const spinner = btn.querySelector('.spinner-border');

            spinner.classList.remove('d-none');
            btn.disabled = true;

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    quantity_change: parseInt(this.querySelector('[name="quantity_change"]').value),
                    reason: this.querySelector('[name="reason"]').value,
                    notes: this.querySelector('[name="notes"]').value,
                })
            })
            .then(res => res.json())
            .then(json => {
                if (json.success) {
                    showToast('success', json.message);
                    window.location.reload();
                } else {
                    showToast('danger', json.message || 'Error adjusting stock');
                }
            })
            .catch(err => {
                showToast('danger', 'Error: ' + err.message);
            })
            .finally(() => {
                spinner.classList.add('d-none');
                btn.disabled = false;
            });
        });
    });

    // Toast helper
    function showToast(type, message) {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} alert-dismissible fade show`;
        toast.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        document.getElementById('async-toast').appendChild(toast);
        setTimeout(() => toast.remove(), 5000);
    }
});
</script>
