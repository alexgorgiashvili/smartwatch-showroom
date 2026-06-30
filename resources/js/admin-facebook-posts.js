/**
 * Admin Facebook Posts
 */
const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

export function initFacebookPostsForm() {
    const btnGenerate = $('#btn-ai-generate');
    const hasAiComposer = !!btnGenerate;

    const messageInput = $('#message');
    const charCount = $('#message-char-count');
    const productId = $('#product_id');
    const language = $('#ai_language');
    const tone = $('#ai_tone');
    const mode = $('#ai_mode');
    const toneContainer = $('#ai_tone_container');
    const customDesc = $('#ai_description');
    const resultsContainer = $('#ai-results-container');
    const variantsList = $('#ai-variants-list');
    const aiPromptInput = $('#ai_prompt');
    const btnPreviewImage = $('#btn-preview-image');
    const imageUrl = $('#image_url');
    const mediaType = $('#media_type');
    const imageGroup = $('#image_url_group');
    const videoGroup = $('#video_url_group');
    const videoUrl = $('#video_url');
    const btnPreviewVideo = $('#btn-preview-video');
    const btnUploadVideo = $('#btn-upload-video');
    const videoUploadInput = $('#video-upload-input');
    const videoProgressContainer = $('#video-upload-progress-container');
    const videoProgressBar = $('#video-upload-progress-bar');

    // Char count update
    const updateCount = () => {
        if (messageInput && charCount) {
            charCount.textContent = `${messageInput.value.length} characters`;
        }
    };
    if (messageInput) {
        messageInput.addEventListener('input', updateCount);
        updateCount();
    }

    // Image preview
    if (btnPreviewImage && imageUrl) {
        btnPreviewImage.addEventListener('click', () => {
            const url = imageUrl.value.trim();
            if (url) {
                window.open(url, '_blank');
            } else {
                if (window.AdminHelpers) window.AdminHelpers.showToast('Please enter an image URL first', 'warning');
            }
        });
    }

    function updateMediaUI() {
        const type = mediaType?.value || 'none';
        if (imageGroup) imageGroup.classList.toggle('d-none', type !== 'image');
        if (videoGroup) videoGroup.classList.toggle('d-none', type !== 'video');
    }

    if (mediaType) {
        mediaType.addEventListener('change', updateMediaUI);
        updateMediaUI();
    }

    if (btnPreviewVideo && videoUrl) {
        btnPreviewVideo.addEventListener('click', () => {
            const url = videoUrl.value.trim();
            if (url) {
                window.open(url, '_blank');
            } else {
                if (window.AdminHelpers) window.AdminHelpers.showToast('Please enter a video URL first', 'warning');
            }
        });
    }

    if (btnUploadVideo && videoUploadInput) {
        btnUploadVideo.addEventListener('click', () => videoUploadInput.click());

        videoUploadInput.addEventListener('change', async function () {
            const file = this.files?.[0];
            if (!file) return;

            if (file.type !== 'video/mp4') {
                if (window.AdminHelpers) window.AdminHelpers.showToast('Invalid video format. Only MP4 allowed.', 'error');
                this.value = '';
                return;
            }

            if (file.size > 50 * 1024 * 1024) {
                if (window.AdminHelpers) window.AdminHelpers.showToast('Video is too large (max 50MB)', 'error');
                this.value = '';
                return;
            }

            const formData = new FormData();
            formData.append('video', file);

            if (btnUploadVideo) btnUploadVideo.disabled = true;
            if (videoProgressContainer) videoProgressContainer.classList.remove('d-none');
            if (videoProgressBar) videoProgressBar.style.width = '0%';

            try {
                const res = await axios.post('/admin/media/upload-video', formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                    onUploadProgress: (progressEvent) => {
                        if (!progressEvent.total) return;
                        const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                        if (videoProgressBar) videoProgressBar.style.width = percentCompleted + '%';
                    }
                });

                if (res.data?.success && videoUrl) {
                    videoUrl.value = res.data.url;
                    if (window.AdminHelpers) window.AdminHelpers.showToast('Video uploaded & applied!', 'success');
                } else {
                    if (window.AdminHelpers) window.AdminHelpers.showToast('Video upload failed', 'error');
                }
            } catch (e) {
                if (window.AdminHelpers) window.AdminHelpers.showToast('Video upload failed', 'error');
            } finally {
                if (btnUploadVideo) btnUploadVideo.disabled = false;
                if (videoProgressContainer) {
                    setTimeout(() => {
                        videoProgressContainer.classList.add('d-none');
                        if (videoProgressBar) videoProgressBar.style.width = '0%';
                    }, 800);
                }
                this.value = '';
            }
        });
    }

    // --- Image Manager Logic ---
    // Can be triggered from Facebook Posts OR Articles OR Products
    const btnImageManager = $('#btn-image-manager');
    const btnImageManagerArticle = $('#btn-image-manager-article');
    const btnImageManagerProduct = $('#btn-image-manager-product');

    if (btnImageManager || btnImageManagerArticle || btnImageManagerProduct) {
        const modalEl = document.getElementById('image-manager-modal');
        let imageModal = null;
        // Upload & Crop Elements
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

        let cropperInstance = null;

        if (modalEl) {
            imageModal = new bootstrap.Modal(modalEl);
        }

        const galleryGrid = $('#gallery-grid');
        const galleryEmpty = $('#gallery-empty');
        const galleryLoading = $('#gallery-loading');
        const btnSelectGalleryImage = $('#btn-select-gallery-image');

        // Gallery Filters
        const filterProduct = $('#gallery-filter-product');
        const filterTime = $('#gallery-filter-time');
        const btnGalleryRefresh = $('#btn-gallery-refresh');
        const loadMoreContainer = $('#gallery-load-more-container');
        const btnGalleryLoadMore = $('#btn-gallery-load-more');

        let activeProductId = productId?.value || null;
        if (!activeProductId) {
            const productDataEl = document.getElementById('product-data');
            if (productDataEl) {
                try {
                    const data = JSON.parse(productDataEl.textContent);
                    activeProductId = data.productId || data.id || null;
                } catch (e) {}
            }
        }

        let selectedImageUrl = null;
        let galleryCurrentPage = 1;
        let galleryHasMore = false;

        // Target input to update (can be Facebook post image or Article cover)
        let targetUrlInput = null;

        const openManager = (targetInput) => {
            targetUrlInput = targetInput;
            if (imageModal) {
                if (modalEl) modalEl.removeAttribute('inert');

                // Pre-select current product in filter if one is chosen (for FB posts)
                const selectedProductId = (productId && productId.value) || activeProductId;
                if (selectedProductId && filterProduct) {
                    filterProduct.value = selectedProductId;
                }

                imageModal.show();
            }
            galleryCurrentPage = 1;
            loadGallery(true);
        };

        if (btnImageManager) {
            btnImageManager.addEventListener('click', () => openManager(imageUrl));
        }

        if (btnImageManagerArticle) {
            const articleCoverInput = $('#cover_image');
            btnImageManagerArticle.addEventListener('click', () => openManager(articleCoverInput));
        }

        if (btnImageManagerProduct) {
            // For products, we don't have a specific input to update, we just use it for upload/crop
            // and trigger a page reload or gallery refresh after upload
            // If we are on the edit page, we can extract the ID from the URL or a data attribute
            let currentProdId = productId ? productId.value : null;
            if (!currentProdId && document.getElementById('product-data')) {
                try {
                    const data = JSON.parse(document.getElementById('product-data').textContent);
                    currentProdId = data.productId || data.id || null;
                } catch(e) {}
            }

            // Set the product ID so the cropper knows where to upload
            if (currentProdId) {
                activeProductId = currentProdId;
                if (productId) {
                    productId.value = currentProdId;
                }
            }

            btnImageManagerProduct.addEventListener('click', () => {
                targetUrlInput = 'product_gallery_refresh'; // Special flag
                openManager(null);
            });
        }

        if (modalEl) {
            modalEl.addEventListener('hidden.bs.modal', () => {
                modalEl.setAttribute('inert', '');
            });
        }

        // Gallery Filter Events
        if (btnGalleryRefresh) {
            btnGalleryRefresh.addEventListener('click', () => {
                galleryCurrentPage = 1;
                loadGallery(true);
            });
        }

        if (btnGalleryLoadMore) {
            btnGalleryLoadMore.addEventListener('click', () => {
                if (galleryHasMore) {
                    galleryCurrentPage++;
                    loadGallery(false);
                }
            });
        }

        // Load Gallery
        async function loadGallery(clearExisting = true) {
            if (clearExisting) {
                galleryEmpty.classList.add('d-none');
                galleryGrid.innerHTML = '';
                btnSelectGalleryImage.disabled = true;
                selectedImageUrl = null;
            }

            galleryLoading.classList.remove('d-none');
            if (loadMoreContainer) loadMoreContainer.classList.add('d-none');

            try {
                // Build query params
                const params = new URLSearchParams({
                    page: galleryCurrentPage
                });

                if (filterProduct && filterProduct.value) {
                    params.append('product_id', filterProduct.value);
                }

                if (filterTime && filterTime.value) {
                    params.append('time_filter', filterTime.value);
                }

                const res = await axios.get(`/admin/images/all-json?${params.toString()}`);
                galleryLoading.classList.add('d-none');

                if (res.data.images && res.data.images.length > 0) {
                    res.data.images.forEach(img => {
                        const col = document.createElement('div');
                        col.className = 'col-4 col-sm-3 col-md-2 mb-2';
                        col.innerHTML = `
                            <div class="card h-100 cursor-pointer gallery-item border position-relative" data-url="${img.url}">
                                <img src="${img.thumbnail_url || img.url}" class="card-img-top" style="object-fit: cover; height: 100px;" alt="Product Image" loading="lazy">
                                <div class="position-absolute bottom-0 start-0 w-100 p-1" style="background: rgba(0,0,0,0.6); backdrop-filter: blur(2px);">
                                    <div class="text-white text-truncate" style="font-size: 0.65rem;" title="${img.product_name}">${img.product_name}</div>
                                </div>
                            </div>
                        `;

                        col.querySelector('.gallery-item').addEventListener('click', function() {
                            $$('.gallery-item').forEach(el => {
                                el.classList.remove('border-primary', 'border-3');
                                el.classList.add('border');
                            });
                            this.classList.remove('border');
                            this.classList.add('border-primary', 'border-3');
                            selectedImageUrl = this.dataset.url;
                            btnSelectGalleryImage.disabled = false;
                        });

                        galleryGrid.appendChild(col);
                    });

                    // Handle pagination state
                    galleryHasMore = res.data.current_page < res.data.last_page;
                    if (galleryHasMore && loadMoreContainer) {
                        loadMoreContainer.classList.remove('d-none');
                    }

                } else if (clearExisting) {
                    galleryEmpty.textContent = 'No images found with the current filters.';
                    galleryEmpty.classList.remove('d-none');
                }
            } catch (error) {
                console.error(error);
                galleryLoading.classList.add('d-none');
                if (clearExisting) {
                    galleryEmpty.textContent = 'Failed to load images.';
                    galleryEmpty.classList.remove('d-none');
                }
            }
        }

        // Use Selected Gallery Image
        btnSelectGalleryImage.addEventListener('click', () => {
            if (targetUrlInput === 'product_gallery_refresh') {
                if (window.AdminHelpers) window.AdminHelpers.showToast('Image selected from gallery. (Product gallery requires page reload)', 'info');
                imageModal.hide();
                window.location.reload();
                return;
            }

            if (selectedImageUrl && targetUrlInput) {
                targetUrlInput.value = selectedImageUrl;
                if (window.AdminHelpers) window.AdminHelpers.showToast('Image URL updated', 'success');
                imageModal.hide();
            }
        });

        // Refresh gallery if product changes while modal is open (rare, but good practice)
        if (productId) {
            productId.addEventListener('change', () => {
                if (modalEl && modalEl.classList.contains('show')) {
                    loadGallery();
                }
            });
        }

        // Drag & Drop Upload
        if (uploadZone && fileInput) {
            uploadZone.addEventListener('click', () => fileInput.click());

            uploadZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadZone.classList.add('bg-secondary', 'bg-opacity-10');
            });

            uploadZone.addEventListener('dragleave', () => {
                uploadZone.classList.remove('bg-secondary', 'bg-opacity-10');
            });

            uploadZone.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadZone.classList.remove('bg-secondary', 'bg-opacity-10');
                if (e.dataTransfer.files.length) {
                    handleFileSelection(e.dataTransfer.files[0]);
                }
            });

            fileInput.addEventListener('change', function() {
                if (this.files.length) {
                    handleFileSelection(this.files[0]);
                }
            });
        }

        function handleFileSelection(file) {
            if (!file.type.match(/^image\/(jpeg|png|webp)$/)) {
                if (window.AdminHelpers) window.AdminHelpers.showToast('Invalid file format. Only JPG, PNG, WEBP allowed.', 'error');
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                if (window.AdminHelpers) window.AdminHelpers.showToast('File is too large (max 5MB)', 'error');
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                uploadZone.classList.add('d-none');
                cropperContainer.classList.remove('d-none');
                cropperControls.classList.remove('d-none');

                // Clear the onload handler just in case it fires multiple times
                cropperImage.onload = null;

                cropperImage.src = e.target.result;

                // Initialize cropper after a short delay to ensure DOM is ready
                setTimeout(() => {
                    initCropper();
                }, 100);
            };
            reader.readAsDataURL(file);
        }

        function initCropper() {
            console.log('[Cropper] initCropper called');
            if (cropperInstance) {
                try {
                    console.log('[Cropper] Destroying previous instance');
                    cropperInstance.destroy();
                } catch (e) {
                    console.warn('[Cropper] Error destroying cropper instance', e);
                }
                cropperInstance = null;
            }

            // Explicitly verify the element exists and is an image
            if (!cropperImage || cropperImage.tagName !== 'IMG') {
                console.error('[Cropper] Target is not a valid image element:', cropperImage);
                return;
            }

            console.log('[Cropper] Creating new instance for src:', cropperImage.src.substring(0, 100) + '...');
            try {
                // IMPORTANT: We use window.Cropper because importing it directly via ES modules
                // in Laravel Vite sometimes causes the class to be undefined or wrapped incorrectly.
                // It's loaded via CDN in the layout file.
                if (typeof window.Cropper !== 'function') {
                    console.error('[Cropper] window.Cropper is not defined. Ensure CDN is loaded.');
                    return;
                }

                cropperInstance = new window.Cropper(cropperImage, {
                    aspectRatio: NaN,
                    viewMode: 1,
                    background: false,
                    zoomable: true,
                    responsive: true,
                    ready() {
                        console.log('[Cropper] Instance is ready!');
                        // Enable buttons only when cropper is fully ready
                        if (btnCropSave) btnCropSave.disabled = false;
                    }
                });
            } catch (error) {
                console.error('[Cropper] Failed to initialize Cropper.js', error);
                if (window.AdminHelpers) window.AdminHelpers.showToast('Failed to initialize image editor', 'error');
            }
        }

        // Cropper Controls with safety checks
        if (cropperRatio) {
            cropperRatio.addEventListener('change', function() {
                if (cropperInstance && typeof cropperInstance.setAspectRatio === 'function') {
                    try {
                        cropperInstance.setAspectRatio(parseFloat(this.value));
                    } catch (e) { console.error('Cropper error', e); }
                }
            });
        }
        $('#btn-crop-rotate-left')?.addEventListener('click', () => {
            if (cropperInstance && typeof cropperInstance.rotate === 'function') {
                try { cropperInstance.rotate(-90); } catch (e) {}
            }
        });
        $('#btn-crop-rotate-right')?.addEventListener('click', () => {
            if (cropperInstance && typeof cropperInstance.rotate === 'function') {
                try { cropperInstance.rotate(90); } catch (e) {}
            }
        });

        let flipX = 1;
        let flipY = 1;
        $('#btn-crop-flip-h')?.addEventListener('click', () => {
            flipX = flipX === 1 ? -1 : 1;
            if (cropperInstance && typeof cropperInstance.scaleX === 'function') {
                try { cropperInstance.scaleX(flipX); } catch (e) {}
            }
        });
        $('#btn-crop-flip-v')?.addEventListener('click', () => {
            flipY = flipY === 1 ? -1 : 1;
            if (cropperInstance && typeof cropperInstance.scaleY === 'function') {
                try { cropperInstance.scaleY(flipY); } catch (e) {}
            }
        });

        // Cancel Crop
        btnCropCancel?.addEventListener('click', () => {
            if (cropperInstance) {
                try { cropperInstance.destroy(); } catch (e) {}
                cropperInstance = null;
            }
            cropperImage.src = '';
            cropperContainer.classList.add('d-none');
            cropperControls.classList.add('d-none');
            uploadZone.classList.remove('d-none');
            fileInput.value = '';
        });

        // Save & Upload Crop
        btnCropSave?.addEventListener('click', () => {
            console.log('[Cropper] Save button clicked');

            if (!cropperInstance) {
                console.error('[Cropper] Save failed: cropperInstance is null');
                if (window.AdminHelpers) window.AdminHelpers.showToast('Please wait for image to load', 'warning');
                return;
            }

            if (typeof cropperInstance.getCroppedCanvas !== 'function') {
                console.error('[Cropper] Save failed: getCroppedCanvas is not a function on the instance', cropperInstance);
                if (window.AdminHelpers) window.AdminHelpers.showToast('Image editor is not fully initialized yet', 'warning');
                return;
            }

            btnCropSave.disabled = true;
            btnCropCancel.disabled = true;
            progressBarContainer.classList.remove('d-none');
            progressBar.style.width = '0%';

            let canvas;
            try {
                console.log('[Cropper] Attempting to get cropped canvas...');
                canvas = cropperInstance.getCroppedCanvas({
                    maxWidth: 2048,
                    maxHeight: 2048
                });
                console.log('[Cropper] Canvas obtained:', !!canvas);
            } catch (e) {
                console.error('[Cropper] Error getting cropped canvas:', e);
            }

            if (!canvas) {
                console.error('[Cropper] Could not get cropped canvas (returned null/undefined)');
                btnCropSave.disabled = false;
                btnCropCancel.disabled = false;
                if (window.AdminHelpers) window.AdminHelpers.showToast('Failed to process image. Try again.', 'error');
                return;
            }

            canvas.toBlob(async (blob) => {
                const formData = new FormData();
                formData.append('image', blob, 'cropped_image.jpg');
                const selectedProductId = (productId && productId.value) || activeProductId;
                if (selectedProductId) {
                    formData.append('product_id', selectedProductId);
                }

                try {
                    const res = await axios.post('/admin/images/upload-standalone', formData, {
                        headers: { 'Content-Type': 'multipart/form-data' },
                        onUploadProgress: (progressEvent) => {
                            const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                            progressBar.style.width = percentCompleted + '%';
                        }
                    });

                    if (res.data.success) {
                        if (targetUrlInput === 'product_gallery_refresh') {
                            if (window.AdminHelpers) window.AdminHelpers.showToast('Image uploaded & added to product!', 'success');
                            setTimeout(() => window.location.reload(), 1000);
                        } else if (targetUrlInput) {
                            targetUrlInput.value = res.data.url;
                            if (window.AdminHelpers) window.AdminHelpers.showToast('Image uploaded & applied!', 'success');
                        }

                        // Switch back to gallery tab and reload
                        const triggerEl = document.querySelector('#gallery-tab');
                        if (triggerEl) bootstrap.Tab.getOrCreateInstance(triggerEl).show();

                        // Select current product in filter to show newly uploaded image
                        const selectedProductId = (productId && productId.value) || activeProductId;
                        if (selectedProductId && filterProduct) {
                            filterProduct.value = selectedProductId;
                        }

                        galleryCurrentPage = 1;
                        loadGallery(true);

                        // Reset crop UI
                        btnCropCancel.click();
                        imageModal.hide();
                    }
                } catch (error) {
                    console.error(error);
                    if (window.AdminHelpers) window.AdminHelpers.showToast('Upload failed', 'error');
                } finally {
                    btnCropSave.disabled = false;
                    btnCropCancel.disabled = false;
                    setTimeout(() => {
                        progressBarContainer.classList.add('d-none');
                        progressBar.style.width = '0%';
                    }, 1000);
                }
            }, 'image/jpeg', 0.85);
        });
    }
    // --- End Image Manager Logic ---

    if (!hasAiComposer) {
        return;
    }

    // Toggle Tone visibility based on mode
    if (mode && toneContainer) {
        mode.addEventListener('change', () => {
            if (mode.value === 'autonomous') {
                toneContainer.style.display = 'none';
            } else {
                toneContainer.style.display = 'block';
            }
        });
    }

    // Enhance Prompt
    const btnEnhance = $('#btn-enhance-prompt');
    const enhanceFeedback = $('#enhance-feedback');
    if (btnEnhance && customDesc) {
        btnEnhance.addEventListener('click', async () => {
            const promptText = customDesc.value.trim();
            if (!promptText) {
                if (window.AdminHelpers) window.AdminHelpers.showToast('Please enter some instructions to enhance', 'warning');
                return;
            }

            const originalHtml = btnEnhance.innerHTML;
            btnEnhance.disabled = true;
            btnEnhance.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="width:10px;height:10px;"></span> Enhancing...';
            enhanceFeedback.classList.add('d-none');

            try {
                const res = await axios.post('/admin/facebook-posts/enhance-prompt', {
                    prompt: promptText,
                    tone: tone.value
                });

                if (res.data.success) {
                    customDesc.value = res.data.enhanced_prompt;

                    enhanceFeedback.innerHTML = `Enhanced! <span class="text-muted" style="font-size:10px;cursor:help;" title="${res.data.metadata.grammar_corrections?.join(', ') || 'Optimized'}">(Hover for details)</span>`;
                    enhanceFeedback.classList.remove('d-none');
                    setTimeout(() => enhanceFeedback.classList.add('d-none'), 5000);

                    if (window.AdminHelpers) window.AdminHelpers.showToast('Prompt enhanced successfully', 'success');
                } else {
                    if (window.AdminHelpers) window.AdminHelpers.showToast(res.data.error || 'Failed to enhance', 'error');
                }
            } catch (error) {
                console.error(error);
                if (window.AdminHelpers) window.AdminHelpers.showToast('Error enhancing prompt', 'error');
            } finally {
                btnEnhance.disabled = false;
                btnEnhance.innerHTML = originalHtml;
                if (window.feather) window.feather.replace();
            }
        });
    }

    // Generate AI
    btnGenerate.addEventListener('click', async () => {
        btnGenerate.disabled = true;
        btnGenerate.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Generating...';
        resultsContainer.classList.add('d-none');
        variantsList.innerHTML = '';

        try {
                const res = await axios.post('/admin/facebook-posts/generate', {
                    product_id: productId.value || null,
                    language: language.value,
                    tone: tone.value,
                    mode: mode ? mode.value : 'custom',
                    description: customDesc.value || null
                });

            if (res.data.success) {
                if (res.data.variants) {
                    // It's the 3 variants structure
                    const vars = res.data.variants;
                    renderVariants(vars, res.data.prompt);
                } else if (res.data.content) {
                    // Single variant fallback
                    renderVariants({ 'Generated': res.data.content }, res.data.prompt);
                }

                if (res.data.image_url && !imageUrl.value) {
                    imageUrl.value = res.data.image_url;
                }

                if (window.AdminHelpers) window.AdminHelpers.showToast('AI content generated successfully', 'success');
            } else {
                if (window.AdminHelpers) window.AdminHelpers.showToast(res.data.error || 'Failed to generate', 'error');
            }
        } catch (error) {
            console.error(error);
            if (window.AdminHelpers) window.AdminHelpers.showToast('Error connecting to AI service', 'error');
        } finally {
            btnGenerate.disabled = false;
            btnGenerate.innerHTML = '<i data-feather="cpu" style="width:14px;height:14px;"></i> Generate 3 Variants';
            if (window.feather) window.feather.replace();
        }
    });

    function renderVariants(variants, prompt) {
        resultsContainer.classList.remove('d-none');
        Object.entries(variants).forEach(([type, content]) => {
            const card = document.createElement('div');
            card.className = 'card border shadow-none cursor-pointer p-2 ai-variant-card';
            card.style.transition = 'all 0.2s ease';

            const badgeMap = { 'entertaining': 'warning', 'sales': 'danger', 'informational': 'info', 'Generated': 'primary' };
            const badgeClass = badgeMap[type.toLowerCase()] || 'primary';

            card.innerHTML = `
                <div class="d-flex justify-content-between mb-1">
                    <span class="badge bg-${badgeClass} text-uppercase" style="font-size:10px;">${type}</span>
                    <button type="button" class="btn btn-xs btn-outline-secondary p-0 px-1 use-variant-btn" title="Use this text">Use</button>
                </div>
                <div class="small" style="white-space: pre-wrap;">${content}</div>
            `;

            // Hover effect
            card.addEventListener('mouseenter', () => card.classList.add('bg-light'));
            card.addEventListener('mouseleave', () => card.classList.remove('bg-light'));

            // Click to use
            const btnUse = card.querySelector('.use-variant-btn');
            btnUse.addEventListener('click', (e) => {
                e.stopPropagation();
                messageInput.value = content;
                updateCount();
                if (aiPromptInput) aiPromptInput.value = prompt || '';

                // Highlight selected
                $$('.ai-variant-card').forEach(c => c.classList.remove('border-primary'));
                card.classList.add('border-primary');

                if (window.AdminHelpers) window.AdminHelpers.showToast('Text copied to message', 'success');
            });

            variantsList.appendChild(card);
        });
    }

    // Hashtag suggestions
    const btnHashtags = $('#btn-suggest-hashtags');
    const hashtagChips = $('#hashtag-chips');
    if (btnHashtags && hashtagChips) {
        btnHashtags.addEventListener('click', async () => {
            const msgVal = messageInput?.value || '';
            const prodId = productId?.value || null;

            if (!msgVal.trim() && !prodId) {
                if (window.AdminHelpers) window.AdminHelpers.showToast('ჯერ ჩაწერეთ ტექსტი ან აარჩიეთ პროდუქტი', 'warning');
                return;
            }

            btnHashtags.disabled = true;
            hashtagChips.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            try {
                const res = await axios.post('/admin/facebook-posts/suggest-hashtags', {
                    message: msgVal,
                    product_id: prodId || null,
                });

                const hashtags = res.data.hashtags || [];
                if (!hashtags.length) {
                    hashtagChips.innerHTML = '<span class="text-muted small">ჰეშთეგები ვერ მოიძებნა</span>';
                    return;
                }

                hashtagChips.innerHTML = hashtags.map(tag => {
                    const safeTag = tag.startsWith('#') ? tag : '#' + tag;
                    return `<button type="button" class="btn btn-outline-secondary hashtag-chip" style="font-size:11px;padding:2px 7px;" data-tag="${safeTag.replace(/"/g, '&quot;')}">${safeTag.replace(/</g, '&lt;')}</button>`;
                }).join('');

                hashtagChips.querySelectorAll('.hashtag-chip').forEach(chip => {
                    chip.addEventListener('click', () => {
                        if (messageInput) {
                            messageInput.value = (messageInput.value.trimEnd() + ' ' + chip.dataset.tag).trim();
                            updateCount();
                        }
                        chip.classList.toggle('btn-outline-secondary');
                        chip.classList.toggle('btn-secondary');
                    });
                });
            } catch (_) {
                hashtagChips.innerHTML = '';
                if (window.AdminHelpers) window.AdminHelpers.showToast('ჰეშთეგების გენერაცია ვერ მოხერხდა', 'error');
            } finally {
                btnHashtags.disabled = false;
            }
        });
    }

    // Async form submission logic
    const form = btnGenerate.closest('form');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const btnDraft = $('#btn-save-draft');
            const btnSchedule = $('#btn-schedule-post');
            const btnPublish = $('#btn-publish-now');

            const action = e.submitter ? e.submitter.value : 'draft';

            // Just disable buttons to prevent double submission and show feedback
            if (action === 'draft') {
                if (btnDraft) {
                    btnDraft.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';
                    btnDraft.disabled = true;
                    if (btnPublish) btnPublish.disabled = true;
                }
            } else {
                if (btnPublish) {
                    btnPublish.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Publishing...';
                    btnPublish.disabled = true;
                    if (btnDraft) btnDraft.disabled = true;
                }
            }

            const formData = new FormData(form);
            formData.append('action', action);

            try {
                const res = await axios.post(form.action, formData, {
                    headers: { 'Accept': 'application/json' }
                });

                if (res.data.success) {
                    if (window.AdminHelpers) window.AdminHelpers.showToast(res.data.message || 'Success', 'success');
                    if (res.data.redirect) {
                        setTimeout(() => {
                            if (window.AdminRouter) {
                                window.AdminRouter.navigate(res.data.redirect);
                            } else {
                                window.location.href = res.data.redirect;
                            }
                        }, 1000);
                    }
                } else {
                    if (window.AdminHelpers) window.AdminHelpers.showToast(res.data.error || 'Operation failed', 'error');
                    resetButtons();
                }
            } catch (error) {
                console.error(error);
                let msg = 'An error occurred';
                if (error.response?.data?.message) {
                    msg = error.response.data.message;
                }
                if (window.AdminHelpers) window.AdminHelpers.showToast(msg, 'error');
                resetButtons();
            }

            function resetButtons() {
                if (btnDraft) {
                    btnDraft.innerHTML = '<i data-feather="file-text" style="width:16px;height:16px;"></i> Save as Draft';
                    btnDraft.disabled = false;
                }
                if (btnPublish) {
                    btnPublish.innerHTML = '<i data-feather="send" style="width:16px;height:16px;"></i> Publish Now';
                    btnPublish.disabled = false;
                }
                if (window.feather) window.feather.replace();
            }
        });
    }
}
