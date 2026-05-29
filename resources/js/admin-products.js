/**
 * Admin Products — DataTable, variant CRUD, image management, delete confirmations
 */

// Lightbox Logic
let currentImages = [];
let currentIndex = 0;

function openLightbox(images, startIndex) {
    currentImages = images;
    currentIndex = startIndex;
    const lightbox = document.getElementById('global-lightbox');
    
    if (!lightbox) return;
    
    updateLightboxView();
    lightbox.classList.add('active');
    document.body.style.overflow = 'hidden'; // Prevent scrolling
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
    
    // Update main image
    mainImg.src = currentImages[currentIndex].url;
    
    // Build thumbnails
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

// Global Event Listeners for Lightbox
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('lightbox-close')?.addEventListener('click', closeLightbox);
    document.getElementById('lightbox-next')?.addEventListener('click', nextLightboxImage);
    document.getElementById('lightbox-prev')?.addEventListener('click', prevLightboxImage);
    
    // Keyboard navigation
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

    // ── Product Index Page ──
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
                { orderable: false, targets: [0, 6] },
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
        triggers.forEach(trigger => {
            // Remove existing listener to avoid duplicates
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
                    if (res.data && res.data.images && res.data.images.length > 0) {
                        openLightbox(res.data.images, 0);
                    } else {
                        if (window.AdminHelpers) window.AdminHelpers.showToast('No images available for this product', 'info');
                    }
                } catch (err) {
                    console.error('Error loading gallery', err);
                    if (window.AdminHelpers) window.AdminHelpers.showToast('Failed to load images', 'error');
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

            const url = btn.dataset.url;
            const name = btn.dataset.name;

            if (typeof window.AdminHelpers !== 'undefined') {
                window.AdminHelpers.confirmDelete(url, `Delete product "${name}"?`);
            }
        });
    },

    // ── Product Form (Create/Edit shared) ──
    initForm() {
        this._bindFormSubmit();
    },

    _bindFormSubmit() {
        const form = document.getElementById('productForm');
        if (!form) return;

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
                    headers: { 'Accept': 'application/json' },
                });

                if (response.data.redirect) {
                    if (window.AdminRouter) {
                        window.AdminRouter.navigate(response.data.redirect);
                    } else {
                        window.location.href = response.data.redirect;
                    }
                } else {
                    if (typeof window.AdminHelpers !== 'undefined') {
                        window.AdminHelpers.showToast(response.data.message || 'Saved!', 'success');
                    }
                }
            } catch (error) {
                if (error.response && error.response.status === 422 && typeof window.AdminHelpers !== 'undefined') {
                    window.AdminHelpers.showValidationErrors(error.response.data.errors, form);
                } else {
                    const msg = error.response?.data?.message || 'An error occurred.';
                    if (typeof window.AdminHelpers !== 'undefined') {
                        window.AdminHelpers.showToast(msg, 'error');
                    }
                }
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                if (typeof feather !== 'undefined') feather.replace();
            }
        });
    },

    // ── Edit Page (Variants + Images) ──
    initEdit() {
        this._bindVariantActions();
        this._bindImageActions();
    },

    _bindVariantActions() {
        const productDataEl = document.getElementById('product-data');
        if (!productDataEl) return;

        let productData;
        try { productData = JSON.parse(productDataEl.textContent); } catch { return; }

        // Add Variant
        const btnAdd = document.getElementById('btnAddVariant');
        if (btnAdd) {
            btnAdd.addEventListener('click', () => this._showVariantModal(null, productData.storeVariantUrl));
        }

        // Edit Variant
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-edit-variant');
            if (!btn) return;
            const variant = JSON.parse(btn.dataset.variant);
            const url = `/admin/products/variants/${variant.id}`;
            this._showVariantModal(variant, url, 'PATCH');
        });

        // Delete Variant
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-delete-variant');
            if (!btn) return;
            if (typeof window.AdminHelpers !== 'undefined') {
                window.AdminHelpers.confirmDelete(btn.dataset.url, `Delete variant "${btn.dataset.name}"?`)
                    .then((confirmed) => { if (confirmed) location.reload(); });
            }
        });

        // Adjust Stock
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-adjust-stock');
            if (!btn) return;
            this._showStockAdjustModal(btn.dataset.variantId, btn.dataset.variantName);
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
                        <label class="form-label small">Name <span class="text-danger">*</span></label>
                        <input type="text" id="swal-name" class="form-control form-control-sm" value="${variant?.name || ''}" required>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small">Color Name</label>
                            <input type="text" id="swal-color-name" class="form-control form-control-sm" value="${variant?.color_name || ''}">
                        </div>
                        <div class="col-6">
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
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: isEdit ? 'Update' : 'Add',
            preConfirm: async () => {
                const data = {
                    name: document.getElementById('swal-name').value,
                    color_name: document.getElementById('swal-color-name').value || null,
                    color_hex: document.getElementById('swal-color-name').value ? document.getElementById('swal-color-hex').value : null,
                    quantity: parseInt(document.getElementById('swal-qty').value, 10),
                    low_stock_threshold: parseInt(document.getElementById('swal-threshold').value, 10),
                };

                if (!data.name) {
                    Swal.showValidationMessage('Name is required');
                    return false;
                }

                try {
                    const headers = { 'Accept': 'application/json' };
                    let response;
                    if (method === 'PATCH') {
                        response = await axios.patch(url, data, { headers });
                    } else {
                        response = await axios.post(url, data, { headers });
                    }
                    return response.data;
                } catch (error) {
                    const msg = error.response?.data?.message || Object.values(error.response?.data?.errors || {}).flat().join(', ') || 'Error';
                    Swal.showValidationMessage(msg);
                    return false;
                }
            },
        }).then((result) => {
            if (result.isConfirmed) {
                if (typeof window.AdminHelpers !== 'undefined') {
                    window.AdminHelpers.showToast(result.value.message || 'Saved!', 'success');
                }
                location.reload();
            }
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
                    }, { headers: { 'Accept': 'application/json' } });
                    return response.data;
                } catch (error) {
                    const msg = error.response?.data?.message || 'Error adjusting stock';
                    Swal.showValidationMessage(msg);
                    return false;
                }
            },
        }).then((result) => {
            if (result.isConfirmed) {
                if (typeof window.AdminHelpers !== 'undefined') {
                    window.AdminHelpers.showToast(result.value.message || 'Stock adjusted!', 'success');
                }
                location.reload();
            }
        });
    },

    _bindImageActions() {
        // Upload images
        const fileInput = document.getElementById('imageFileInput');
        if (fileInput) {
            fileInput.addEventListener('change', async () => {
                const form = document.getElementById('imageUploadForm');
                if (!form || !fileInput.files.length) return;

                const formData = new FormData(form);
                try {
                    await axios.post(form.action, formData, {
                        headers: { 'Accept': 'application/json' },
                    });
                    if (typeof window.AdminHelpers !== 'undefined') {
                        window.AdminHelpers.showToast('Images uploaded!', 'success');
                    }
                    location.reload();
                } catch (error) {
                    const msg = error.response?.data?.message || 'Upload failed';
                    if (typeof window.AdminHelpers !== 'undefined') {
                        window.AdminHelpers.showToast(msg, 'error');
                    }
                }
            });
        }

        // Set primary
        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('.btn-set-primary');
            if (!btn) return;
            try {
                await axios.post(btn.dataset.url, {}, { headers: { 'Accept': 'application/json' } });
                if (typeof window.AdminHelpers !== 'undefined') {
                    window.AdminHelpers.showToast('Primary image updated!', 'success');
                }
                location.reload();
            } catch (error) {
                if (typeof window.AdminHelpers !== 'undefined') {
                    window.AdminHelpers.showToast('Failed to set primary image', 'error');
                }
            }
        });

        // Delete image
        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('.btn-delete-image');
            if (!btn) return;
            if (typeof window.AdminHelpers !== 'undefined') {
                window.AdminHelpers.confirmDelete(btn.dataset.url, 'Delete this image?')
                    .then((confirmed) => { if (confirmed) location.reload(); });
            }
        });
    },
};

window.AdminProducts = AdminProducts;

export default AdminProducts;
