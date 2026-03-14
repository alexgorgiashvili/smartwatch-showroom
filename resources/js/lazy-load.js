/**
 * Enhanced Lazy Loading with Intersection Observer
 * Optimized for SEO and performance
 */

(function() {
    'use strict';

    // Configuration
    const config = {
        rootMargin: '50px 0px',
        threshold: 0.01,
        loadingClass: 'lazy-loading',
        loadedClass: 'lazy-loaded',
        errorClass: 'lazy-error'
    };

    // Check for Intersection Observer support
    if (!('IntersectionObserver' in window)) {
        // Fallback: load all images immediately
        loadAllImages();
        return;
    }

    // Create Intersection Observer
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                loadImage(img);
                observer.unobserve(img);
            }
        });
    }, config);

    /**
     * Load a single image
     */
    function loadImage(img) {
        const src = img.dataset.src;
        const srcset = img.dataset.srcset;

        if (!src && !srcset) return;

        img.classList.add(config.loadingClass);

        // Create a new image to preload
        const tempImg = new Image();

        tempImg.onload = () => {
            // Set the actual src/srcset
            if (srcset) img.srcset = srcset;
            if (src) img.src = src;

            img.classList.remove(config.loadingClass);
            img.classList.add(config.loadedClass);

            // Remove data attributes
            delete img.dataset.src;
            delete img.dataset.srcset;
        };

        tempImg.onerror = () => {
            img.classList.remove(config.loadingClass);
            img.classList.add(config.errorClass);
            console.error('Failed to load image:', src);
        };

        // Start loading
        if (srcset) tempImg.srcset = srcset;
        if (src) tempImg.src = src;
    }

    /**
     * Fallback: load all images immediately
     */
    function loadAllImages() {
        document.querySelectorAll('img[data-src], img[data-srcset]').forEach(img => {
            if (img.dataset.src) img.src = img.dataset.src;
            if (img.dataset.srcset) img.srcset = img.dataset.srcset;
        });
    }

    /**
     * Initialize lazy loading
     */
    function init() {
        // Find all lazy images
        const lazyImages = document.querySelectorAll('img[data-src], img[data-srcset]');

        // Observe each image
        lazyImages.forEach(img => {
            imageObserver.observe(img);
        });

        // Also handle images added dynamically
        const mutationObserver = new MutationObserver(mutations => {
            mutations.forEach(mutation => {
                mutation.addedNodes.forEach(node => {
                    if (node.nodeType === 1) { // Element node
                        const lazyImgs = node.querySelectorAll ? 
                            node.querySelectorAll('img[data-src], img[data-srcset]') : [];
                        lazyImgs.forEach(img => imageObserver.observe(img));
                    }
                });
            });
        });

        mutationObserver.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose for manual triggering if needed
    window.lazyLoadImages = init;
})();
