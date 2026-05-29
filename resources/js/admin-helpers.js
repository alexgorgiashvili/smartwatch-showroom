/**
 * Admin Helpers — Toast, validation, SweetAlert2 wrappers, form submission
 * Depends on: jQuery (core.js), Bootstrap (core.js), SweetAlert2 (vendor), Axios (bootstrap.js)
 */

const AdminHelpers = {
    /**
     * Show a Bootstrap toast notification
     * @param {string} message
     * @param {'success'|'danger'|'warning'|'info'} type
     */
    showToast(message, type = 'success') {
        const toastEl = document.getElementById('async-toast');
        if (!toastEl) return;

        const body = toastEl.querySelector('.toast-body');
        if (body) body.textContent = message;

        // Set background color based on type
        toastEl.className = 'toast align-items-center border-0 shadow-lg';
        const bgMap = {
            success: 'bg-success text-white',
            danger: 'bg-danger text-white',
            warning: 'bg-warning text-dark',
            info: 'bg-info text-white',
        };
        toastEl.classList.add(...(bgMap[type] || bgMap.success).split(' '));

        const toast = new bootstrap.Toast(toastEl, { delay: 3500 });
        toast.show();
    },

    /**
     * Display Laravel validation errors on a form
     * @param {Object} errors - { field: ['msg1', 'msg2'] }
     * @param {HTMLFormElement} form
     */
    showValidationErrors(errors, form) {
        AdminHelpers.clearValidationErrors(form);

        Object.entries(errors).forEach(([field, messages]) => {
            // Support dot-notation (e.g., items.0.variant_id → items[0][variant_id])
            const name = field.replace(/\.(\d+)\./g, '[$1].').replace(/\.([^.]+)/g, '[$1]');
            const input = form.querySelector(`[name="${field}"], [name="${name}"]`);
            if (!input) return;

            input.classList.add('is-invalid');

            // Find or create feedback element
            let feedback = input.parentElement.querySelector('.invalid-feedback');
            if (!feedback) {
                feedback = document.createElement('div');
                feedback.className = 'invalid-feedback';
                input.parentElement.appendChild(feedback);
            }
            feedback.textContent = messages[0];
            feedback.style.display = 'block';
        });

        // Scroll to first error
        const firstInvalid = form.querySelector('.is-invalid');
        if (firstInvalid) {
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstInvalid.focus();
        }
    },

    /**
     * Clear all validation errors from a form
     * @param {HTMLFormElement} form
     */
    clearValidationErrors(form) {
        form.querySelectorAll('.is-invalid').forEach((el) => {
            el.classList.remove('is-invalid');
        });
        form.querySelectorAll('.invalid-feedback').forEach((el) => {
            el.textContent = '';
            el.style.display = 'none';
        });
    },

    /**
     * Submit a form via Axios with spinner + validation + toast
     * @param {HTMLFormElement} form
     * @param {Object} options
     * @param {string}   [options.method]       - HTTP method override
     * @param {string}   [options.url]          - URL override (default: form.action)
     * @param {string}   [options.successMsg]   - Custom success message
     * @param {boolean}  [options.redirect]     - Follow redirect from response (default: true)
     * @param {Function} [options.onSuccess]    - Callback on success(data)
     * @param {Function} [options.onError]      - Callback on error(err)
     * @returns {Promise}
     */
    async submitForm(form, options = {}) {
        const btn = form.querySelector('[type="submit"]');
        const originalHtml = btn ? btn.innerHTML : '';

        AdminHelpers.clearValidationErrors(form);

        // Show spinner
        if (btn) {
            btn.disabled = true;
            btn.classList.add('btn-loading');
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';
        }

        const method = (options.method || form.method || 'POST').toUpperCase();
        const url = options.url || form.action;
        const formData = new FormData(form);

        // For PUT/PATCH, use POST with _method field (Laravel convention)
        if (method === 'PUT' || method === 'PATCH') {
            formData.set('_method', method);
        }

        try {
            const { data } = await axios.post(url, formData, {
                headers: { 'Accept': 'application/json' },
            });

            AdminHelpers.showToast(data.message || options.successMsg || 'Saved successfully!', 'success');

            if (options.onSuccess) {
                options.onSuccess(data);
            } else if (options.redirect !== false && data.redirect) {
                window.AdminRouter.navigate(data.redirect);
            }

            return data;
        } catch (err) {
            if (err.response?.status === 422 && err.response?.data?.errors) {
                AdminHelpers.showValidationErrors(err.response.data.errors, form);
                AdminHelpers.showToast(err.response.data.message || 'Please fix the errors below.', 'danger');
            } else {
                const msg = err.response?.data?.message || 'An error occurred. Please try again.';
                AdminHelpers.showToast(msg, 'danger');
            }

            if (options.onError) options.onError(err);
            throw err;
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.classList.remove('btn-loading');
                btn.innerHTML = originalHtml;
            }
        }
    },

    /**
     * Confirm and execute a DELETE action via SweetAlert2
     * @param {string} url
     * @param {Object} options
     * @param {string} [options.title]       - Confirm title
     * @param {string} [options.text]        - Confirm text
     * @param {string} [options.successMsg]  - Success message
     * @param {string} [options.redirect]    - Redirect URL after delete
     * @param {Function} [options.onSuccess] - Callback on success
     * @returns {Promise}
     */
    async confirmDelete(url, options = {}) {
        const result = await Swal.fire({
            title: options.title || 'Are you sure?',
            text: options.text || 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete',
            cancelButtonText: 'Cancel',
            reverseButtons: true,
        });

        if (!result.isConfirmed) return null;

        try {
            const { data } = await axios.delete(url, {
                headers: { 'Accept': 'application/json' },
            });

            AdminHelpers.showToast(data.message || options.successMsg || 'Deleted successfully!', 'success');

            if (options.onSuccess) {
                options.onSuccess(data);
            } else if (options.redirect) {
                window.AdminRouter.navigate(options.redirect);
            }

            return data;
        } catch (err) {
            AdminHelpers.showToast(err.response?.data?.message || 'Delete failed.', 'danger');
            throw err;
        }
    },

    /**
     * Generic confirm action via SweetAlert2
     * @param {Object} options
     * @param {string}  options.url           - Endpoint URL
     * @param {string}  [options.method]      - HTTP method (default: POST)
     * @param {Object}  [options.data]        - Request body
     * @param {string}  [options.title]       - Confirm title
     * @param {string}  [options.text]        - Confirm text
     * @param {string}  [options.icon]        - SweetAlert icon
     * @param {string}  [options.confirmText] - Confirm button text
     * @param {string}  [options.successMsg]  - Success message
     * @param {Function} [options.onSuccess]  - Callback
     * @returns {Promise}
     */
    async confirmAction(options = {}) {
        const result = await Swal.fire({
            title: options.title || 'Confirm',
            text: options.text || 'Are you sure?',
            icon: options.icon || 'question',
            showCancelButton: true,
            confirmButtonText: options.confirmText || 'Confirm',
            cancelButtonText: 'Cancel',
            reverseButtons: true,
        });

        if (!result.isConfirmed) return null;

        try {
            const method = (options.method || 'POST').toLowerCase();
            const { data } = await axios[method](options.url, options.data || {}, {
                headers: { 'Accept': 'application/json' },
            });

            AdminHelpers.showToast(data.message || options.successMsg || 'Action completed!', 'success');

            if (options.onSuccess) options.onSuccess(data);

            return data;
        } catch (err) {
            AdminHelpers.showToast(err.response?.data?.message || 'Action failed.', 'danger');
            throw err;
        }
    },
};

// Axios interceptor: handle 401 → redirect to login
axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            window.location.href = '/admin/login';
        }
        return Promise.reject(error);
    }
);

window.AdminHelpers = AdminHelpers;

// Global aliases for convenience
window.showToast = AdminHelpers.showToast;
window.confirmDelete = AdminHelpers.confirmDelete;
window.confirmAction = AdminHelpers.confirmAction;

export default AdminHelpers;
