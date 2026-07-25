/**
 * Admin Orders — Create form item management, price calculation
 */

const AdminOrders = {

    initCreate() {
        const rows = document.querySelectorAll('#orderItemsContainer .order-item-row');
        this._itemIndex = rows.length;
        this._bindAddItem();
        this._bindItemChanges();
        this._bindRemoveItem();
        this._updatePrices();
    },

    _itemIndex: 1,

    _bindAddItem() {
        const btn = document.getElementById('btnAddItem');
        if (!btn) return;

        btn.addEventListener('click', () => {
            const container = document.getElementById('orderItemsContainer');
            if (!container) return;

            const firstRow = container.querySelector('.order-item-row');
            if (!firstRow) return;

            const clone = firstRow.cloneNode(true);
            clone.dataset.index = this._itemIndex;

            // Update names
            clone.querySelectorAll('[name]').forEach(el => {
                el.name = el.name.replace(/\[\d+\]/, `[${this._itemIndex}]`);
            });

            // Reset values
            const select = clone.querySelector('.variant-select');
            if (select) {
                select.value = '';
                select.classList.remove('is-invalid');
            }
            const qty = clone.querySelector('.item-qty');
            if (qty) {
                qty.value = 1;
                qty.classList.remove('is-invalid');
            }
            clone.querySelectorAll('.invalid-feedback').forEach(el => el.remove());

            // Reset price/subtotal displays
            const price = clone.querySelector('.item-price');
            if (price) price.textContent = '—';
            const subtotal = clone.querySelector('.item-subtotal');
            if (subtotal) subtotal.textContent = '—';

            // Show remove button
            const removeBtn = clone.querySelector('.btn-remove-item');
            if (removeBtn) removeBtn.style.visibility = 'visible';

            container.appendChild(clone);
            this._itemIndex++;

            // Show remove on first row if multiple items
            if (container.querySelectorAll('.order-item-row').length > 1) {
                const firstRemove = firstRow.querySelector('.btn-remove-item');
                if (firstRemove) firstRemove.style.visibility = 'visible';
            }

            if (typeof feather !== 'undefined') feather.replace();
        });
    },

    _bindItemChanges() {
        const container = document.getElementById('orderItemsContainer');
        if (!container) return;

        container.addEventListener('change', (e) => {
            if (e.target.classList.contains('variant-select') || e.target.classList.contains('item-qty')) {
                this._updatePrices();
            }
        });

        container.addEventListener('input', (e) => {
            if (e.target.classList.contains('item-qty')) {
                this._updatePrices();
            }
        });
    },

    _bindRemoveItem() {
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-remove-item');
            if (!btn) return;

            const row = btn.closest('.order-item-row');
            const container = document.getElementById('orderItemsContainer');
            if (!row || !container) return;

            if (container.querySelectorAll('.order-item-row').length <= 1) return;

            row.remove();

            // Hide remove on last remaining row
            const remaining = container.querySelectorAll('.order-item-row');
            if (remaining.length === 1) {
                const lastRemove = remaining[0].querySelector('.btn-remove-item');
                if (lastRemove) lastRemove.style.visibility = 'hidden';
            }

            this._updatePrices();
        });
    },

    _updatePrices() {
        const container = document.getElementById('orderItemsContainer');
        if (!container) return;

        let total = 0;

        container.querySelectorAll('.order-item-row').forEach(row => {
            const select = row.querySelector('.variant-select');
            const qtyInput = row.querySelector('.item-qty');
            const priceEl = row.querySelector('.item-price');
            const subtotalEl = row.querySelector('.item-subtotal');

            if (!select || !qtyInput) return;

            const option = select.options[select.selectedIndex];
            const price = option ? parseFloat(option.dataset.price) || 0 : 0;
            const qty = parseInt(qtyInput.value, 10) || 0;
            const subtotal = price * qty;

            if (priceEl) priceEl.textContent = price > 0 ? `GEL ${price.toFixed(2)}` : '—';
            if (subtotalEl) subtotalEl.textContent = subtotal > 0 ? `GEL ${subtotal.toFixed(2)}` : '—';

            total += subtotal;
        });

        const totalEl = document.getElementById('orderTotal');
        if (totalEl) totalEl.textContent = `GEL ${total.toFixed(2)}`;
    },
};

window.AdminOrders = AdminOrders;

export default AdminOrders;
