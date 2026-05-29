/**
 * Admin Entry Point
 * Bootstraps Axios + Echo, then initializes the PJAX router and global helpers.
 */

import './bootstrap';
import './admin-helpers';
import './admin-router';
import './admin-dashboard';
import './admin-products';
import './admin-orders';
import './admin-product-quality';
import { initLangfuseDashboard } from './admin-langfuse-dashboard';
import { initInbox, destroyInbox } from './admin-inbox';
import { initSocialComments, destroySocialComments } from './admin-social-comments';
import { initSocialDashboard, destroySocialDashboard } from './admin-social-dashboard';
import { initFacebookPostsForm } from './admin-facebook-posts';

function initAdminInboxBadgeRealtime() {
    if (window.__adminInboxBadgeRealtimeInitialized) {
        return;
    }

    const sidebarBadge = document.getElementById('sidebar-inbox-badge');
    if (!sidebarBadge || !window.Echo) {
        return;
    }

    const isLocalhostRuntime = ['127.0.0.1', 'localhost'].includes(window.location.hostname);
    const channel = isLocalhostRuntime
        ? window.Echo.channel('inbox')
        : window.Echo.private('inbox');

    if (!channel) {
        return;
    }

    window.__adminInboxBadgeRealtimeInitialized = true;

    channel
        .listen('.MessageReceived', (event) => {
            if (window.location.pathname.startsWith('/admin/inbox')) {
                return;
            }

            if (event?.message?.sender_type !== 'customer') {
                return;
            }

            const current = parseInt(sidebarBadge.dataset.unreadCount || '0', 10);
            const next = Number.isNaN(current) ? 1 : current + 1;
            sidebarBadge.textContent = `${next}`;
            sidebarBadge.dataset.unreadCount = `${next}`;
            sidebarBadge.classList.toggle('d-none', next <= 0);
        });
}

document.addEventListener('DOMContentLoaded', () => {
    // Initialize PJAX router
    if (window.AdminRouter) {
        window.AdminRouter.init();

        // Register page initializers for PJAX navigation
        window.AdminRouter.registerPage('/admin', () => {
            window.AdminDashboard && window.AdminDashboard.init();
        });
        window.AdminRouter.registerPage('/admin/products', () => {
            window.AdminProducts && window.AdminProducts.initIndex();
        });
        window.AdminRouter.registerPage('/admin/product-quality', () => {
            window.AdminProductQuality && window.AdminProductQuality.initIndex();
        });
        window.AdminRouter.registerPage('/admin/product-quality/create', () => {
            window.AdminProductQuality && window.AdminProductQuality.initCreate();
        });
        window.AdminRouter.registerPage('/admin/product-quality/*', () => {
            window.AdminProductQuality && window.AdminProductQuality.initShow();
        });
        window.AdminRouter.registerPage('/admin/products/create', () => {
            window.AdminProducts && window.AdminProducts.initForm();
            initFacebookPostsForm(); // Also init image manager
        });
        window.AdminRouter.registerPage('/admin/products/', () => {
            window.AdminProducts && window.AdminProducts.initForm();
            window.AdminProducts && window.AdminProducts.initEdit();
            initFacebookPostsForm(); // Also init image manager
        });
        window.AdminRouter.registerPage('/admin/articles/create', () => {
            initFacebookPostsForm(); // Init image manager
        });
        window.AdminRouter.registerPage('/admin/articles/', () => {
            initFacebookPostsForm(); // Init image manager
        });
        window.AdminRouter.registerPage('/admin/orders/create', () => {
            window.AdminOrders && window.AdminOrders.initCreate();
        });
        window.AdminRouter.registerPage('/admin/inbox', () => {
            destroyInbox();
            initInbox();
        });
        window.AdminRouter.registerPage('/admin/social-comments', () => {
            destroySocialComments();
            initSocialComments();
        });
        window.AdminRouter.registerPage('/admin/social-dashboard', () => {
            destroySocialDashboard();
            initSocialDashboard();
        });
        window.AdminRouter.registerPage('/admin/langfuse-dashboard', () => {
            initLangfuseDashboard();
        });
        window.AdminRouter.registerPage('/admin/facebook-posts/create', () => {
            initFacebookPostsForm();
        });
        window.AdminRouter.registerPage('/admin/facebook-posts/', () => {
            initFacebookPostsForm();
        });
    }

    initAdminInboxBadgeRealtime();

    // Initialize page JS on first full-page load
    const path = window.location.pathname.replace(/\/$/, '') || '/admin';
    if (path === '/admin') {
        window.AdminDashboard && window.AdminDashboard.init();
    } else if (path === '/admin/products') {
        window.AdminProducts && window.AdminProducts.initIndex();
    } else if (path === '/admin/product-quality') {
        window.AdminProductQuality && window.AdminProductQuality.initIndex();
    } else if (path === '/admin/product-quality/create') {
        window.AdminProductQuality && window.AdminProductQuality.initCreate();
    } else if (path.startsWith('/admin/product-quality/')) {
        window.AdminProductQuality && window.AdminProductQuality.initShow();
    } else if (path === '/admin/products/create') {
        window.AdminProducts && window.AdminProducts.initForm();
        initFacebookPostsForm(); // Init image manager
    } else if (path.startsWith('/admin/products/') && path.includes('/edit')) {
        window.AdminProducts && window.AdminProducts.initForm();
        window.AdminProducts && window.AdminProducts.initEdit();
        initFacebookPostsForm(); // Init image manager
    } else if (path === '/admin/articles/create' || (path.startsWith('/admin/articles/') && path.includes('/edit'))) {
        initFacebookPostsForm(); // Init image manager
    } else if (path === '/admin/orders/create') {
        window.AdminOrders && window.AdminOrders.initCreate();
    } else if (path === '/admin/inbox') {
        initInbox();
    } else if (path === '/admin/social-comments') {
        initSocialComments();
    } else if (path === '/admin/social-dashboard') {
        initSocialDashboard();
    } else if (path === '/admin/langfuse-dashboard') {
        initLangfuseDashboard();
    } else if (path.startsWith('/admin/facebook-posts/create') || (path.startsWith('/admin/facebook-posts/') && path.includes('/edit'))) {
        initFacebookPostsForm();
    }
});
