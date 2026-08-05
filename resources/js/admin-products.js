/**
 * Admin Products - DataTable, variant CRUD, image management, delete confirmations
 */

let currentImages = [];
let currentIndex = 0;

function openLightbox(images, startIndex) {
    currentImages = images;
    currentIndex = startIndex;
    const lightbox = document.getElementById('global-lightbox');

    if (!lightbox) return;

    updateLightboxView();
    lightbox.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    const lightbox = document.getElementById('global-lightbox');
    if (lightbox) {
        lightbox.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function updateLightboxView() {
    const mainImg = document.getElementById('lightbox-main-img');
    const thumbContainer = document.getElementById('lightbox-thumbnails');

    if (!mainImg || !currentImages.length) return;

    mainImg.src = currentImages[currentIndex].url;
    thumbContainer.innerHTML = '';

    currentImages.forEach((img, idx) => {
        const thumb = document.createElement('img');
        thumb.src = img.thumbnail_url || img.url;
        thumb.className = `lightbox-thumb ${idx === currentIndex ? 'active' : ''}`;
        thumb.addEventListener('click', () => {
            currentIndex = idx;
            updateLightboxView();
        });
        thumbContainer.appendChild(thumb);
    });
}

function nextLightboxImage() {
    if (currentImages.length === 0) return;
    currentIndex = (currentIndex + 1) % currentImages.length;
    updateLightboxView();
}

function prevLightboxImage() {
    if (currentImages.length === 0) return;
    currentIndex = (currentIndex - 1 + currentImages.length) % currentImages.length;
    updateLightboxView();
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('lightbox-close')?.addEventListener('click', closeLightbox);
    document.getElementById('lightbox-next')?.addEventListener('click', nextLightboxImage);
    document.getElementById('lightbox-prev')?.addEventListener('click', prevLightboxImage);

    document.addEventListener('keydown', (e) => {
        const lightbox = document.getElementById('global-lightbox');
        if (lightbox && lightbox.classList.contains('active')) {
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowRight') nextLightboxImage();
            if (e.key === 'ArrowLeft') prevLightboxImage();
        }
    });
});

const AdminProducts = {
    _editBound: false,
    _currentProductData: null,

    initIndex() {
        this._initDataTable();
        this._bindDeleteProduct();
    },

    _initDataTable() {
        const table = document.getElementById('productsTable');
        if (!table || typeof $.fn.DataTable === 'undefined') {
            this._bindGalleryTriggers();
            return;
        }

        if ($.fn.DataTable.isDataTable(table)) {
            this._bindGalleryTriggers();
            return;
        }

        $(table).DataTable({
            pageLength: 25,
            order: [],
            columnDefs: [
                { orderable: false, targets: [0, 7] },
            ],
            language: {
                search: '',
                searchPlaceholder: 'Search products...',
                lengthMenu: '_MENU_ per page',
            },
            dom: '<"d-flex justify-content-between align-items-center mb-3"fl>t<"d-flex justify-content-between align-items-center mt-3"ip>',
            drawCallback: () => {
                if (typeof feather !== 'undefined') feather.replace();
                this._bindGalleryTriggers();
            }
        });

        if (typeof feather !== 'undefined') feather.replace();
    },

    _bindGalleryTriggers() {
        const triggers = document.querySelectorAll('.product-gallery-trigger');
        triggers.forEach((trigger) => {
            const newTrigger = trigger.cloneNode(true);
            if (trigger.parentNode) {
                trigger.parentNode.replaceChild(newTrigger, trigger);
            }

            newTrigger.addEventListener('click', async (e) => {
                e.preventDefault();
                const productId = newTrigger.getAttribute('data-product-id');
                if (!productId) return;

                try {
                    document.body.style.cursor = 'wait';
                    const res = await axios.get(`/admin/products/${productId}/images-json`);
                    if (res.data?.images?.length) {
                        openLightbox(res.data.images, 0);
                    } else if (window.AdminHelpers) {
                        window.AdminHelpers.showToast('No images available for this product', 'info');
                    }
                } catch (err) {
                    console.error('Error loading gallery', err);
                    if (window.AdminHelpers) {
                        window.AdminHelpers.showToast('Failed to load images', 'error');
                    }
                } finally {
                    document.body.style.cursor = '';
                }
            });
        });
    },

    _bindDeleteProduct() {
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-delete-product');
            if (!btn) return;

            if (typeof window.AdminHelpers === 'undefined') return;

            window.AdminHelpers.confirmDelete(btn.dataset.url, {
                title: `Delete product "${btn.dataset.name}"?`,
                text: 'This action cannot be undone.',
                onSuccess: (data) => {
                    const targetUrl = data?.redirect || '/admin/products';
                    if (window.AdminRouter) {
                        window.AdminRouter.navigate(targetUrl);
                    } else {
                        window.location.href = targetUrl;
                    }
                },
            });
        });
    },

    initForm() {
        this._bindFormSubmit();
    },

    _bindFormSubmit() {
        const form = document.getElementById('productForm');
        if (!form || form.dataset.asyncBound === '1') return;

        form.dataset.asyncBound = '1';

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const btn = form.querySelector('button[type="submit"]');
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';

            if (typeof window.AdminHelpers !== 'undefined') {
                window.AdminHelpers.clearValidationErrors(form);
            }

            try {
                const formData = new FormData(form);
                const response = await axios.post(form.action, formData, {
                    headers: { Accept: 'application/json' },
                });

                if (response.data.redirect) {
                    if (window.AdminRouter) {
                        window.AdminRouter.navigate(response.data.redirect);
                    } else {
                        window.location.href = response.data.redirect;
                    }
                } else if (typeof window.AdminHelpers !== 'undefined') {
                    window.AdminHelpers.showToast(response.data.message || 'Saved!', 'success');
                }
            } catch (error) {
                if (error.response?.status === 422 && typeof window.AdminHelpers !== 'undefined') {
                    window.AdminHelpers.showValidationErrors(error.response.data.errors, form);
                } else if (typeof window.AdminHelpers !== 'undefined') {
                    window.AdminHelpers.showToast(error.response?.data?.message || 'An error occurred.', 'error');
                }
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                if (typeof feather !== 'undefined') feather.replace();
            }
        });
    },

    initEdit() {
        const productDataEl = document.getElementById('product-data');
        if (!productDataEl) return;

        try {
            this._currentProductData = JSON.parse(productDataEl.textContent);
        } catch {
            this._currentProductData = null;
            return;
        }

        if (this._editBound) return;

        this._editBound = true;
        this._bindVariantActions();
        this._bindImageActions();
    },

    _bindVariantActions() {
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('#btnAddVariant');
            if (!btn || !this._currentProductData?.storeVariantUrl) return;
            this._showVariantModal(null, this._currentProductData.storeVariantUrl);
        });

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-edit-variant');
            if (!btn) return;

            const variant = JSON.parse(btn.dataset.variant);
            this._showVariantModal(variant, `/admin/products/variants/${variant.id}`, 'PATCH');
        });

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-delete-variant');
            if (!btn || typeof window.AdminHelpers === 'undefined') return;

            window.AdminHelpers.confirmDelete(btn.dataset.url, {
                title: `Delete variant "${btn.dataset.name}"?`,
                text: 'This action cannot be undone.',
                onSuccess: () => {
                    btn.closest('tr')?.remove();
                    this._syncVariantsEmptyState();
                },
            });
        });

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-adjust-stock');
            if (!btn) return;
            this._showStockAdjustModal(btn.dataset.variantId, btn.dataset.variantName);
        });

        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('.btn-toggle-listed-variant');
            if (!btn) return;

            const currentlyListed = btn.dataset.isListed === '1';

            try {
                const response = await axios.patch(btn.dataset.url, {
                    is_listed_separately: !currentlyListed,
                }, { headers: { Accept: 'application/json' } });

                if (typeof window.AdminHelpers !== 'undefined') {
                    window.AdminHelpers.showToast(response.data?.message || 'Updated', 'success');
                }

                if (response.data?.variant) {
                    this._upsertVariantRow(response.data.variant);
                }
            } catch (error) {
                if (typeof window.AdminHelpers !== 'undefined') {
                    window.AdminHelpers.showToast(error.response?.data?.message || 'Failed to update listing visibility', 'error');
                }
            }
        });

        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('.btn-map-variant-images');
            if (!btn) return;

            const selectedIds = this._safeJsonParse(btn.dataset.imageIds, []);
            await this._openVariantImagePicker(btn.dataset.syncUrl, selectedIds);
        });
    },

    _showVariantModal(variant, url, method = 'POST') {
        if (typeof Swal === 'undefined') return;

        const isEdit = !!variant;
        Swal.fire({
            title: isEdit ? 'Edit Variant' : 'Add Variant',
            html: `
                <div class="text-start">
                    <div class="mb-2">
                        <label class="form-label small">Name (KA) <span class="text-danger">*</span></label>
                        <input type="text" id="swal-name" class="form-control form-control-sm" value="${variant?.name || ''}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Name (EN) <span class="text-danger">*</span></label>
                        <input type="text" id="swal-name-en" class="form-control form-control-sm" value="${variant?.name_en || ''}" required>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-4">
                            <label class="form-label small">Color (KA)</label>
                            <input type="text" id="swal-color-name" class="form-control form-control-sm" value="${variant?.color_name || ''}">
                        </div>
                        <div class="col-4">
                            <label class="form-label small">Color (EN)</label>
                            <input type="text" id="swal-color-name-en" class="form-control form-control-sm" value="${variant?.color_name_en || ''}">
                        </div>
                        <div class="col-4">
                            <label class="form-label small">Color Hex</label>
                            <input type="color" id="swal-color-hex" class="form-control form-control-sm form-control-color" value="${variant?.color_hex || '#000000'}" style="height:31px;">
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small">Quantity <span class="text-danger">*</span></label>
                            <input type="number" id="swal-qty" class="form-control form-control-sm" min="0" value="${variant?.quantity ?? 0}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Low Stock Threshold <span class="text-danger">*</span></label>
                            <input type="number" id="swal-threshold" class="form-control form-control-sm" min="0" value="${variant?.low_stock_threshold ?? 5}" required>
                        </div>
                    </div>
                    <div class="form-check form-switch mb-1">
                        <input class="form-check-input" type="checkbox" id="swal-listed-separately" ${variant?.is_listed_separately ? 'checked' : ''}>
                        <label class="form-check-label small" for="swal-listed-separately">Show as separate card in catalog</label>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: isEdit ? 'Update' : 'Add',
            preConfirm: async () => {
                const data = {
                    name: document.getElementById('swal-name').value,
                    name_en: document.getElementById('swal-name-en').value,
                    color_name: document.getElementById('swal-color-name').value || null,
                    color_name_en: document.getElementById('swal-color-name-en').value || null,
                    color_hex: document.getElementById('swal-color-name').value ? document.getElementById('swal-color-hex').value : null,
                    quantity: parseInt(document.getElementById('swal-qty').value, 10),
                    low_stock_threshold: parseInt(document.getElementById('swal-threshold').value, 10),
                    is_listed_separately: document.getElementById('swal-listed-separately').checked,
                };

                if (!data.name || !data.name_en) {
                    Swal.showValidationMessage('Both Georgian and English names are required');
                    return false;
                }

                try {
                    const response = method === 'PATCH'
                        ? await axios.patch(url, data, { headers: { Accept: 'application/json' } })
                        : await axios.post(url, data, { headers: { Accept: 'application/json' } });

                    return response.data;
                } catch (error) {
                    const msg = error.response?.data?.message
                        || Object.values(error.response?.data?.errors || {}).flat().join(', ')
                        || 'Error';
                    Swal.showValidationMessage(msg);
                    return false;
                }
            },
        }).then((result) => {
            if (!result.isConfirmed) return;

            if (typeof window.AdminHelpers !== 'undefined') {
                window.AdminHelpers.showToast(result.value.message || 'Saved!', 'success');
            }

            this._upsertVariantRow(result.value.variant);
        });
    },

    _showStockAdjustModal(variantId, variantName) {
        if (typeof Swal === 'undefined') return;

        Swal.fire({
            title: `Adjust Stock: ${variantName}`,
            html: `
                <div class="text-start">
                    <div class="mb-2">
                        <label class="form-label small">Quantity Change <span class="text-danger">*</span></label>
                        <input type="number" id="swal-adj-qty" class="form-control form-control-sm" placeholder="e.g., +5 or -3" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Reason</label>
                        <input type="text" id="swal-adj-reason" class="form-control form-control-sm" placeholder="e.g., Restock, Damaged">
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Adjust',
            preConfirm: async () => {
                const qty = parseInt(document.getElementById('swal-adj-qty').value, 10);
                const reason = document.getElementById('swal-adj-reason').value;

                if (isNaN(qty) || qty === 0) {
                    Swal.showValidationMessage('Enter a non-zero quantity change');
                    return false;
                }

                try {
                    const response = await axios.post(`/admin/variants/${variantId}/adjust-stock`, {
                        quantity_change: qty,
                        reason: reason || null,
                    }, { headers: { Accept: 'application/json' } });

                    return response.data;
                } catch (error) {
                    Swal.showValidationMessage(error.response?.data?.message || 'Error adjusting stock');
                    return false;
                }
            },
        }).then((result) => {
            if (!result.isConfirmed) return;

            if (typeof window.AdminHelpers !== 'undefined') {
                window.AdminHelpers.showToast(result.value.message || 'Stock adjusted!', 'success');
            }

            if (result.value?.variant) {
                this._upsertVariantRow(result.value.variant);
            } else {
                window.location.reload();
            }
        });
    },

    _bindImageActions() {
        const fileInput = document.getElementById('imageFileInput');
        if (fileInput && fileInput.dataset.asyncBound !== '1') {
            fileInput.dataset.asyncBound = '1';

            fileInput.addEventListener('change', async () => {
                const form = document.getElementById('imageUploadForm');
                if (!form || !fileInput.files.length) return;

                try {
                    const response = await axios.post(form.action, new FormData(form), {
                        headers: { Accept: 'application/json' },
                    });

                    if (typeof window.AdminHelpers !== 'undefined') {
                        window.AdminHelpers.showToast(response.data?.message || 'Images uploaded!', 'success');
                    }

                    this._renderImages(response.data?.images || []);
                    fileInput.value = '';
                } catch (error) {
                    if (typeof window.AdminHelpers !== 'undefined') {
                        window.AdminHelpers.showToast(error.response?.data?.message || 'Upload failed', 'error');
                    }
                }
            });
        }

        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('.btn-set-primary');
            if (!btn) return;

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const response = await axios.post(btn.dataset.url, { _token: csrfToken }, {
                    headers: { Accept: 'application/json' },
                });
                if (typeof window.AdminHelpers !== 'undefined') {
                    window.AdminHelpers.showToast(response.data?.message || 'Primary image updated!', 'success');
                }

                this._renderImages(response.data?.images || []);
            } catch (error) {
                if (typeof window.AdminHelpers !== 'undefined') {
                    const message = error.response?.data?.message
                        || (error.response?.status ? `Failed to set primary image (HTTP ${error.response.status})` : 'Failed to set primary image');
                    window.AdminHelpers.showToast(message, 'error');
                }
            }
        });

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-delete-image');
            if (!btn || typeof window.AdminHelpers === 'undefined') return;

            window.AdminHelpers.confirmDelete(btn.dataset.url, {
                title: 'Delete this image?',
                text: 'This action cannot be undone.',
                onSuccess: (data) => {
                    this._renderImages(data?.images || []);
                },
            });
        });
    },

    _upsertVariantRow(variant) {
        if (!variant) return;

        const tbody = document.querySelector('#variantsTable tbody');
        if (!tbody) return;

        document.getElementById('noVariantsRow')?.remove();

        const rowHtml = this._buildVariantRowHtml(variant);
        const existingRow = tbody.querySelector(`tr[data-variant-id="${variant.id}"]`);

        if (existingRow) {
            existingRow.outerHTML = rowHtml;
        } else {
            tbody.insertAdjacentHTML('beforeend', rowHtml);
        }

        this._syncVariantsEmptyState();
        if (typeof feather !== 'undefined') feather.replace();
    },

    _syncVariantsEmptyState() {
        const tbody = document.querySelector('#variantsTable tbody');
        if (!tbody) return;

        const rows = tbody.querySelectorAll('tr[data-variant-id]');
        const emptyRow = document.getElementById('noVariantsRow');

        if (rows.length === 0 && !emptyRow) {
            tbody.insertAdjacentHTML('beforeend', '<tr id="noVariantsRow"><td colspan="11" class="text-center text-muted py-3">No variants yet. Add one above.</td></tr>');
        }

        if (rows.length > 0) {
            emptyRow?.remove();
        }
    },

    _buildVariantRowHtml(variant) {
        const quantity = Number(variant.quantity ?? 0);
        const threshold = Number(variant.low_stock_threshold ?? 0);
        const availableQuantity = variant.available_quantity ?? quantity;
        const bridgeQuantity = variant.bridge_stock_quantity ?? '—';
        const syncStatus = variant.stock_sync_status || '—';
        const hasColor = !!(variant.color_name || variant.color_hex);
        const colorHex = variant.color_hex || '#000000';
        const colorName = variant.color_name || 'Unnamed';
        const isListed = !!variant.is_listed_separately;
        const mappedImagesCount = Number(variant.mapped_images_count ?? (Array.isArray(variant.mapped_image_ids) ? variant.mapped_image_ids.length : 0));
        const mappedImageIds = Array.isArray(variant.mapped_image_ids) ? variant.mapped_image_ids : [];
        const bridgeVariationId = variant.bridge_variation_id
            ? `<div class="text-muted">Var #${this._escapeHtml(String(variant.bridge_variation_id))}</div>`
            : '';

        let stockBadge = '<span class="badge bg-success">In Stock</span>';
        if (quantity <= 0) {
            stockBadge = '<span class="badge bg-danger">Out of Stock</span>';
        } else if (quantity <= threshold) {
            stockBadge = '<span class="badge bg-warning text-dark">Low Stock</span>';
        }

        return `
            <tr data-variant-id="${variant.id}">
                <td class="fw-bold">${this._escapeHtml(variant.name || '')}</td>
                <td>
                    ${hasColor
                        ? `<span class="d-inline-flex align-items-center gap-1">
                                <span style="width:14px;height:14px;border-radius:50%;background:${this._escapeHtml(colorHex)};display:inline-block;border:1px solid #dee2e6;"></span>
                                ${this._escapeHtml(colorName)}
                           </span>`
                        : '<span class="text-muted">—</span>'}
                </td>
                <td>
                    ${isListed
                        ? '<span class="badge bg-primary">Listed</span>'
                        : '<span class="badge bg-light text-muted border">Hidden</span>'}
                </td>
                <td>
                    <span class="badge ${mappedImagesCount > 0 ? 'bg-info text-dark' : 'bg-light text-muted border'}">${this._escapeHtml(String(mappedImagesCount))} mapped</span>
                </td>
                <td>${this._escapeHtml(String(availableQuantity))}</td>
                <td>${this._escapeHtml(String(quantity))}</td>
                <td>${this._escapeHtml(String(bridgeQuantity))}</td>
                <td>${this._escapeHtml(String(threshold))}</td>
                <td>${stockBadge}</td>
                <td>
                    <div class="small">
                        <div>${this._escapeHtml(syncStatus)}</div>
                        ${bridgeVariationId}
                    </div>
                </td>
                <td>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-outline-primary btn-sm p-1 btn-edit-variant"
                                data-variant='${this._escapeAttributeJson(variant)}'
                                title="Edit">
                            <i data-feather="edit-2" style="width:14px;height:14px;"></i>
                        </button>
                        <button type="button" class="btn ${isListed ? 'btn-primary' : 'btn-outline-primary'} btn-sm p-1 btn-toggle-listed-variant"
                                data-url="${this._escapeHtml(variant.toggle_listing_url || `/admin/products/variants/${variant.id}/toggle-listing`)}"
                                data-is-listed="${isListed ? '1' : '0'}"
                                title="Toggle catalog listing">
                            <i data-feather="layers" style="width:14px;height:14px;"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm p-1 btn-map-variant-images"
                                data-sync-url="${this._escapeHtml(variant.sync_images_url || `/admin/products/variants/${variant.id}/images`)}"
                                data-image-ids='${this._escapeAttributeJson(mappedImageIds)}'
                                title="Map images">
                            <i data-feather="image" style="width:14px;height:14px;"></i>
                        </button>
                        <button type="button" class="btn btn-outline-info btn-sm p-1 btn-adjust-stock"
                                data-variant-id="${variant.id}"
                                data-variant-name="${this._escapeHtml(variant.name || '')}"
                                title="Adjust Stock">
                            <i data-feather="package" style="width:14px;height:14px;"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm p-1 btn-delete-variant"
                                data-url="${this._escapeHtml(variant.delete_url || `/admin/products/variants/${variant.id}`)}"
                                data-name="${this._escapeHtml(variant.name || '')}"
                                title="Delete">
                            <i data-feather="trash-2" style="width:14px;height:14px;"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    },

    async _openVariantImagePicker(syncUrl, selectedIds = []) {
        const allImagesUrl = this._currentProductData?.allImagesJsonUrl;
        if (!allImagesUrl || !syncUrl || typeof Swal === 'undefined') {
            return;
        }

        let images = [];
        try {
            const response = await axios.get(allImagesUrl, {
                params: {
                    product_id: this._currentProductData.id,
                },
            });

            images = response.data?.images || [];
        } catch (error) {
            if (typeof window.AdminHelpers !== 'undefined') {
                window.AdminHelpers.showToast(error.response?.data?.message || 'Failed to load product images', 'error');
            }
            return;
        }

        const selectedSet = new Set((selectedIds || []).map((id) => Number(id)));
        const imageOptions = images.map((image) => {
            const imageId = Number(image.id);
            const label = this._escapeHtml(image.product_name || `Image #${imageId}`);
            const thumb = this._escapeHtml(image.thumbnail_url || image.url || '');
            const checked = selectedSet.has(imageId) ? 'checked' : '';

            return `
                <label class="d-flex align-items-center gap-2 border rounded p-2 mb-2">
                    <input type="checkbox" class="variant-image-checkbox" value="${imageId}" ${checked}>
                    <img src="${thumb}" alt="${label}" style="width:42px;height:42px;object-fit:cover;border-radius:6px;">
                    <span class="small text-start flex-grow-1">${label}</span>
                </label>
            `;
        }).join('');

        await Swal.fire({
            title: 'Map Images to Variant',
            html: images.length
                ? `<div style="max-height: 360px; overflow-y:auto;">${imageOptions}</div><p class="small text-muted mt-2 mb-0">Order is saved top-to-bottom.</p>`
                : '<p class="text-muted mb-0">No images available for this product yet.</p>',
            showCancelButton: true,
            confirmButtonText: 'Save Mapping',
            preConfirm: async () => {
                if (!images.length) {
                    return true;
                }

                const selectedImageIds = Array.from(document.querySelectorAll('.variant-image-checkbox:checked'))
                    .map((el) => Number(el.value))
                    .filter((id) => !Number.isNaN(id));

                try {
                    const response = await axios.put(syncUrl, {
                        image_ids: selectedImageIds,
                    }, { headers: { Accept: 'application/json' } });

                    return response.data;
                } catch (error) {
                    const msg = error.response?.data?.message
                        || Object.values(error.response?.data?.errors || {}).flat().join(', ')
                        || 'Failed to save mapping';
                    Swal.showValidationMessage(msg);
                    return false;
                }
            },
        }).then((result) => {
            if (!result.isConfirmed || !result.value) {
                return;
            }

            if (typeof window.AdminHelpers !== 'undefined') {
                window.AdminHelpers.showToast(result.value.message || 'Mapping saved', 'success');
            }

            if (result.value.variant) {
                this._upsertVariantRow(result.value.variant);
            }
        });
    },

    _safeJsonParse(value, fallback) {
        if (typeof value !== 'string' || value.trim() === '') {
            return fallback;
        }

        try {
            return JSON.parse(value);
        } catch (_) {
            return fallback;
        }
    },

    _renderImages(images) {
        const grid = document.getElementById('imagesGrid');
        if (!grid) return;

        if (!Array.isArray(images) || images.length === 0) {
            grid.innerHTML = `
                <div class="col-12" id="noImagesMsg">
                    <p class="text-center text-muted py-3 mb-0">No images uploaded yet.</p>
                </div>
            `;
            return;
        }

        grid.innerHTML = images.map((image) => `
            <div class="col-6 col-md-3 col-lg-2" data-image-id="${image.id}">
                <div class="card h-100 ${image.is_primary ? 'border-primary' : ''}">
                    <img src="${this._escapeHtml(image.thumbnail_url || image.url)}" class="card-img-top" alt="${this._escapeHtml(image.alt_ka || image.alt_en || '')}" style="height:120px;object-fit:cover;">
                    <div class="card-body p-2 text-center">
                        ${image.is_primary
                            ? '<span class="badge bg-primary mb-1">Primary</span>'
                            : `<button type="button" class="btn btn-outline-primary btn-sm p-1 mb-1 btn-set-primary"
                                    data-url="${this._escapeHtml(image.primary_url)}"
                                    title="Set as Primary">
                                    <i data-feather="star" style="width:12px;height:12px;"></i>
                               </button>`}
                        <button type="button" class="btn btn-outline-danger btn-sm p-1 mb-1 btn-delete-image"
                                data-url="${this._escapeHtml(image.delete_url)}"
                                title="Delete">
                            <i data-feather="trash-2" style="width:12px;height:12px;"></i>
                        </button>
                    </div>
                </div>
            </div>
        `).join('');

        if (typeof feather !== 'undefined') feather.replace();
    },

    _escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    },

    _escapeAttributeJson(value) {
        return this._escapeHtml(JSON.stringify(value));
    },
};

window.AdminProducts = AdminProducts;

export default AdminProducts;
