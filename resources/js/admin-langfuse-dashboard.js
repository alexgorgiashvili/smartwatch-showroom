function buildDashboardUrl(form) {
    const action = form.getAttribute('action') || window.location.pathname;
    const url = new URL(action, window.location.origin);
    const formData = new FormData(form);

    for (const [key, value] of formData.entries()) {
        if (value !== null && `${value}` !== '') {
            url.searchParams.set(key, value);
        }
    }

    url.searchParams.set('_refresh', `${Date.now()}`);

    return url.pathname + url.search;
}

export function initLangfuseDashboard() {
    const root = document.querySelector('#page-content [data-langfuse-dashboard], [data-langfuse-dashboard]');
    if (!root) {
        return;
    }

    const form = root.querySelector('[data-langfuse-dashboard-form]');
    const refreshButton = root.querySelector('[data-langfuse-dashboard-refresh]');

    if (!form) {
        return;
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault();

        const url = buildDashboardUrl(form);

        if (window.AdminRouter) {
            window.AdminRouter.navigate(url);
            return;
        }

        window.location.href = url;
    });

    if (refreshButton) {
        refreshButton.addEventListener('click', () => {
            const url = buildDashboardUrl(form);

            if (window.AdminRouter) {
                window.AdminRouter.navigate(url);
                return;
            }

            window.location.href = url;
        });
    }
}
