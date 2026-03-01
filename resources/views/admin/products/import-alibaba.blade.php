@extends('admin.layout')

@section('title', 'Import Alibaba Product')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-0">Import from Alibaba</h3>
            <p class="text-muted mb-0">Paste one Alibaba URL or full page source, then review and confirm.</p>
        </div>
        <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">Back to Products</a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div id="import-message" class="mb-3"></div>
            <form id="alibaba-parse-form" class="row g-3">
                @csrf
                <div class="col-lg-10">
                    <label for="alibaba-url" class="form-label">Alibaba Product URL</label>
                    <input type="url" name="url" id="alibaba-url" class="form-control" placeholder="https://www.alibaba.com/product-detail/..." required>
                    <div class="form-text">If crawler is blocked by captcha/interception, paste full browser Page Source below.</div>
                </div>
                <div class="col-lg-2 d-flex align-items-end">
                    <button type="submit" id="parse-button" class="btn btn-primary w-100">Parse Product</button>
                </div>
                <div class="col-12">
                    <label for="raw-html" class="form-label">Fallback: Full Page Source (optional)</label>
                    <textarea name="raw_html" id="raw-html" rows="6" class="form-control" placeholder="Open Alibaba product in browser after captcha → View Page Source → paste full HTML here"></textarea>
                </div>
            </form>
        </div>
    </div>

    <div id="review-card" class="card d-none">
        <div class="card-body">
            <h5 class="mb-3">Review & Confirm</h5>
            <form id="alibaba-confirm-form">
                @csrf
                <input type="hidden" name="source_url" id="source_url">
                <input type="hidden" name="source_product_id" id="source_product_id">

                <div class="border rounded p-3 mb-4">
                    <h6 class="mb-3">Images (Select to Import)</h6>
                    <div id="images-grid" class="row g-3"></div>
                </div>

                <div class="row">
                    <div class="col-lg-6 mb-3">
                        <label for="name_en" class="form-label">Name (EN)</label>
                        <input type="text" name="name_en" id="name_en" class="form-control" required>
                    </div>
                    <div class="col-lg-6 mb-3">
                        <label for="name_ka" class="form-label">Name (KA)</label>
                        <input type="text" name="name_ka" id="name_ka" class="form-control" required>
                    </div>
                    <div class="col-lg-6 mb-3">
                        <label for="slug" class="form-label">Slug</label>
                        <input type="text" name="slug" id="slug" class="form-control">
                    </div>
                    <div class="col-lg-3 mb-3">
                        <label for="price" class="form-label">Price</label>
                        <input type="number" step="0.01" min="0" name="price" id="price" class="form-control">
                    </div>
                    <div class="col-lg-3 mb-3">
                        <label for="currency" class="form-label">Currency</label>
                        <input type="text" maxlength="3" name="currency" id="currency" class="form-control" value="GEL">
                    </div>

                    <div class="col-lg-6 mb-3">
                        <label for="short_description_en" class="form-label">Short Description (EN)</label>
                        <input type="text" name="short_description_en" id="short_description_en" class="form-control">
                    </div>
                    <div class="col-lg-6 mb-3">
                        <label for="short_description_ka" class="form-label">Short Description (KA)</label>
                        <input type="text" name="short_description_ka" id="short_description_ka" class="form-control">
                    </div>

                    <div class="col-lg-6 mb-3">
                        <label for="description_en" class="form-label">Description (EN)</label>
                        <textarea name="description_en" id="description_en" rows="5" class="form-control"></textarea>
                    </div>
                    <div class="col-lg-6 mb-3">
                        <label for="description_ka" class="form-label">Description (KA)</label>
                        <textarea name="description_ka" id="description_ka" rows="5" class="form-control"></textarea>
                    </div>

                    <div class="col-lg-4 mb-3">
                        <label for="water_resistant" class="form-label">Water Resistance</label>
                        <input type="text" name="water_resistant" id="water_resistant" class="form-control">
                    </div>
                    <div class="col-lg-4 mb-3">
                        <label for="battery_life_hours" class="form-label">Battery Life (hours)</label>
                        <input type="number" min="1" max="1000" name="battery_life_hours" id="battery_life_hours" class="form-control">
                    </div>
                    <div class="col-lg-4 mb-3">
                        <label for="warranty_months" class="form-label">Warranty (months)</label>
                        <input type="number" min="0" max="120" name="warranty_months" id="warranty_months" class="form-control">
                    </div>
                    <div class="col-lg-4 mb-3">
                        <label for="brand" class="form-label">Brand</label>
                        <input type="text" name="brand" id="brand" class="form-control">
                    </div>
                    <div class="col-lg-4 mb-3">
                        <label for="model" class="form-label">Model</label>
                        <input type="text" name="model" id="model" class="form-control">
                    </div>
                    <div class="col-lg-4 mb-3">
                        <label for="memory_size" class="form-label">Memory Size</label>
                        <input type="text" name="memory_size" id="memory_size" class="form-control">
                    </div>
                    <div class="col-lg-3 mb-3">
                        <label for="operating_system" class="form-label">Operating System</label>
                        <input type="text" name="operating_system" id="operating_system" class="form-control">
                    </div>
                    <div class="col-lg-3 mb-3">
                        <label for="screen_size" class="form-label">Screen Size</label>
                        <input type="text" name="screen_size" id="screen_size" class="form-control">
                    </div>
                    <div class="col-lg-3 mb-3">
                        <label for="display_type" class="form-label">Display Type</label>
                        <input type="text" name="display_type" id="display_type" class="form-control">
                    </div>
                    <div class="col-lg-3 mb-3">
                        <label for="screen_resolution" class="form-label">Screen Resolution</label>
                        <input type="text" name="screen_resolution" id="screen_resolution" class="form-control">
                    </div>
                    <div class="col-lg-3 mb-3">
                        <label for="battery_capacity_mah" class="form-label">Battery Capacity (mAh)</label>
                        <input type="number" min="1" max="100000" name="battery_capacity_mah" id="battery_capacity_mah" class="form-control">
                    </div>
                    <div class="col-lg-3 mb-3">
                        <label for="charging_time_hours" class="form-label">Charging Time (hours)</label>
                        <input type="number" min="0" max="999.9" step="0.1" name="charging_time_hours" id="charging_time_hours" class="form-control">
                    </div>
                    <div class="col-lg-3 mb-3">
                        <label for="case_material" class="form-label">Case Material</label>
                        <input type="text" name="case_material" id="case_material" class="form-control">
                    </div>
                    <div class="col-lg-3 mb-3">
                        <label for="band_material" class="form-label">Band Material</label>
                        <input type="text" name="band_material" id="band_material" class="form-control">
                    </div>
                    <div class="col-lg-4 mb-3">
                        <label for="camera" class="form-label">Camera</label>
                        <input type="text" name="camera" id="camera" class="form-control">
                    </div>
                    <div class="col-lg-8 mb-3">
                        <label for="functions" class="form-label">Functions (comma separated)</label>
                        <textarea name="functions" id="functions" rows="2" class="form-control"></textarea>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-sm-6 col-lg-3 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="sim_support" id="sim_support" value="1">
                            <label class="form-check-label" for="sim_support">SIM Support</label>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="gps_features" id="gps_features" value="1">
                            <label class="form-check-label" for="gps_features">GPS Features</label>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" checked>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="featured" id="featured" value="1">
                            <label class="form-check-label" for="featured">Featured</label>
                        </div>
                    </div>
                </div>

                <div class="border rounded p-3 mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="mb-0">Variants</h6>
                        <button type="button" id="add-variant" class="btn btn-outline-primary btn-sm">Add Variant</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th style="width: 140px;">Quantity</th>
                                    <th style="width: 180px;">Low Stock Threshold</th>
                                    <th style="width: 90px;"></th>
                                </tr>
                            </thead>
                            <tbody id="variants-body"></tbody>
                        </table>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" id="confirm-button" class="btn btn-success">Confirm & Create Product</button>
                    <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            if (window.axios && csrfToken) {
                window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
                window.axios.defaults.headers.common['Accept'] = 'application/json';
            }

            const parseForm = document.getElementById('alibaba-parse-form');
            const confirmForm = document.getElementById('alibaba-confirm-form');
            const reviewCard = document.getElementById('review-card');
            const messageEl = document.getElementById('import-message');
            const parseButton = document.getElementById('parse-button');
            const confirmButton = document.getElementById('confirm-button');
            const variantsBody = document.getElementById('variants-body');
            const addVariantButton = document.getElementById('add-variant');
            const imagesGrid = document.getElementById('images-grid');

            const showMessage = (type, text) => {
                messageEl.innerHTML = `<div class="alert alert-${type}" role="alert">${text}</div>`;
            };

            const setButtonLoading = (button, loading, label = 'Loading') => {
                if (!button) {
                    return;
                }

                if (loading) {
                    button.dataset.originalHtml = button.innerHTML;
                    button.disabled = true;
                    button.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>${label}`;
                    return;
                }

                button.disabled = false;
                if (button.dataset.originalHtml) {
                    button.innerHTML = button.dataset.originalHtml;
                }
            };

            const fillField = (id, value) => {
                const input = document.getElementById(id);
                if (!input) {
                    return;
                }
                input.value = value ?? '';
            };

            const fillCheckbox = (id, value) => {
                const input = document.getElementById(id);
                if (!input) {
                    return;
                }
                input.checked = !!value;
            };

            const renderVariants = (variants) => {
                const rows = (variants || []).map((variant, index) => `
                    <tr>
                        <td>
                            <input type="text" class="form-control" name="variants[${index}][name]" value="${(variant.name || '').replace(/"/g, '&quot;')}" required>
                            <input type="hidden" name="variants[${index}][color_name]" value="${(variant.color_name || '').replace(/"/g, '&quot;')}">
                            <input type="hidden" name="variants[${index}][color_hex]" value="${(variant.color_hex || '').replace(/"/g, '&quot;')}">
                        </td>
                        <td>
                            <input type="number" min="0" class="form-control" name="variants[${index}][quantity]" value="${variant.quantity ?? 0}">
                        </td>
                        <td>
                            <input type="number" min="0" class="form-control" name="variants[${index}][low_stock_threshold]" value="${variant.low_stock_threshold ?? 5}">
                        </td>
                        <td>
                            <button type="button" class="btn btn-outline-danger btn-sm remove-variant">Remove</button>
                        </td>
                    </tr>
                `).join('');

                variantsBody.innerHTML = rows || `
                    <tr>
                        <td>
                            <input type="text" class="form-control" name="variants[0][name]" value="Default" required>
                            <input type="hidden" name="variants[0][color_name]" value="">
                            <input type="hidden" name="variants[0][color_hex]" value="">
                        </td>
                        <td><input type="number" min="0" class="form-control" name="variants[0][quantity]" value="0"></td>
                        <td><input type="number" min="0" class="form-control" name="variants[0][low_stock_threshold]" value="5"></td>
                        <td><button type="button" class="btn btn-outline-danger btn-sm remove-variant">Remove</button></td>
                    </tr>
                `;
            };

            const reindexVariantNames = () => {
                Array.from(variantsBody.querySelectorAll('tr')).forEach((row, index) => {
                    const nameInput = row.querySelector('input[name*="[name]"]');
                    const qtyInput = row.querySelector('input[name*="[quantity]"]');
                    const thresholdInput = row.querySelector('input[name*="[low_stock_threshold]"]');
                    const colorNameInput = row.querySelector('input[name*="[color_name]"]');
                    const colorHexInput = row.querySelector('input[name*="[color_hex]"]');

                    if (nameInput) nameInput.name = `variants[${index}][name]`;
                    if (qtyInput) qtyInput.name = `variants[${index}][quantity]`;
                    if (thresholdInput) thresholdInput.name = `variants[${index}][low_stock_threshold]`;
                    if (colorNameInput) colorNameInput.name = `variants[${index}][color_name]`;
                    if (colorHexInput) colorHexInput.name = `variants[${index}][color_hex]`;
                });
            };

            const renderImages = (images) => {
                const html = (images || []).map((url, index) => `
                    <div class="col-sm-6 col-lg-3">
                        <label class="border rounded p-2 d-block h-100">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="selected_images[]" value="${url}" checked>
                                <span class="form-check-label">Import image ${index + 1}</span>
                            </div>
                            <img src="${url}" class="img-fluid rounded" alt="preview-${index + 1}">
                        </label>
                    </div>
                `).join('');

                imagesGrid.innerHTML = html || '<div class="col-12 text-muted">No images found from this URL.</div>';
            };

            parseForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                setButtonLoading(parseButton, true, 'Parsing');
                showMessage('info', 'Parsing Alibaba product and processing with AI...');

                try {
                    const response = await window.axios.post('{{ route('admin.products.import-alibaba.parse') }}', new FormData(parseForm));
                    const data = response.data?.data || {};
                    const product = data.product || {};

                    document.getElementById('source_url').value = data.source_url || document.getElementById('alibaba-url').value;
                    document.getElementById('source_product_id').value = data.source_product_id || '';
                    fillField('name_en', product.name_en);
                    fillField('name_ka', product.name_ka);
                    fillField('slug', product.slug);
                    fillField('short_description_en', product.short_description_en);
                    fillField('short_description_ka', product.short_description_ka);
                    fillField('description_en', product.description_en);
                    fillField('description_ka', product.description_ka);
                    fillField('price', product.price);
                    fillField('currency', product.currency || 'GEL');
                    fillField('water_resistant', product.water_resistant);
                    fillField('battery_life_hours', product.battery_life_hours);
                    fillField('warranty_months', product.warranty_months);
                    fillField('brand', product.brand);
                    fillField('model', product.model);
                    fillField('memory_size', product.memory_size);
                    fillField('operating_system', product.operating_system);
                    fillField('screen_size', product.screen_size);
                    fillField('display_type', product.display_type);
                    fillField('screen_resolution', product.screen_resolution);
                    fillField('battery_capacity_mah', product.battery_capacity_mah);
                    fillField('charging_time_hours', product.charging_time_hours);
                    fillField('case_material', product.case_material);
                    fillField('band_material', product.band_material);
                    fillField('camera', product.camera);
                    fillField('functions', Array.isArray(product.functions) ? product.functions.join(', ') : product.functions);
                    fillCheckbox('sim_support', product.sim_support);
                    fillCheckbox('gps_features', product.gps_features);
                    fillCheckbox('is_active', product.is_active ?? true);
                    fillCheckbox('featured', product.featured ?? false);

                    renderImages(data.images || []);
                    renderVariants(data.variants || []);

                    reviewCard.classList.remove('d-none');
                    showMessage('success', response.data?.message || 'Parsed successfully. Review the data and confirm.');
                } catch (error) {
                    const payload = error?.response?.data || {};
                    if (payload?.captcha_detected) {
                        showMessage('warning', `${payload.message}<br><br><strong>Quick fix:</strong> Open the same URL in browser, complete captcha, right click → View Page Source, copy full HTML and paste into "Fallback: Full Page Source" field, then Parse again.`);
                    } else {
                        const message = payload?.message || 'Could not parse product URL.';
                        showMessage('danger', message);
                    }
                } finally {
                    setButtonLoading(parseButton, false);
                }
            });

            addVariantButton.addEventListener('click', () => {
                const nextIndex = variantsBody.querySelectorAll('tr').length;
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <input type="text" class="form-control" name="variants[${nextIndex}][name]" value="" required>
                        <input type="hidden" name="variants[${nextIndex}][color_name]" value="">
                        <input type="hidden" name="variants[${nextIndex}][color_hex]" value="">
                    </td>
                    <td><input type="number" min="0" class="form-control" name="variants[${nextIndex}][quantity]" value="0"></td>
                    <td><input type="number" min="0" class="form-control" name="variants[${nextIndex}][low_stock_threshold]" value="5"></td>
                    <td><button type="button" class="btn btn-outline-danger btn-sm remove-variant">Remove</button></td>
                `;
                variantsBody.appendChild(row);
            });

            variantsBody.addEventListener('click', (event) => {
                const button = event.target.closest('.remove-variant');
                if (!button) {
                    return;
                }

                button.closest('tr')?.remove();
                reindexVariantNames();
            });

            confirmForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                setButtonLoading(confirmButton, true, 'Creating');
                showMessage('info', 'Creating product...');

                try {
                    const response = await window.axios.post('{{ route('admin.products.import-alibaba.confirm') }}', new FormData(confirmForm));
                    showMessage('success', response.data?.message || 'Product created successfully. Redirecting...');
                    if (response.data?.redirect) {
                        window.location.href = response.data.redirect;
                    }
                } catch (error) {
                    if (error?.response?.status === 409) {
                        const message = error?.response?.data?.message || 'This source product already exists.';
                        showMessage('warning', message);
                        if (error?.response?.data?.redirect) {
                            window.location.href = error.response.data.redirect;
                        }
                        return;
                    }

                    if (error?.response?.status === 422 && error?.response?.data?.errors) {
                        const errors = Object.values(error.response.data.errors).flat();
                        showMessage('danger', `<ul class="mb-0">${errors.map((item) => `<li>${item}</li>`).join('')}</ul>`);
                    } else {
                        const message = error?.response?.data?.message || 'Failed to create product.';
                        showMessage('danger', message);
                    }
                } finally {
                    setButtonLoading(confirmButton, false);
                }
            });
        });
    </script>
@endpush
