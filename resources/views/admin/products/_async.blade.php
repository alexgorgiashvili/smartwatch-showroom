<script>
    document.addEventListener('DOMContentLoaded', () => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const messageContainer = document.getElementById('async-message');
        const toastElement = document.getElementById('async-toast');
        const toastBody = toastElement?.querySelector('.toast-body');
        const toast = toastElement && window.bootstrap ? new window.bootstrap.Toast(toastElement) : null;

        if (window.axios && csrfToken) {
            window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
            window.axios.defaults.headers.common['Accept'] = 'application/json';
        }

        const showMessage = (type, text) => {
            if (!messageContainer) {
                return;
            }

            messageContainer.innerHTML = `
                <div class="alert alert-${type}" role="alert">
                    ${text}
                </div>
            `;
        };

        const showToast = (type, text) => {
            if (!toastElement || !toastBody || !toast) {
                return;
            }

            toastElement.classList.remove('bg-success', 'bg-danger', 'bg-info', 'bg-dark');
            toastElement.classList.add(`bg-${type}`, 'text-white');
            toastBody.textContent = text;
            toast.show();
        };

        const setButtonLoading = (button, isLoading) => {
            if (!button) {
                return;
            }

            if (isLoading) {
                button.dataset.originalHtml = button.innerHTML;
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Loading';
                return;
            }

            if (button.dataset.originalHtml) {
                button.innerHTML = button.dataset.originalHtml;
            }
            button.disabled = false;
        };

        const clearFieldErrors = () => {
            const form = document.getElementById('product-form');
            if (!form) {
                return;
            }

            form.querySelectorAll('.is-invalid').forEach((input) => input.classList.remove('is-invalid'));
            form.querySelectorAll('[data-error-for]').forEach((el) => {
                el.textContent = '';
            });
        };

        const applyFieldErrors = (errors) => {
            const form = document.getElementById('product-form');
            if (!form) {
                return;
            }

            Object.entries(errors).forEach(([field, messages]) => {
                const baseField = field.includes('.') ? field.split('.')[0] : field;
                const input = form.querySelector(`[name="${field}"]`) || form.querySelector(`[name="${baseField}"]`) || form.querySelector(`[name="${baseField}[]"]`);
                if (input) {
                    input.classList.add('is-invalid');
                }

                const feedback = form.querySelector(`[data-error-for="${baseField}"]`) || form.querySelector(`[data-error-for="${field}"]`);
                if (feedback) {
                    feedback.textContent = Array.isArray(messages) ? messages[0] : messages;
                }
            });
        };

        const renderImages = (images) => {
            const grid = document.getElementById('product-images-grid');
            if (!grid) {
                return;
            }

            if (!images || images.length === 0) {
                grid.innerHTML = '<div class="col-12"><div class="text-muted">No images uploaded yet.</div></div>';
                return;
            }

            grid.innerHTML = images.map((image) => {
                const primaryBadge = image.is_primary
                    ? '<span class="badge bg-success position-absolute" style="top: 8px; left: 8px;">Primary</span>'
                    : '';
                const primaryButton = image.is_primary
                    ? ''
                    : `
                        <form method="POST" action="${image.primary_url}" data-async-action="primary">
                            <input type="hidden" name="_token" value="${csrfToken || ''}">
                            <button type="submit" class="btn btn-outline-primary btn-sm">Primary</button>
                        </form>
                    `;
                const altText = image.alt_en || 'No alt text';
                const fileName = image.path ? image.path.split('/').pop() : '';

                return `
                    <div class="col-sm-6 col-md-4 col-lg-3">
                        <div class="border rounded p-2 h-100">
                            <div class="position-relative">
                                <img src="${image.url}" alt="${altText}" class="rounded w-100" style="height: auto;">
                                ${primaryBadge}
                            </div>
                            <div class="mt-2">
                                <div class="fw-semibold small text-truncate">${fileName}</div>
                                <div class="text-muted small text-truncate">${altText}</div>
                            </div>
                            <div class="d-flex gap-2 mt-2">
                                ${primaryButton}
                                <form method="POST" action="${image.delete_url}" data-async-action="delete">
                                    <input type="hidden" name="_token" value="${csrfToken || ''}">
                                    <input type="hidden" name="_method" value="DELETE">
                                    <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        };

        const bindImageActions = (container) => {
            if (!container) {
                return;
            }

            container.addEventListener('submit', async (event) => {
                const form = event.target.closest('form');
                if (!form || !form.dataset.asyncAction) {
                    return;
                }

                event.preventDefault();

                const submitButton = form.querySelector('button[type="submit"]');
                setButtonLoading(submitButton, true);

                if (form.dataset.asyncAction === 'delete' && !confirm('Delete this image?')) {
                    return;
                }

                try {
                    const response = await window.axios.post(form.action, new FormData(form));
                    renderImages(response.data.images || []);
                    showMessage('success', response.data.message || 'Success.');
                    showToast('success', response.data.message || 'Success.');
                } catch (error) {
                    const message = error?.response?.data?.message || 'Request failed.';
                    showMessage('danger', message);
                    showToast('danger', message);
                } finally {
                    setButtonLoading(submitButton, false);
                }
            });
        };

        const uploadForm = document.getElementById('image-upload-form');
        if (uploadForm) {
            uploadForm.addEventListener('submit', async (event) => {
                event.preventDefault();

                const submitButton = uploadForm.querySelector('button[type="submit"]');
                setButtonLoading(submitButton, true);

                try {
                    const response = await window.axios.post(uploadForm.action, new FormData(uploadForm));
                    renderImages(response.data.images || []);
                    uploadForm.reset();
                    showMessage('success', response.data.message || 'Images uploaded.');
                    showToast('success', response.data.message || 'Images uploaded.');
                } catch (error) {
                    const message = error?.response?.data?.message || 'Upload failed.';
                    showMessage('danger', message);
                    showToast('danger', message);
                } finally {
                    setButtonLoading(submitButton, false);
                }
            });
        }

        const productForm = document.getElementById('product-form');
        if (productForm) {
            productForm.addEventListener('submit', async (event) => {
                event.preventDefault();

                const submitButton = productForm.querySelector('button[type="submit"]');
                setButtonLoading(submitButton, true);
                clearFieldErrors();

                try {
                    const response = await window.axios.post(productForm.action, new FormData(productForm));
                    showMessage('success', response.data.message || 'Saved successfully.');
                    showToast('success', response.data.message || 'Saved successfully.');
                    if (response.data.redirect) {
                        window.location.href = response.data.redirect;
                    }
                } catch (error) {
                    if (error?.response?.status === 422) {
                        const errors = error.response.data?.errors || {};
                        const errorList = Object.values(errors).flat().map((item) => `<li>${item}</li>`).join('');
                        showMessage('danger', `<ul class="mb-0">${errorList}</ul>`);
                        applyFieldErrors(errors);
                        showToast('danger', 'Please fix the highlighted fields.');
                        return;
                    }

                    const message = error?.response?.data?.message || 'Save failed.';
                    showMessage('danger', message);
                    showToast('danger', message);
                } finally {
                    setButtonLoading(submitButton, false);
                }
            });
        }

        bindImageActions(document.getElementById('product-images-grid'));
    });
</script>
