/**
 * Admin Product Quality — mode toggles and tab restoration for PJAX pages
 */

const AdminProductQuality = {
    initIndex() {
        // No index-specific JS yet.
    },

    initCreate() {
        const modeInputs = document.querySelectorAll('input[name="mode"]');
        const catalogBlock = document.getElementById('catalog-mode-fields');
        const adHocBlock = document.getElementById('ad-hoc-mode-fields');
        const productSelect = document.getElementById('catalog_product_id');

        const syncMode = () => {
            const mode = document.querySelector('input[name="mode"]:checked')?.value || 'catalog';
            if (catalogBlock) catalogBlock.classList.toggle('d-none', mode !== 'catalog');
            if (adHocBlock) adHocBlock.classList.toggle('d-none', mode !== 'ad_hoc');
        };

        modeInputs.forEach((input) => input.addEventListener('change', syncMode));
        syncMode();

        if (productSelect) {
            productSelect.addEventListener('change', () => {
                const selected = productSelect.options[productSelect.selectedIndex];
                if (!selected) return;

                const sourceUrl = document.getElementById('source_url');
                const externalSource = document.getElementById('external_source');
                const externalProductId = document.getElementById('external_product_id');

                if (sourceUrl && !sourceUrl.value) {
                    sourceUrl.value = selected.dataset.sourceUrl || '';
                }

                if (externalSource && !externalSource.value) {
                    externalSource.value = selected.dataset.externalSource || '';
                }

                if (externalProductId && !externalProductId.value) {
                    externalProductId.value = selected.dataset.externalProductId || '';
                }
            });
        }
    },

    initShow() {
        const hash = window.location.hash;

        if (hash) {
            const tabTrigger = document.querySelector(`[data-bs-target="${hash}"]`);
            if (tabTrigger && window.bootstrap?.Tab) {
                window.bootstrap.Tab.getOrCreateInstance(tabTrigger).show();
            }
        }

        document.querySelectorAll('#product-quality-tabs [data-bs-toggle="tab"]').forEach((button) => {
            button.addEventListener('shown.bs.tab', (event) => {
                const target = event.target.getAttribute('data-bs-target');
                if (target) {
                    history.replaceState(null, '', `${window.location.pathname}${window.location.search}${target}`);
                }
            });
        });
    },
};

window.AdminProductQuality = AdminProductQuality;

export default AdminProductQuality;
