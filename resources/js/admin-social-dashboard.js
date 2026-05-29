/**
 * Admin Social Dashboard — 4-tab SPA (Overview / Posts / Comments / Schedule)
 */
import { initSocialComments, destroySocialComments } from './admin-social-comments';

let cfg = {};
let postsState = { page: 1 };
let commentsInitialized = false;
let postFormSubmitting = false;
let socialImageManagerState = {
    modal: null,
    selectedImageUrl: null,
    galleryCurrentPage: 1,
    galleryHasMore: false,
    targetUrlInput: null,
    getProductId: () => '',
    onApply: null,
    initialized: false,
    cropperInstance: null,
    flipX: 1,
    flipY: 1,
};

const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

function getBootstrap() {
    return window.bootstrap || globalThis.bootstrap || null;
}

function esc(s) {
    if (!s) return '';
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function toAbsoluteUrl(url) {
    if (!url) return '';

    try {
        return new URL(url, window.location.origin).href;
    } catch (_) {
        return url;
    }
}

function isResolvableImageUrl(url) {
    if (!url) return false;

    return /^(https?:\/\/|\/|storage\/)/i.test(url);
}

function getSelectedProductSlug() {
    const productEl = $('#sd-form-product');
    if (!productEl) return '';

    return productEl.selectedOptions?.[0]?.dataset?.slug || '';
}

// ── Status / Platform helpers ─────────────────────────────────────────
const statusBadge = (s) => {
    const map = {
        draft: ['secondary', 'დრაფტი'],
        scheduled: ['primary', 'დაგეგმილი'],
        published: ['success', 'გამოქვეყნებული'],
        failed: ['danger', 'შეცდომა'],
    };
    const [color, label] = map[s] || ['secondary', s];
    return `<span class="badge bg-${color}">${label}</span>`;
};

const platformBadges = (fb, ig) => {
    let out = '';
    if (fb) out += '<span class="badge bg-primary me-1" style="font-size:10px;">FB</span>';
    if (ig) out += '<span class="badge me-1" style="font-size:10px;background:linear-gradient(45deg,#405de6,#e1306c);">IG</span>';
    return out || '—';
};

// ── Overview tab ──────────────────────────────────────────────────────
async function loadOverview() {
    try {
        const res = await axios.get(cfg.statsUrl);
        const s = res.data;

        const set = (id, val) => {
            const el = $(id);
            if (el) el.textContent = val ?? '0';
        };

        set('#sd-stat-total-posts', s.total_posts);
        set('#sd-stat-published', s.published_posts);
        set('#sd-stat-scheduled', s.scheduled_posts);
        set('#sd-stat-comments', s.total_comments);
        set('#sd-stat-unread', s.unread_comments);
        set('#sd-stat-reactions', (s.total_reactions || 0) + (s.total_ig_likes || 0));

        const unreadBadge = $('#sd-comments-badge');
        if (unreadBadge) {
            if (s.unread_comments > 0) {
                unreadBadge.textContent = s.unread_comments;
                unreadBadge.classList.remove('d-none');
            } else {
                unreadBadge.classList.add('d-none');
            }
        }

        // Recent posts table
        const tbody = $('#sd-recent-posts-tbody');
        if (tbody) {
            if (!s.recent_posts || s.recent_posts.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">პოსტები არ არის</td></tr>';
            } else {
                tbody.innerHTML = s.recent_posts.map(p => `
                    <tr>
                        <td>
                            <div class="small" style="max-width:280px;word-break:break-word;">${esc(p.message)}${p.has_image ? ' <i data-feather="image" style="width:11px;height:11px;" class="text-muted"></i>' : ''}</div>
                        </td>
                        <td>${platformBadges(p.platforms?.includes('FB'), p.platforms?.includes('IG'))}</td>
                        <td>${statusBadge(p.status)}</td>
                        <td class="small text-muted">${p.published_at || '—'}</td>
                    </tr>
                `).join('');
                if (window.feather) window.feather.replace();
            }
        }

        // Recent comments list
        const commentsList = $('#sd-recent-comments-list');
        if (commentsList) {
            if (!s.recent_comments || s.recent_comments.length === 0) {
                commentsList.innerHTML = '<div class="list-group-item text-center text-muted py-3">კომენტარები არ არის</div>';
            } else {
                const statusColors = { unread: 'warning', read: 'secondary', replied: 'success', spam: 'danger', hidden: 'dark' };
                commentsList.innerHTML = s.recent_comments.map(c => `
                    <div class="list-group-item list-group-item-action py-2 px-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="small fw-bold">
                                ${c.platform === 'instagram'
                                    ? '<span class="badge me-1" style="font-size:9px;background:linear-gradient(45deg,#405de6,#e1306c);">IG</span>'
                                    : '<span class="badge bg-primary me-1" style="font-size:9px;">FB</span>'}
                                ${esc(c.author_name)}
                            </div>
                            <span class="badge bg-${statusColors[c.status] || 'secondary'}" style="font-size:9px;">${c.status}</span>
                        </div>
                        <div class="small text-muted mt-1" style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(c.message)}</div>
                        <div class="text-muted" style="font-size:10px;">${c.commented_at || ''}</div>
                    </div>
                `).join('');
            }
        }
    } catch (e) {
        console.error('Social Dashboard stats error', e);
    }
}

// ── Posts tab ─────────────────────────────────────────────────────────
async function loadPosts() {
    const tbody = $('#sd-posts-tbody');
    if (!tbody) return;

    const params = new URLSearchParams({
        status: $('#sd-posts-filter-status')?.value || 'all',
        search: $('#sd-posts-search')?.value || '',
        page: postsState.page,
    });

    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></td></tr>';

    try {
        const res = await axios.get(cfg.postsUrl + '?' + params.toString());
        const { data, meta } = res.data;

        if (!data || data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">პოსტები არ მოიძებნა</td></tr>';
        } else {
            tbody.innerHTML = data.map(p => `
                <tr data-id="${p.id}">
                    <td>
                        <div class="small" style="max-width:300px;word-break:break-word;">${esc(p.message)}</div>
                        ${p.product ? `<div class="text-muted" style="font-size:10px;">${esc(p.product)}</div>` : ''}
                    </td>
                    <td>${platformBadges(p.post_to_facebook, p.post_to_instagram)}</td>
                    <td>${statusBadge(p.status)}</td>
                    <td class="small text-muted">${p.status === 'scheduled' ? (p.scheduled_at || '—') : (p.published_at || p.created_at || '—')}</td>
                    <td class="small text-muted">${esc(p.author)}</td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn btn-outline-primary btn-sm p-1 sd-post-edit-btn" data-id="${p.id}" title="რედაქტირება">
                                <i data-feather="edit-2" style="width:12px;height:12px;"></i>
                            </button>
                            ${p.status !== 'published' ? `<button class="btn btn-outline-success btn-sm p-1 sd-post-publish-btn" data-id="${p.id}" title="გამოქვეყნება">
                                <i data-feather="send" style="width:12px;height:12px;"></i>
                            </button>` : ''}
                            <button class="btn btn-outline-danger btn-sm p-1 sd-post-delete-btn" data-id="${p.id}" title="წაშლა">
                                <i data-feather="trash-2" style="width:12px;height:12px;"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        // Pagination
        const pag = $('#sd-posts-pagination');
        if (pag && meta) {
            pag.classList.toggle('d-none', meta.last_page <= 1);
            const prev = $('#sd-posts-prev');
            const next = $('#sd-posts-next');
            const info = $('#sd-posts-page-info');
            if (prev) prev.disabled = meta.current_page <= 1;
            if (next) next.disabled = meta.current_page >= meta.last_page;
            if (info) info.textContent = `გვ. ${meta.current_page} / ${meta.last_page} (${meta.total} პოსტი)`;
        }

        if (window.feather) window.feather.replace();
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger">ჩატვირთვა ვერ მოხერხდა</td></tr>';
    }
}

// ── Scheduled tab ─────────────────────────────────────────────────────
async function loadScheduled() {
    const tbody = $('#sd-schedule-tbody');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></td></tr>';

    try {
        const res = await axios.get(cfg.scheduledUrl);
        const posts = res.data.data || [];

        if (posts.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">დაგეგმილი პოსტები არ არის</td></tr>';
        } else {
            tbody.innerHTML = posts.map(p => `
                <tr data-id="${p.id}">
                    <td><div class="small" style="max-width:300px;word-break:break-word;">${esc(p.message)}</div></td>
                    <td>${platformBadges(p.post_to_facebook, p.post_to_instagram)}</td>
                    <td>
                        <div class="small fw-bold">${p.scheduled_at || '—'}</div>
                        <div class="text-muted" style="font-size:10px;">${p.scheduled_at_human || ''}</div>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn btn-outline-primary btn-sm p-1 sd-post-edit-btn" data-id="${p.id}" title="რედაქტირება">
                                <i data-feather="edit-2" style="width:12px;height:12px;"></i>
                            </button>
                            <button class="btn btn-outline-success btn-sm p-1 sd-post-publish-btn" data-id="${p.id}" title="ახლავე გამოქვეყნება">
                                <i data-feather="send" style="width:12px;height:12px;"></i>
                            </button>
                            <button class="btn btn-outline-danger btn-sm p-1 sd-post-delete-btn" data-id="${p.id}" title="წაშლა">
                                <i data-feather="trash-2" style="width:12px;height:12px;"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        if (window.feather) window.feather.replace();
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-danger">ჩატვირთვა ვერ მოხერხდა</td></tr>';
    }
}

// ── Post Offcanvas ────────────────────────────────────────────────────
function preparePostPanel(postId = null) {
    const offcanvasEl = $('#sd-post-offcanvas');
    const titleEl = $('#sd-post-offcanvas-title');
    const container = $('#sd-post-form-container');
    if (!offcanvasEl || !container) return null;

    const offcanvasBody = offcanvasEl.querySelector('.offcanvas-body');
    if (offcanvasBody) {
        offcanvasBody.style.overflowY = 'auto';
        offcanvasBody.style.overflowX = 'hidden';
    }

    if (titleEl) titleEl.textContent = postId ? 'პოსტის რედაქტირება' : 'ახალი პოსტი';
    container.innerHTML = renderPostForm(postId);

    return { offcanvasEl, container };
}

function openPostPanel(postId = null) {
    const prepared = preparePostPanel(postId);
    if (!prepared) return;

    prepared.offcanvasEl.classList.remove('d-none');
    initPostForm(postId);
    prepared.offcanvasEl.scrollIntoView({ behavior: 'smooth', block: 'start' });

    if (window.feather) window.feather.replace();
}

function closePostPanel() {
    const panel = $('#sd-post-offcanvas');
    if (!panel) return;

    panel.classList.add('d-none');
}

function updateSelectedImagePreview() {
    const input = $('#sd-form-image-url');
    const previewWrap = $('#sd-selected-image-preview');
    const previewImg = $('#sd-selected-image-preview-img');
    const previewEmpty = $('#sd-selected-image-empty');
    const mediaTypeEl = $('#sd-form-media-type');
    const rawUrl = input?.value?.trim() || '';
    const url = isResolvableImageUrl(rawUrl) ? toAbsoluteUrl(rawUrl) : '';

    if (!previewWrap || !previewImg || !previewEmpty) return;

    if (!url) {
        previewWrap.classList.add('d-none');
        previewImg.classList.add('d-none');
        previewImg.src = '';
        previewEmpty.classList.remove('d-none');
        return;
    }

    if (mediaTypeEl && mediaTypeEl.value !== 'image') {
        mediaTypeEl.value = 'image';
    }

    if (input) {
        input.value = url;
    }

    previewWrap.classList.remove('d-none');
    previewEmpty.classList.add('d-none');
    previewImg.classList.remove('d-none');
    previewImg.src = url;

    $$('#sd-product-image-gallery .sd-product-image-item').forEach((item) => {
        const active = item.dataset.url === url;
        item.classList.toggle('border-primary', active);
        item.classList.toggle('border-3', active);
        item.classList.toggle('shadow-sm', active);
        item.classList.toggle('border', !active);
    });
}

async function loadProductImagesForSelection(productId, selectedUrl = '', autoSelectFirst = false) {
    const section = $('#sd-product-images-section');
    const gallery = $('#sd-product-image-gallery');
    const emptyState = $('#sd-product-image-empty');
    const imageInput = $('#sd-form-image-url');
    const productSlug = getSelectedProductSlug();

    if (!section || !gallery || !emptyState) return [];

    if (!productId || !productSlug) {
        section.classList.add('d-none');
        gallery.innerHTML = '';
        emptyState.classList.add('d-none');
        return [];
    }

    section.classList.remove('d-none');
    gallery.innerHTML = '<div class="col-12 text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>';
    emptyState.classList.add('d-none');

    try {
        const res = await axios.get(cfg.productImagesUrl.replace('{id}', productSlug));
        const images = res.data?.images || [];

        if (!images.length) {
            gallery.innerHTML = '';
            emptyState.textContent = 'No images found with the current filters.';
            emptyState.classList.remove('d-none');
            return [];
        }

        gallery.innerHTML = images.map((img) => `
            <div class="col-6 col-md-4 col-xl-3">
                <button type="button" class="btn p-0 border rounded overflow-hidden w-100 text-start sd-product-image-item" data-url="${esc(toAbsoluteUrl(img.url))}" title="სურათის არჩევა">
                    <img src="${esc(toAbsoluteUrl(img.thumbnail_url || img.url))}" alt="Product image" class="w-100 d-block" style="height:120px;object-fit:cover;">
                    <span class="d-flex justify-content-between align-items-center px-2 py-1 bg-white small">
                        <span class="text-truncate">${img.is_primary ? 'მთავარი სურათი' : 'პროდუქტის სურათი'}</span>
                        ${img.is_primary ? '<span class="badge bg-primary">Primary</span>' : ''}
                    </span>
                </button>
            </div>
        `).join('');

        gallery.querySelectorAll('.sd-product-image-item').forEach((item) => {
            item.addEventListener('click', () => {
                if (imageInput) imageInput.value = toAbsoluteUrl(item.dataset.url || '');
                updateSelectedImagePreview();
            });
        });

        const currentUrl = toAbsoluteUrl(selectedUrl || imageInput?.value?.trim() || '');
        const primaryImage = images.find((img) => img.is_primary) || images[0];
        const matchingImage = images.find((img) => toAbsoluteUrl(img.url) === currentUrl);

        if (!matchingImage && autoSelectFirst && primaryImage && imageInput) {
            imageInput.value = toAbsoluteUrl(primaryImage.url);
        }

        updateSelectedImagePreview();
        return images;
    } catch (error) {
        gallery.innerHTML = '';
        emptyState.textContent = 'პროდუქტის სურათები ვერ ჩაიტვირთა';
        emptyState.classList.remove('d-none');
        return [];
    }
}

function ensureSocialImageManager() {
    const modalEl = document.getElementById('image-manager-modal');
    if (!modalEl) return null;

    const bs = getBootstrap();
    socialImageManagerState.modal = bs?.Modal ? bs.Modal.getOrCreateInstance(modalEl) : null;

    if (socialImageManagerState.initialized) {
        return socialImageManagerState.modal;
    }

    const galleryGrid = $('#gallery-grid');
    const galleryEmpty = $('#gallery-empty');
    const galleryLoading = $('#gallery-loading');
    const btnSelectGalleryImage = $('#btn-select-gallery-image');
    const filterProduct = $('#gallery-filter-product');
    const filterTime = $('#gallery-filter-time');
    const btnGalleryRefresh = $('#btn-gallery-refresh');
    const loadMoreContainer = $('#gallery-load-more-container');
    const btnGalleryLoadMore = $('#btn-gallery-load-more');
    const uploadZone = $('#upload-zone');
    const fileInput = $('#standalone-image-upload');
    const cropperContainer = $('#cropper-container');
    const cropperImage = $('#cropper-image');
    const cropperControls = $('#cropper-controls');
    const cropperRatio = $('#cropper-ratio');
    const btnCropSave = $('#btn-crop-save');
    const btnCropCancel = $('#btn-crop-cancel');
    const progressBarContainer = $('#upload-progress-container');
    const progressBar = $('#upload-progress-bar');

    const hideModal = () => {
        if (document.activeElement instanceof HTMLElement) {
            document.activeElement.blur();
        }
        socialImageManagerState.modal?.hide();
    };

    const resetCropper = () => {
        if (socialImageManagerState.cropperInstance) {
            try {
                socialImageManagerState.cropperInstance.destroy();
            } catch (_) {}
            socialImageManagerState.cropperInstance = null;
        }

        socialImageManagerState.flipX = 1;
        socialImageManagerState.flipY = 1;

        if (cropperImage) cropperImage.src = '';
        cropperContainer?.classList.add('d-none');
        cropperControls?.classList.add('d-none');
        uploadZone?.classList.remove('d-none');
        if (fileInput) fileInput.value = '';
    };

    const loadGallery = async (clearExisting = true) => {
        if (!galleryGrid || !galleryEmpty || !galleryLoading || !btnSelectGalleryImage) return;

        if (clearExisting) {
            galleryEmpty.classList.add('d-none');
            galleryGrid.innerHTML = '';
            btnSelectGalleryImage.disabled = true;
            socialImageManagerState.selectedImageUrl = null;
        }

        galleryLoading.classList.remove('d-none');
        loadMoreContainer?.classList.add('d-none');

        try {
            const params = new URLSearchParams({
                page: socialImageManagerState.galleryCurrentPage,
            });

            if (filterProduct?.value) params.append('product_id', filterProduct.value);
            if (filterTime?.value) params.append('time_filter', filterTime.value);

            const res = await axios.get(`${cfg.allImagesUrl}?${params.toString()}`);
            galleryLoading.classList.add('d-none');

            if (res.data.images && res.data.images.length > 0) {
                res.data.images.forEach((img) => {
                    const col = document.createElement('div');
                    col.className = 'col-4 col-sm-3 col-md-2 mb-2';
                    col.innerHTML = `
                        <div class="card h-100 cursor-pointer gallery-item border position-relative" data-url="${esc(toAbsoluteUrl(img.url))}">
                            <img src="${esc(toAbsoluteUrl(img.thumbnail_url || img.url))}" class="card-img-top" style="object-fit: cover; height: 100px;" alt="Product Image" loading="lazy">
                            <div class="position-absolute bottom-0 start-0 w-100 p-1" style="background: rgba(0,0,0,0.6); backdrop-filter: blur(2px);">
                                <div class="text-white text-truncate" style="font-size: 0.65rem;" title="${esc(img.product_name)}">${esc(img.product_name)}</div>
                            </div>
                        </div>
                    `;

                    col.querySelector('.gallery-item')?.addEventListener('click', function () {
                        $$('.gallery-item').forEach((el) => {
                            el.classList.remove('border-primary', 'border-3');
                            el.classList.add('border');
                        });

                        this.classList.remove('border');
                        this.classList.add('border-primary', 'border-3');
                        socialImageManagerState.selectedImageUrl = toAbsoluteUrl(this.dataset.url);
                        btnSelectGalleryImage.disabled = false;
                    });

                    galleryGrid.appendChild(col);
                });

                socialImageManagerState.galleryHasMore = res.data.current_page < res.data.last_page;
                if (socialImageManagerState.galleryHasMore) {
                    loadMoreContainer?.classList.remove('d-none');
                }
            } else if (clearExisting) {
                galleryEmpty.textContent = 'No images found with the current filters.';
                galleryEmpty.classList.remove('d-none');
            }
        } catch (error) {
            galleryLoading.classList.add('d-none');
            if (clearExisting) {
                galleryEmpty.textContent = 'Failed to load images.';
                galleryEmpty.classList.remove('d-none');
            }
        }
    };

    const initCropper = () => {
        if (!cropperImage || typeof window.Cropper !== 'function') return;

        if (socialImageManagerState.cropperInstance) {
            try {
                socialImageManagerState.cropperInstance.destroy();
            } catch (_) {}
            socialImageManagerState.cropperInstance = null;
        }

        socialImageManagerState.cropperInstance = new window.Cropper(cropperImage, {
            aspectRatio: NaN,
            viewMode: 1,
            background: false,
            zoomable: true,
            responsive: true,
        });
    };

    const handleFileSelection = (file) => {
        if (!file.type.match(/^image\/(jpeg|png|webp)$/)) {
            window.AdminHelpers?.showToast('Invalid file format. Only JPG, PNG, WEBP allowed.', 'error');
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            window.AdminHelpers?.showToast('File is too large (max 5MB)', 'error');
            return;
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            uploadZone?.classList.add('d-none');
            cropperContainer?.classList.remove('d-none');
            cropperControls?.classList.remove('d-none');
            if (cropperImage) cropperImage.src = e.target?.result || '';
            setTimeout(() => initCropper(), 100);
        };
        reader.readAsDataURL(file);
    };

    btnGalleryRefresh && (btnGalleryRefresh.onclick = () => {
        socialImageManagerState.galleryCurrentPage = 1;
        loadGallery(true);
    });

    btnGalleryLoadMore && (btnGalleryLoadMore.onclick = () => {
        if (!socialImageManagerState.galleryHasMore) return;
        socialImageManagerState.galleryCurrentPage += 1;
        loadGallery(false);
    });

    btnSelectGalleryImage && (btnSelectGalleryImage.onclick = async () => {
        const targetInput = socialImageManagerState.targetUrlInput;
        if (!targetInput || !socialImageManagerState.selectedImageUrl) return;

        const absoluteUrl = toAbsoluteUrl(socialImageManagerState.selectedImageUrl);
        targetInput.value = absoluteUrl;
        socialImageManagerState.onApply?.(absoluteUrl);
        hideModal();
        window.AdminHelpers?.showToast('Image URL updated', 'success');

        const productId = socialImageManagerState.getProductId?.() || '';
        if (productId) {
            await loadProductImagesForSelection(productId, absoluteUrl, false);
        }
    });

    if (modalEl) {
        modalEl.addEventListener('hidden.bs.modal', () => {
            modalEl.setAttribute('inert', '');
            resetCropper();
        });
    }

    uploadZone && fileInput && (uploadZone.onclick = () => fileInput.click());
    uploadZone?.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadZone.classList.add('bg-secondary', 'bg-opacity-10');
    });
    uploadZone?.addEventListener('dragleave', () => {
        uploadZone.classList.remove('bg-secondary', 'bg-opacity-10');
    });
    uploadZone?.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadZone.classList.remove('bg-secondary', 'bg-opacity-10');
        if (e.dataTransfer.files.length) handleFileSelection(e.dataTransfer.files[0]);
    });
    fileInput && (fileInput.onchange = function () {
        if (this.files?.length) handleFileSelection(this.files[0]);
    });

    cropperRatio && (cropperRatio.onchange = function () {
        if (socialImageManagerState.cropperInstance?.setAspectRatio) {
            socialImageManagerState.cropperInstance.setAspectRatio(parseFloat(this.value));
        }
    });

    $('#btn-crop-rotate-left') && ($('#btn-crop-rotate-left').onclick = () => socialImageManagerState.cropperInstance?.rotate?.(-90));
    $('#btn-crop-rotate-right') && ($('#btn-crop-rotate-right').onclick = () => socialImageManagerState.cropperInstance?.rotate?.(90));
    $('#btn-crop-flip-h') && ($('#btn-crop-flip-h').onclick = () => {
        socialImageManagerState.flipX = socialImageManagerState.flipX === 1 ? -1 : 1;
        socialImageManagerState.cropperInstance?.scaleX?.(socialImageManagerState.flipX);
    });
    $('#btn-crop-flip-v') && ($('#btn-crop-flip-v').onclick = () => {
        socialImageManagerState.flipY = socialImageManagerState.flipY === 1 ? -1 : 1;
        socialImageManagerState.cropperInstance?.scaleY?.(socialImageManagerState.flipY);
    });
    btnCropCancel && (btnCropCancel.onclick = () => resetCropper());

    btnCropSave && (btnCropSave.onclick = () => {
        const cropper = socialImageManagerState.cropperInstance;
        if (!cropper?.getCroppedCanvas) {
            window.AdminHelpers?.showToast('Please wait for image to load', 'warning');
            return;
        }

        btnCropSave.disabled = true;
        if (btnCropCancel) btnCropCancel.disabled = true;
        progressBarContainer?.classList.remove('d-none');
        if (progressBar) progressBar.style.width = '0%';

        const canvas = cropper.getCroppedCanvas({ maxWidth: 2048, maxHeight: 2048 });
        if (!canvas) {
            btnCropSave.disabled = false;
            if (btnCropCancel) btnCropCancel.disabled = false;
            window.AdminHelpers?.showToast('Failed to process image. Try again.', 'error');
            return;
        }

        canvas.toBlob(async (blob) => {
            const formData = new FormData();
            formData.append('image', blob, 'cropped_image.jpg');

            const productId = socialImageManagerState.getProductId?.() || '';
            if (productId) formData.append('product_id', productId);

            try {
                const res = await axios.post(cfg.uploadImageUrl, formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                    onUploadProgress: (progressEvent) => {
                        if (!progressBar) return;
                        const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                        progressBar.style.width = percentCompleted + '%';
                    },
                });

                if (res.data?.success && socialImageManagerState.targetUrlInput) {
                    const absoluteUrl = toAbsoluteUrl(res.data.url);
                    socialImageManagerState.targetUrlInput.value = absoluteUrl;
                    socialImageManagerState.onApply?.(absoluteUrl);
                    hideModal();
                    window.AdminHelpers?.showToast('Image uploaded & applied!', 'success');

                    if (filterProduct && productId) {
                        filterProduct.value = productId;
                    }

                    socialImageManagerState.galleryCurrentPage = 1;
                    await loadGallery(true);
                    if (productId) {
                        await loadProductImagesForSelection(productId, absoluteUrl, false);
                    }
                }
            } catch (error) {
                window.AdminHelpers?.showToast('Upload failed', 'error');
            } finally {
                btnCropSave.disabled = false;
                if (btnCropCancel) btnCropCancel.disabled = false;
                setTimeout(() => {
                    progressBarContainer?.classList.add('d-none');
                    if (progressBar) progressBar.style.width = '0%';
                }, 1000);
            }
        }, 'image/jpeg', 0.85);
    });

    socialImageManagerState.open = ({ targetInput, productId, onApply }) => {
        socialImageManagerState.targetUrlInput = targetInput;
        socialImageManagerState.getProductId = typeof productId === 'function' ? productId : () => productId || '';
        socialImageManagerState.onApply = onApply || null;
        socialImageManagerState.galleryCurrentPage = 1;
        socialImageManagerState.selectedImageUrl = null;

        const currentProductId = socialImageManagerState.getProductId?.() || '';
        if (filterProduct) filterProduct.value = currentProductId;

        modalEl.removeAttribute('inert');
        socialImageManagerState.modal?.show();
        loadGallery(true);
    };

    socialImageManagerState.initialized = true;
    return socialImageManagerState.modal;
}

function renderPostForm(postId) {
    const products = cfg.products || [];
    const productOptions = products.map(p =>
        `<option value="${p.id}" data-slug="${esc(p.slug || '')}">${esc(p.name)} — GEL ${p.price}</option>`
    ).join('');

    return `
    <form id="sd-post-form" data-post-id="${postId || ''}">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label small fw-bold">პროდუქტი <span class="text-muted">(სურვ.)</span></label>
                <select class="form-select form-select-sm" id="sd-form-product">
                    <option value="">— პროდუქტის გარეშე —</option>
                    ${productOptions}
                </select>
            </div>
            <div class="col-12">
                <label class="form-label small fw-bold">ტექსტი <span class="text-danger">*</span></label>
                <textarea class="form-control" id="sd-form-message" name="message" rows="8" required placeholder="პოსტის ტექსტი..."></textarea>
                <div class="d-flex justify-content-between align-items-center mt-1">
                    <span class="text-muted" style="font-size:11px;" id="sd-form-char-count">0 სიმბოლო</span>
                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" id="sd-form-hashtag-btn">
                        <i data-feather="hash" style="width:12px;height:12px;"></i> ჰეშთეგები
                    </button>
                </div>
                <div id="sd-hashtag-chips" class="d-flex flex-wrap gap-1 mt-1"></div>
            </div>
            <div class="col-6">
                <label class="form-label small fw-bold">მედია ტიპი</label>
                <select class="form-select form-select-sm" id="sd-form-media-type" name="media_type">
                    <option value="none">ტექსტი</option>
                    <option value="image">სურათი</option>
                    <option value="video">ვიდეო</option>
                </select>
            </div>
            <div class="col-6" id="sd-form-image-group">
                <label class="form-label small fw-bold">სურათის URL</label>
                <div class="input-group input-group-sm">
                    <input type="url" class="form-control form-control-sm" id="sd-form-image-url" name="image_url" placeholder="https://...">
                    <button type="button" class="btn btn-outline-secondary" id="sd-form-preview-image" title="Preview Image">
                        <i data-feather="external-link" style="width:12px;height:12px;"></i>
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="sd-form-image-manager" title="Open Image Manager">
                        <i data-feather="image" style="width:12px;height:12px;"></i>
                    </button>
                </div>
            </div>
            <div class="col-12 d-none" id="sd-selected-image-preview">
                <label class="form-label small fw-bold">არჩეული მედია</label>
                <div class="border rounded p-2 bg-light">
                    <div id="sd-selected-image-empty" class="small text-muted">სურათი ჯერ არ არის არჩეული</div>
                    <img id="sd-selected-image-preview-img" src="" alt="Selected media" class="img-fluid rounded d-none" style="max-height:220px;object-fit:cover;">
                </div>
            </div>
            <div class="col-12 d-none" id="sd-product-images-section">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label small fw-bold mb-0">პროდუქტის სურათები</label>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="sd-product-images-manager-btn">
                            <i data-feather="image" style="width:12px;height:12px;"></i> მედია მენეჯერი
                        </button>
                    </div>
                </div>
                <div id="sd-product-image-empty" class="small text-muted d-none mb-2">ამ პროდუქტს სურათები არ აქვს. შეგიძლია მედია მენეჯერიდან დაამატო.</div>
                <div class="row g-2" id="sd-product-image-gallery"></div>
            </div>
            <div class="col-12">
                <label class="form-label small fw-bold d-block">პლატფორმები</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" id="sd-form-fb" name="post_to_facebook" value="1" ${cfg.fbConfigured ? 'checked' : 'disabled'}>
                    <label class="form-check-label small" for="sd-form-fb">Facebook${!cfg.fbConfigured ? ' (კონფ. არ არის)' : ''}</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" id="sd-form-ig" name="post_to_instagram" value="1" ${cfg.igConfigured ? '' : 'disabled'}>
                    <label class="form-check-label small" for="sd-form-ig">Instagram${!cfg.igConfigured ? ' (კონფ. არ არის)' : ''}</label>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label small fw-bold">განრიგის დრო <span class="text-muted">(სურვ.)</span></label>
                <input type="datetime-local" class="form-control form-control-sm" id="sd-form-scheduled-at" name="scheduled_at">
            </div>
            <div class="col-12">
                <div class="border rounded p-2 bg-light">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold mb-1">AI გენ. ენა</label>
                            <select class="form-select form-select-sm" id="sd-form-ai-lang">
                                <option value="ka">ქართული</option>
                                <option value="en">English</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold mb-1">ტონი</label>
                            <select class="form-select form-select-sm" id="sd-form-ai-tone">
                                <option value="casual">მეგობრული</option>
                                <option value="professional">პროფ.</option>
                                <option value="exciting">ემოციური</option>
                                <option value="urgent">სასწრაფო</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="sd-form-ai-generate-btn">
                                <i data-feather="cpu" style="width:12px;height:12px;"></i> AI გენ.
                            </button>
                        </div>
                    </div>
                    <div id="sd-form-ai-results" class="mt-2 d-none"></div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 mt-4 pt-3 border-top">
            <button type="submit" name="sd-action" value="draft" class="btn btn-outline-warning btn-sm flex-fill" id="sd-form-btn-draft">შენახვა დრაფტად</button>
            <button type="submit" name="sd-action" value="schedule" class="btn btn-outline-primary btn-sm flex-fill" id="sd-form-btn-schedule">განრიგი</button>
            <button type="submit" name="sd-action" value="publish" class="btn btn-primary btn-sm flex-fill" id="sd-form-btn-publish">გამოქვეყნება</button>
        </div>
    </form>`;
}

async function initPostForm(postId) {
    const messageEl = $('#sd-form-message');
    const charCount = $('#sd-form-char-count');
    const mediaTypeEl = $('#sd-form-media-type');
    const imageGroup = $('#sd-form-image-group');
    const productEl = $('#sd-form-product');
    const imageUrlEl = $('#sd-form-image-url');
    const previewImageBtn = $('#sd-form-preview-image');
    const imageManagerBtn = $('#sd-form-image-manager');
    const productManagerBtn = $('#sd-product-images-manager-btn');

    ensureSocialImageManager();

    if (messageEl && charCount) {
        messageEl.addEventListener('input', () => {
            charCount.textContent = messageEl.value.length + ' სიმბოლო';
        });
    }

    if (mediaTypeEl && imageGroup) {
        const toggleMedia = () => {
            imageGroup.classList.toggle('d-none', mediaTypeEl.value === 'none' || mediaTypeEl.value === 'video');
        };
        mediaTypeEl.addEventListener('change', toggleMedia);
        toggleMedia();
    }

    const openImageManager = () => {
        if (!imageUrlEl) return;
        ensureSocialImageManager();
        socialImageManagerState.open?.({
            targetInput: imageUrlEl,
            productId: () => productEl?.value || '',
            onApply: (url) => {
                if (imageUrlEl) imageUrlEl.value = toAbsoluteUrl(url);
                updateSelectedImagePreview();
            },
        });
    };

    imageUrlEl?.addEventListener('input', () => updateSelectedImagePreview());
    previewImageBtn?.addEventListener('click', () => {
        const rawUrl = imageUrlEl?.value?.trim() || '';
        const url = isResolvableImageUrl(rawUrl) ? toAbsoluteUrl(rawUrl) : '';
        if (url) {
            window.open(url, '_blank');
        } else {
            window.AdminHelpers?.showToast('ჯერ სურათი აირჩიე', 'warning');
        }
    });
    imageManagerBtn?.addEventListener('click', () => openImageManager());
    productManagerBtn?.addEventListener('click', () => openImageManager());
    productEl?.addEventListener('change', async () => {
        const productId = productEl.value || '';
        await loadProductImagesForSelection(productId, imageUrlEl?.value || '', true);
    });

    // Load post data for edit
    if (postId) {
        try {
            const url = cfg.postEditUrl.replace('{id}', postId);
            const res = await axios.get(url, { headers: { 'X-PJAX': '1', 'Accept': 'application/json' } });
            // Try to extract from JSON or parse the form
            if (res.data && res.data.post) {
                const p = res.data.post;
                if (messageEl) messageEl.value = p.message || '';
                if (charCount) charCount.textContent = (p.message || '').length + ' სიმბოლო';
                if (productEl && p.product_id) productEl.value = p.product_id;
                const fbEl = $('#sd-form-fb');
                const igEl = $('#sd-form-ig');
                if (fbEl) fbEl.checked = !!p.post_to_facebook;
                if (igEl) igEl.checked = !!p.post_to_instagram;
                if (mediaTypeEl) mediaTypeEl.value = p.media_type || 'none';
                if (imageUrlEl) imageUrlEl.value = toAbsoluteUrl(p.image_url || '');
                const schedEl = $('#sd-form-scheduled-at');
                if (schedEl && p.scheduled_at) schedEl.value = p.scheduled_at.replace(' ', 'T').slice(0, 16);

                await loadProductImagesForSelection(productEl?.value || '', toAbsoluteUrl(p.image_url || ''), false);
                updateSelectedImagePreview();
            }
        } catch (_) {}
    } else {
        await loadProductImagesForSelection(productEl?.value || '', imageUrlEl?.value || '', true);
        updateSelectedImagePreview();
    }

    // AI Generate
    $('#sd-form-ai-generate-btn')?.addEventListener('click', async () => {
        const btn = $('#sd-form-ai-generate-btn');
        const resultsEl = $('#sd-form-ai-results');
        if (!btn || !resultsEl) return;

        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:10px;height:10px;"></span>';

        try {
            const res = await axios.post(cfg.generateUrl, {
                product_id: $('#sd-form-product')?.value || null,
                language: $('#sd-form-ai-lang')?.value || 'ka',
                tone: $('#sd-form-ai-tone')?.value || 'casual',
                mode: 'custom',
            });

            if (res.data.success && res.data.variants) {
                resultsEl.classList.remove('d-none');
                const badgeMap = { entertaining: 'warning', sales: 'danger', informational: 'info' };
                resultsEl.innerHTML = Object.entries(res.data.variants).map(([type, content]) => `
                    <div class="border rounded p-2 mb-1 small ai-variant" style="cursor:pointer;">
                        <span class="badge bg-${badgeMap[type] || 'secondary'} mb-1" style="font-size:9px;">${type}</span>
                        <div style="white-space:pre-wrap;">${esc(content)}</div>
                        <button type="button" class="btn btn-xs btn-outline-primary mt-1 use-variant-btn" data-content="${esc(content).replace(/"/g, '&quot;')}" style="font-size:10px;padding:1px 6px;">გამოყენება</button>
                    </div>
                `).join('');

                resultsEl.querySelectorAll('.use-variant-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const msgEl = $('#sd-form-message');
                        if (msgEl) {
                            msgEl.value = btn.dataset.content;
                            if (charCount) charCount.textContent = msgEl.value.length + ' სიმბოლო';
                        }
                        if (window.AdminHelpers) window.AdminHelpers.showToast('ტექსტი დაკოპირდა', 'success');
                    });
                });
            }
        } catch (_) {
            if (window.AdminHelpers) window.AdminHelpers.showToast('AI გენერაცია ვერ მოხერხდა', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            if (window.feather) window.feather.replace();
        }
    });

    // Hashtag suggestions
    $('#sd-form-hashtag-btn')?.addEventListener('click', async () => {
        const msgEl = $('#sd-form-message');
        const chipsEl = $('#sd-hashtag-chips');
        if (!msgEl || !chipsEl) return;

        chipsEl.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        try {
            const res = await axios.post(cfg.suggestHashtagsUrl, {
                message: msgEl.value,
                product_id: $('#sd-form-product')?.value || null,
            });

            const hashtags = res.data.hashtags || [];
            if (hashtags.length === 0) {
                chipsEl.innerHTML = '<span class="text-muted small">ჰეშთეგები ვერ მოიძებნა</span>';
                return;
            }

            chipsEl.innerHTML = hashtags.map(tag => {
                const safeTag = tag.startsWith('#') ? tag : '#' + tag;
                return `<button type="button" class="btn btn-outline-secondary btn-sm hashtag-chip" style="font-size:11px;padding:2px 7px;" data-tag="${esc(safeTag)}">${esc(safeTag)}</button>`;
            }).join('');

            chipsEl.querySelectorAll('.hashtag-chip').forEach(chip => {
                chip.addEventListener('click', () => {
                    const msgEl = $('#sd-form-message');
                    if (msgEl) {
                        msgEl.value = (msgEl.value.trimEnd() + ' ' + chip.dataset.tag).trim();
                        if (charCount) charCount.textContent = msgEl.value.length + ' სიმბოლო';
                    }
                    chip.classList.toggle('btn-outline-secondary');
                    chip.classList.toggle('btn-secondary');
                });
            });
        } catch (_) {
            chipsEl.innerHTML = '';
        }
    });

    // Form submit
    const form = $('#sd-post-form');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (postFormSubmitting) {
                return;
            }

            const action = e.submitter?.value || 'draft';
            postFormSubmitting = true;

            try {
                await submitPostForm(form, postId, action);
            } finally {
                postFormSubmitting = false;
            }
        });
    }
}

async function submitPostForm(form, postId, action) {
    const messageEl = $('#sd-form-message');
    const message = messageEl?.value?.trim();
    if (!message) {
        if (window.AdminHelpers) window.AdminHelpers.showToast('ტექსტი სავალდებულოა', 'warning');
        return;
    }

    const scheduledAt = $('#sd-form-scheduled-at')?.value || '';
    if (action === 'schedule' && !scheduledAt) {
        if (window.AdminHelpers) window.AdminHelpers.showToast('განრიგისთვის თარიღი სავალდებულოა', 'warning');
        return;
    }

    const payload = new URLSearchParams();
    payload.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
    payload.append('message', message);
    payload.append('product_id', $('#sd-form-product')?.value || '');
    payload.append('image_url', $('#sd-form-image-url')?.value || '');
    payload.append('media_type', $('#sd-form-media-type')?.value || 'none');
    payload.append('action', action === 'schedule' ? 'draft' : action);
    if (action === 'schedule') payload.append('scheduled_at', scheduledAt);
    if ($('#sd-form-fb')?.checked) payload.append('post_to_facebook', '1');
    if ($('#sd-form-ig')?.checked) payload.append('post_to_instagram', '1');

    if (postId) {
        payload.append('_method', 'PUT');
    }

    try {
        const url = postId
            ? cfg.postUpdateUrl.replace('{id}', postId)
            : cfg.postStoreUrl;

        const res = await axios.post(url, payload, {
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        });

        if (res.data?.success !== false) {
            closePostPanel();

            const label = action === 'schedule' ? 'განრიგში დაემატა' : (action === 'publish' ? 'გამოქვეყნდა' : 'შეინახა');
            if (window.AdminHelpers) window.AdminHelpers.showToast('პოსტი ' + label, 'success');

            await loadPosts();
            await loadOverview();
            if (action === 'schedule') await loadScheduled();
        } else {
            if (window.AdminHelpers) window.AdminHelpers.showToast(res.data?.message || 'შეცდომა', 'error');
        }
    } catch (e) {
        const msg = e.response?.data?.message || 'შეცდომა';
        if (window.AdminHelpers) window.AdminHelpers.showToast(msg, 'error');
    }
}

async function publishPost(postId) {
    if (!confirm('გამოქვეყნება?')) return;
    try {
        const url = cfg.postPublishUrl.replace('{id}', postId);
        await axios.post(url, { _token: document.querySelector('meta[name="csrf-token"]')?.content });
        if (window.AdminHelpers) window.AdminHelpers.showToast('გამოქვეყნდა!', 'success');
        await loadPosts();
        await loadScheduled();
        await loadOverview();
    } catch (e) {
        if (window.AdminHelpers) window.AdminHelpers.showToast('გამოქვეყნება ვერ მოხერხდა', 'error');
    }
}

async function deletePost(postId) {
    if (!confirm('წაიშლება? ეს ქმედება შეუქცევადია.')) return;
    try {
        const url = cfg.postDestroyUrl.replace('{id}', postId);
        await axios.delete(url, {
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
        });
        if (window.AdminHelpers) window.AdminHelpers.showToast('წაიშალა', 'success');
        await loadPosts();
        await loadOverview();
    } catch (e) {
        if (window.AdminHelpers) window.AdminHelpers.showToast('წაშლა ვერ მოხერხდა', 'error');
    }
}

// ── Init / Destroy ─────────────────────────────────────────────────────
export function initSocialDashboard() {
    const configEl = document.getElementById('sd-config');
    if (!configEl) return;

    cfg = JSON.parse(configEl.textContent);
    postsState = { page: 1 };
    commentsInitialized = false;
    postFormSubmitting = false;

    // Load overview immediately
    loadOverview();

    const prepared = preparePostPanel();
    if (prepared) {
        initPostForm();
    }

    $('#sd-post-panel-close')?.addEventListener('click', () => closePostPanel());

    // New post buttons
    const newPostBtns = ['#sd-new-post-btn', '#sd-posts-new-btn', '#sd-schedule-new-btn'];
    newPostBtns.forEach(sel => {
        $(sel)?.addEventListener('click', () => openPostPanel());
    });

    // Tab events
    $('#sd-tab-posts')?.addEventListener('shown.bs.tab', () => {
        loadPosts();
    });

    $('#sd-tab-comments')?.addEventListener('shown.bs.tab', () => {
        if (!commentsInitialized) {
            if (cfg.socialCommentsConfig) {
                const existingConfig = document.getElementById('sc-config');
                if (!existingConfig) {
                    const scriptEl = document.createElement('script');
                    scriptEl.id = 'sc-config';
                    scriptEl.type = 'application/json';
                    scriptEl.textContent = JSON.stringify(cfg.socialCommentsConfig);
                    document.body.appendChild(scriptEl);
                }
            }
            destroySocialComments();
            initSocialComments();
            commentsInitialized = true;
        }
    });

    $('#sd-tab-schedule')?.addEventListener('shown.bs.tab', () => {
        loadScheduled();
    });

    // Overview "all" buttons
    $('#sd-overview-all-posts-btn')?.addEventListener('click', () => {
        const tab = document.getElementById('sd-tab-posts');
        const bs = getBootstrap();
        if (tab && bs?.Tab) {
            bs.Tab.getOrCreateInstance(tab).show();
        } else {
            tab?.click();
        }
    });

    $('#sd-overview-all-comments-btn')?.addEventListener('click', () => {
        const tab = document.getElementById('sd-tab-comments');
        const bs = getBootstrap();
        if (tab && bs?.Tab) {
            bs.Tab.getOrCreateInstance(tab).show();
        } else {
            tab?.click();
        }
    });

    // Posts filters
    let searchTimer;
    $('#sd-posts-search')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { postsState.page = 1; loadPosts(); }, 300);
    });
    $('#sd-posts-filter-status')?.addEventListener('change', () => { postsState.page = 1; loadPosts(); });

    // Posts pagination
    $('#sd-posts-prev')?.addEventListener('click', () => { if (postsState.page > 1) { postsState.page--; loadPosts(); } });
    $('#sd-posts-next')?.addEventListener('click', () => { postsState.page++; loadPosts(); });

    // Posts table actions (delegated)
    const postsHandler = (e) => {
        const editBtn = e.target.closest('.sd-post-edit-btn');
        if (editBtn) { openPostPanel(parseInt(editBtn.dataset.id, 10)); return; }

        const publishBtn = e.target.closest('.sd-post-publish-btn');
        if (publishBtn) { publishPost(parseInt(publishBtn.dataset.id, 10)); return; }

        const deleteBtn = e.target.closest('.sd-post-delete-btn');
        if (deleteBtn) { deletePost(parseInt(deleteBtn.dataset.id, 10)); return; }
    };

    $('#sd-posts-tbody')?.addEventListener('click', postsHandler);
    $('#sd-schedule-tbody')?.addEventListener('click', postsHandler);
    $('#sd-recent-posts-tbody')?.addEventListener('click', postsHandler);

    if (window.feather) window.feather.replace();
}

export function destroySocialDashboard() {
    commentsInitialized = false;
    try { destroySocialComments(); } catch (_) {}
}
