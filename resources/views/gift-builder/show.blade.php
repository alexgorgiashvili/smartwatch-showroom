@extends('layouts.app')

@section('title', __('storefront.gift_builder.meta_title'))
@section('meta_description', __('storefront.gift_builder.meta_description'))
@section('robots', config('gift_builder.public_enabled') === true ? 'index, follow' : 'noindex, nofollow, noarchive')

@section('content')
    <div class="gift-experience gift-builder-page" data-gift-builder-experience>
        <section class="gift-builder-intro">
            <div class="gift-shell">
                <a href="{{ route('gift-builder.boxes') }}" class="gift-back-link">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    {{ __('storefront.gift_builder.back_to_boxes') }}
                </a>
                <div class="gift-builder-intro-row">
                    <div>
                        <div class="gift-kicker"><span aria-hidden="true">✦</span>MyTechnic Gift Box</div>
                        <h1>{{ __('storefront.gift_builder.title') }}</h1>
                        <p>{{ __('storefront.gift_builder.subtitle_v2') }}</p>
                    </div>
                    <div class="gift-builder-promise">
                        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                        <span><strong>{{ __('storefront.gift_builder.stock_checked') }}</strong>{{ __('storefront.gift_builder.at_cart_time') }}</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="gift-builder-workspace">
            <div class="gift-shell">
                <div id="gift-preset-overview" class="gift-preset-overview" hidden></div>

                <div id="gift-builder-app" class="gift-builder-grid">
                    <div class="gift-builder-main">
                        <nav id="gift-progress" class="gift-progress" aria-label="{{ __('storefront.gift_builder.progress_label') }}"></nav>
                        <div id="gift-content" class="gift-builder-content"></div>
                    </div>
                    <aside id="gift-summary" class="gift-builder-summary" aria-label="{{ __('storefront.gift_builder.summary') }}"></aside>
                </div>
            </div>
        </section>

        <div id="gift-mobile-bar" class="gift-builder-mobile-bar"></div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const config = @json($builderConfig);
    const i18n = @json(trans('storefront.gift_builder'));
    const common = @json(trans('storefront.common'));
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const tr = (key, replacements = {}) => Object.entries(replacements).reduce(
        (value, [name, replacement]) => String(value).replace(`:${name}`, replacement),
        i18n[key] || key
    );
    const first = (items) => Array.isArray(items) && items.length ? items[0] : null;
    const bySlug = (items, slug) => (items || []).find((item) => item.slug === slug) || null;
    const money = (value) => `${Number(value || 0).toFixed(2)} ₾`;
    const readyBox = typeof config.initial?.ready_box === 'object' && config.initial.ready_box
        ? config.initial.ready_box
        : (config.initial?.ready_box ? { slug: config.initial.ready_box, title: i18n.preset_box } : null);

    const steps = [
        { key: 'main', label: i18n.step_watch, icon: 'fa-clock' },
        { key: 'addons', label: i18n.step_addons, icon: 'fa-puzzle-piece' },
        { key: 'finish', label: i18n.step_finish, icon: 'fa-gift' },
    ];

    const state = {
        step: readyBox ? 2 : 0,
        recipient_type: config.initial?.recipient_type || 'other',
        occasion: config.initial?.occasion || 'just_because',
        budget_band: bySlug(config.budgetBands, config.initial?.budget_band)?.slug || first(config.budgetBands)?.slug || 'under_100',
        packaging_slug: config.initial?.packaging_slug || first(config.packaging)?.slug || 'standard',
        message: '',
        mainVariantId: config.initial?.selected_variant_id ? Number(config.initial.selected_variant_id) : null,
        addonVariantIds: new Set((config.initial?.addon_variant_ids || []).map(Number)),
        readyBox,
        presetDirty: false,
        discountRetained: readyBox ? config.initial?.ready_box?.discount_retained !== false : false,
        priced: null,
        error: null,
        isPricing: false,
        isAdding: false,
    };

    let priceTimer = null;
    let priceRequest = null;
    const trackedSteps = new Set();

    function analytics(name, payload = {}) {
        const safePayload = Object.fromEntries(Object.entries(payload).filter(([, value]) => value !== '' && value !== null && typeof value !== 'undefined'));
        if (window.storefrontAnalytics?.trackCustom) window.storefrontAnalytics.trackCustom(name, safePayload);
        else window.storefrontAnalytics?.track?.(name, safePayload);
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (character) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        }[character]));
    }

    function productForVariant(variantId) {
        const id = Number(variantId);
        return (config.products || []).find((product) => (product.variants || []).some((variant) => Number(variant.id) === id)) || null;
    }

    function variantForProduct(product, variantId) {
        return (product?.variants || []).find((variant) => Number(variant.id) === Number(variantId)) || first(product?.variants || []);
    }

    function selectedLines() {
        const lines = [];
        const main = productForVariant(state.mainVariantId);
        if (main) lines.push({ product: main, variant: variantForProduct(main, state.mainVariantId), role: 'main' });
        state.addonVariantIds.forEach((variantId) => {
            const product = productForVariant(variantId);
            if (product) lines.push({ product, variant: variantForProduct(product, variantId), role: 'addon' });
        });
        return lines;
    }

    function payload() {
        return {
            recipient_type: state.recipient_type,
            occasion: state.occasion,
            budget_band: state.budget_band,
            packaging_slug: state.packaging_slug,
            message: state.message,
            ready_box_slug: state.readyBox?.slug || null,
            ready_box: state.readyBox?.slug || null,
            items: selectedLines().map((line) => ({
                variant_id: Number(line.variant.id), quantity: 1, role: line.role,
            })),
        };
    }

    function roleMatches(product, role) {
        return role === 'main'
            ? ['main', 'both'].includes(product.role)
            : ['addon', 'both'].includes(product.role);
    }

    function budgetMatches(product) {
        const band = bySlug(config.budgetBands, state.budget_band);
        if (!band) return true;
        if (band.min !== null && typeof band.min !== 'undefined' && Number(product.price) < Number(band.min)) return false;
        if (band.max !== null && typeof band.max !== 'undefined' && Number(product.price) > Number(band.max)) return false;
        return true;
    }

    function productsFor(role) {
        const selectedMainProductId = productForVariant(state.mainVariantId)?.id;

        return (config.products || [])
            .filter((product) => (product.variants || []).length)
            .filter((product) => roleMatches(product, role))
            .filter((product) => role !== 'addon' || Number(product.id) !== Number(selectedMainProductId))
            .filter((product) => role !== 'main' || budgetMatches(product));
    }

    function markPresetChanged() {
        if (!state.readyBox) return;
        state.presetDirty = true;
        state.discountRetained = false;
    }

    function discountRetention(priced) {
        if (typeof priced?.discount_retained === 'boolean') return priced.discount_retained;
        if (typeof priced?.preset?.discount_retained === 'boolean') return priced.preset.discount_retained;
        if (typeof priced?.ready_box?.discount_retained === 'boolean') return priced.ready_box.discount_retained;
        if (Array.isArray(priced?.warnings) && priced.warnings.some((warning) => warning?.code === 'preset_discount_removed')) return false;
        return null;
    }

    function selectedProductVariant(product, mode) {
        if (mode === 'main') return variantForProduct(product, state.mainVariantId);
        const selectedId = (product.variants || []).map((variant) => Number(variant.id)).find((id) => state.addonVariantIds.has(id));
        return variantForProduct(product, selectedId);
    }

    function productCard(product, mode) {
        const productVariantIds = (product.variants || []).map((variant) => Number(variant.id));
        const selectedAddonId = productVariantIds.find((id) => state.addonVariantIds.has(id));
        const isSelected = mode === 'main'
            ? productVariantIds.includes(Number(state.mainVariantId))
            : Boolean(selectedAddonId);
        const selectedVariant = selectedProductVariant(product, mode);
        const atLimit = mode === 'addon' && !isSelected && selectedLines().length >= Number(config.maxItems || 4);
        const action = mode === 'main' ? 'data-select-main' : 'data-toggle-addon';
        const defaultId = Number(selectedAddonId || first(product.variants)?.id || 0);

        return `
            <article class="gift-product-card ${isSelected ? 'is-selected' : ''} ${atLimit ? 'is-disabled' : ''}">
                <button type="button" ${action}="${defaultId}" role="${mode === 'main' ? 'radio' : 'checkbox'}" aria-checked="${isSelected}" ${atLimit ? 'disabled' : ''}>
                    <div class="gift-product-media">
                        <img src="${escapeHtml(product.image)}" alt="${escapeHtml(product.name)}" loading="lazy" decoding="async">
                        ${product.badge ? `<span>${escapeHtml(product.badge)}</span>` : ''}
                        <i class="fa-solid ${isSelected ? 'fa-circle-check' : mode === 'main' ? 'fa-plus' : 'fa-plus'}" aria-hidden="true"></i>
                    </div>
                    <div class="gift-product-copy">
                        <h3>${escapeHtml(product.name)}</h3>
                        <p>${escapeHtml(product.price_formatted || money(product.price))}</p>
                        ${product.short_description ? `<small>${escapeHtml(product.short_description)}</small>` : ''}
                    </div>
                </button>
                ${(product.variants || []).length > 1 ? `
                    <fieldset class="gift-product-variants">
                        <legend>${escapeHtml(i18n.choose_color)}</legend>
                        <div>
                            ${(product.variants || []).map((variant) => {
                                const selected = Number(variant.id) === Number(selectedVariant?.id) && isSelected;
                                return `<button type="button" data-select-variant="${Number(variant.id)}" data-variant-mode="${mode}" aria-pressed="${selected}" ${atLimit ? 'disabled aria-disabled="true"' : ''} title="${escapeHtml(variant.color_name || variant.name)}">
                                    ${variant.color_hex ? `<i style="--gift-color:${escapeHtml(variant.color_hex)}"></i>` : ''}
                                    <span>${escapeHtml(variant.color_name || variant.name)}</span>
                                </button>`;
                            }).join('')}
                        </div>
                    </fieldset>` : ''}
            </article>`;
    }

    function renderProgress() {
        const progress = document.getElementById('gift-progress');
        const percent = ((state.step + 1) / steps.length) * 100;
        progress.innerHTML = `
            <div class="gift-progress-top">
                <span>${escapeHtml(tr('step_of', { current: state.step + 1, total: steps.length }))}</span>
                <strong>${escapeHtml(steps[state.step].label)}</strong>
            </div>
            <div class="gift-progress-track" role="progressbar" aria-valuemin="1" aria-valuemax="${steps.length}" aria-valuenow="${state.step + 1}" aria-label="${escapeHtml(i18n.progress_label)}"><span style="width:${percent}%"></span></div>
            <div class="gift-progress-steps">
                ${steps.map((step, index) => `<button type="button" data-go-step="${index}" class="${index === state.step ? 'is-current' : index < state.step ? 'is-complete' : ''}" ${index === state.step ? 'aria-current="step"' : ''}>
                    <span><i class="fa-solid ${index < state.step ? 'fa-check' : step.icon}" aria-hidden="true"></i></span>
                    <small>${escapeHtml(step.label)}</small>
                </button>`).join('')}
            </div>`;
    }

    function renderPresetOverview() {
        const overview = document.getElementById('gift-preset-overview');
        if (!state.readyBox) {
            overview.hidden = true;
            overview.innerHTML = '';
            return;
        }
        overview.hidden = false;
        const retained = state.discountRetained;
        overview.innerHTML = `
            <div class="gift-preset-icon"><i class="fa-solid fa-box-open" aria-hidden="true"></i></div>
            <div class="gift-preset-copy">
                <p>${escapeHtml(i18n.editing_ready_box)}</p>
                <h2>${escapeHtml(state.readyBox.title || i18n.preset_box)}</h2>
                <span class="${retained ? 'is-retained' : 'is-removed'}"><i class="fa-solid ${retained ? 'fa-tag' : 'fa-circle-info'}" aria-hidden="true"></i>${escapeHtml(retained ? i18n.preset_discount_retained : i18n.preset_discount_removed)}</span>
            </div>
            <div class="gift-preset-actions">
                <button type="button" data-go-step="0">${escapeHtml(i18n.change_watch)}</button>
                <button type="button" data-go-step="1">${escapeHtml(i18n.change_addons)}</button>
                <button type="button" data-go-step="2">${escapeHtml(i18n.change_package)}</button>
            </div>`;
    }

    function renderContent() {
        const content = document.getElementById('gift-content');
        const step = steps[state.step].key;
        let html = '';

        if (step === 'main') {
            const products = productsFor('main');
            html = `
                <div class="gift-step-heading">
                    <div><span>1</span><div><h2 tabindex="-1">${escapeHtml(i18n.watch_title)}</h2><p>${escapeHtml(i18n.watch_text)}</p></div></div>
                    <small>${escapeHtml(tr('available_count', { count: products.length }))}</small>
                </div>
                <fieldset class="gift-budget-filter">
                    <legend>${escapeHtml(i18n.budget_filter)}</legend>
                    <div>
                        ${(config.budgetBands || []).map((option) => `<button type="button" data-budget="${escapeHtml(option.slug)}" aria-pressed="${option.slug === state.budget_band}" class="${option.slug === state.budget_band ? 'is-selected' : ''}">${escapeHtml(option.label)}</button>`).join('')}
                    </div>
                </fieldset>
                <div class="gift-product-grid" role="radiogroup" aria-label="${escapeHtml(i18n.watch_title)}">
                    ${products.length ? products.map((product) => productCard(product, 'main')).join('') : `<p class="gift-builder-empty">${escapeHtml(i18n.no_products)}</p>`}
                </div>`;
        }

        if (step === 'addons') {
            const products = productsFor('addon');
            html = `
                <div class="gift-step-heading">
                    <div><span>2</span><div><h2 tabindex="-1">${escapeHtml(i18n.addons_title)}</h2><p>${escapeHtml(tr('addons_text_v2', { count: Math.max(0, Number(config.maxItems || 4) - 1) }))}</p></div></div>
                    <button type="button" data-skip-addons class="gift-skip-button">${escapeHtml(i18n.skip_addons)}<i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                </div>
                <p class="gift-selection-count"><i class="fa-solid fa-box" aria-hidden="true"></i>${escapeHtml(tr('addon_selected_count', { count: state.addonVariantIds.size, max: Math.max(0, Number(config.maxItems || 4) - 1) }))}</p>
                <div class="gift-product-grid" role="group" aria-label="${escapeHtml(i18n.addons_title)}">
                    ${products.length ? products.map((product) => productCard(product, 'addon')).join('') : `<p class="gift-builder-empty">${escapeHtml(i18n.no_addons)}</p>`}
                </div>`;
        }

        if (step === 'finish') {
            const packaging = bySlug(config.packaging, state.packaging_slug);
            html = `
                <div class="gift-step-heading">
                    <div><span>3</span><div><h2 tabindex="-1">${escapeHtml(i18n.finish_title)}</h2><p>${escapeHtml(i18n.finish_text)}</p></div></div>
                </div>
                <div class="gift-finish-grid">
                    <fieldset class="gift-packaging-options">
                        <legend>${escapeHtml(i18n.packaging)}</legend>
                        ${(config.packaging || []).map((option) => `<button type="button" data-package="${escapeHtml(option.slug)}" aria-pressed="${option.slug === state.packaging_slug}" class="${option.slug === state.packaging_slug ? 'is-selected' : ''}">
                            <span class="gift-package-icon"><i class="fa-solid ${option.slug === 'premium' ? 'fa-gem' : 'fa-gift'}" aria-hidden="true"></i></span>
                            <span><strong>${escapeHtml(option.label)}</strong><small>${escapeHtml(tr('capacity', { count: option.capacity_units }))}</small></span>
                            <b>${Number(option.price || 0) ? money(option.price) : escapeHtml(common.free || i18n.free)}</b>
                            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                        </button>`).join('')}
                    </fieldset>
                    <div class="gift-card-message">
                        <div class="gift-message-heading"><span><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i></span><div><h3>${escapeHtml(i18n.card_message)}</h3><p>${escapeHtml(i18n.message_optional_help)}</p></div></div>
                        <label for="gift-message" class="sr-only">${escapeHtml(i18n.card_message)}</label>
                        <textarea id="gift-message" maxlength="${Number(config.messageMaxLength || 300)}" rows="5" placeholder="${escapeHtml(i18n.message_placeholder)}">${escapeHtml(state.message)}</textarea>
                        <div><small>${escapeHtml(i18n.optional)}</small><span data-message-count>${state.message.length}/${Number(config.messageMaxLength || 300)}</span></div>
                    </div>
                </div>
                ${state.readyBox ? `<div data-discount-warning class="gift-discount-warning ${state.discountRetained ? 'hidden' : ''}"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><p><strong>${escapeHtml(i18n.discount_changed_title)}</strong><span>${escapeHtml(i18n.discount_changed_text)}</span></p></div>` : ''}
                <div class="gift-finish-mobile-summary">${summaryLinesHtml(false)}</div>`;
        }

        content.innerHTML = `${html}
            <div class="gift-step-actions">
                <button type="button" data-prev class="gift-secondary-button ${state.step === 0 ? 'is-invisible' : ''}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i>${escapeHtml(i18n.back)}</button>
                <button type="button" data-next class="gift-primary-button" ${state.step === 0 && !state.mainVariantId ? 'disabled' : ''}>${escapeHtml(state.step === steps.length - 1 ? i18n.add_box : i18n.continue)}<i class="fa-solid ${state.step === steps.length - 1 ? 'fa-cart-plus' : 'fa-arrow-right'}" aria-hidden="true"></i></button>
            </div>`;
    }

    function summaryLinesHtml(compact = true) {
        const lines = selectedLines();
        if (!lines.length) return `<p class="gift-summary-empty">${escapeHtml(i18n.choose_main)}</p>`;
        return lines.map((line) => `<div class="gift-summary-line">
            <img src="${escapeHtml(line.product.image)}" alt="" loading="lazy">
            <div><p>${escapeHtml(line.product.name)}</p><small>${escapeHtml(line.role === 'main' ? i18n.main_gift : i18n.addon)} · ${escapeHtml(line.variant?.color_name || line.variant?.name || '')}</small></div>
            <strong>${escapeHtml(line.product.price_formatted || money(line.product.price))}</strong>
            ${!compact ? `<button type="button" data-go-step="${line.role === 'main' ? 0 : 1}" aria-label="${escapeHtml(i18n.edit)}"><i class="fa-solid fa-pen" aria-hidden="true"></i></button>` : ''}
        </div>`).join('');
    }

    function renderSummary() {
        const summary = document.getElementById('gift-summary');
        const mobile = document.getElementById('gift-mobile-bar');
        const packaging = bySlug(config.packaging, state.packaging_slug);
        const fallbackSubtotal = selectedLines().reduce((sum, line) => sum + Number(line.product.price || 0), 0);
        const itemSubtotal = Number(state.priced?.items_subtotal ?? fallbackSubtotal);
        const packagingAmount = Number(state.priced?.packaging_amount ?? packaging?.price ?? 0);
        const discount = Number(state.priced?.discount_amount || state.priced?.discount?.amount || 0);
        const total = Number(state.priced?.total ?? (itemSubtotal + packagingAmount - discount));
        const canAdd = Boolean(state.mainVariantId && state.priced && !state.error && !state.isAdding && !state.isPricing);
        const warnings = Array.isArray(state.priced?.warnings) ? state.priced.warnings : [];

        summary.innerHTML = `<div class="gift-summary-card">
            <header><div><p>${escapeHtml(i18n.your_box)}</p><h2>${escapeHtml(i18n.summary)}</h2></div><span>${selectedLines().length}/${Number(config.maxItems || 4)}</span></header>
            <div class="gift-summary-lines">${summaryLinesHtml(true)}</div>
            <dl>
                <div><dt>${escapeHtml(i18n.products_total)}</dt><dd>${money(itemSubtotal)}</dd></div>
                <div><dt>${escapeHtml(i18n.packaging)} <small>${escapeHtml(packaging?.label || '')}</small></dt><dd>${packagingAmount ? money(packagingAmount) : escapeHtml(common.free || i18n.free)}</dd></div>
                ${discount > 0 ? `<div class="is-discount"><dt>${escapeHtml(i18n.gift_discount)}</dt><dd>−${money(discount)}</dd></div>` : ''}
                <div class="is-total"><dt>${escapeHtml(common.total || i18n.total)}</dt><dd>${money(total)}</dd></div>
            </dl>
            <div class="gift-summary-live" aria-live="polite">
                ${state.isPricing ? `<p class="is-loading"><i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i>${escapeHtml(i18n.rechecking_price)}</p>` : ''}
                ${state.error ? `<p class="is-error"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>${escapeHtml(state.error)}</p>` : ''}
                ${warnings.map((warning) => `<p class="is-warning"><i class="fa-solid fa-circle-info" aria-hidden="true"></i>${escapeHtml(warning.message || warning)}</p>`).join('')}
            </div>
            <button type="button" data-add-cart class="gift-primary-button" ${canAdd ? '' : 'disabled'}><i class="fa-solid fa-cart-plus" aria-hidden="true"></i>${escapeHtml(state.isAdding ? i18n.adding : i18n.add_box)}</button>
            <p class="gift-summary-secure"><i class="fa-solid fa-lock" aria-hidden="true"></i>${escapeHtml(i18n.secure_checkout)}</p>
        </div>`;

        mobile.innerHTML = `<div><span>${escapeHtml(tr('selected_count', { count: selectedLines().length }))}</span><strong>${money(total)}</strong></div>
            <button type="button" ${state.step === steps.length - 1 ? 'data-add-cart' : 'data-next'} class="gift-primary-button" ${(state.step === 0 && !state.mainVariantId) || (state.step === steps.length - 1 && !canAdd) ? 'disabled' : ''}>${escapeHtml(state.step === steps.length - 1 ? i18n.add_to_cart : i18n.next)}<i class="fa-solid ${state.step === steps.length - 1 ? 'fa-cart-plus' : 'fa-arrow-right'}" aria-hidden="true"></i></button>`;
    }

    function render(trackStep = false) {
        renderPresetOverview();
        renderProgress();
        renderContent();
        renderSummary();
        if (trackStep || !trackedSteps.has(state.step)) {
            trackedSteps.add(state.step);
            analytics('gift_builder_step_view', {
                step_number: state.step + 1,
                step_name: steps[state.step].key,
                gift_mode: state.readyBox ? 'preset' : 'custom',
                box_slug: state.readyBox?.slug,
            });
        }
    }

    function renderAndRestoreFocus(attribute, value) {
        render();
        requestAnimationFrame(() => {
            const control = Array.from(document.querySelectorAll(`[${attribute}]`))
                .find((element) => element.getAttribute(attribute) === String(value));
            control?.focus({ preventScroll: true });
        });
    }

    function syncDiscountNotice() {
        document.querySelector('[data-discount-warning]')?.classList.toggle('hidden', state.discountRetained);
    }

    function goStep(index, shouldFocus = true) {
        state.step = Math.max(0, Math.min(steps.length - 1, Number(index)));
        render(true);
        if (shouldFocus) {
            requestAnimationFrame(() => {
                document.querySelector('#gift-content h2')?.focus({ preventScroll: true });
                document.getElementById('gift-builder-app')?.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
            });
        }
    }

    function schedulePrice() {
        clearTimeout(priceTimer);
        priceTimer = setTimeout(refreshPrice, 220);
    }

    async function refreshPrice() {
        priceRequest?.abort();
        if (!state.mainVariantId) {
            state.priced = null;
            state.error = null;
            state.isPricing = false;
            renderSummary();
            return;
        }
        priceRequest = new AbortController();
        state.isPricing = true;
        state.error = null;
        renderSummary();
        try {
            const response = await fetch(config.routes.price, {
                method: 'POST', credentials: 'same-origin', signal: priceRequest.signal,
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload()),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                const errors = data.errors ? Object.values(data.errors).flat() : [];
                throw new Error(errors[0] || data.message || i18n.pricing_failed);
            }
            state.priced = data.gift_box;
            state.error = null;
            const retained = discountRetention(state.priced);
            if (typeof retained === 'boolean') {
                state.discountRetained = retained;
                state.presetDirty = !retained;
            }
        } catch (error) {
            if (error.name === 'AbortError') return;
            state.priced = null;
            state.error = error.message || i18n.network_error;
            analytics('gift_flow_error', { error_stage: 'pricing', box_slug: state.readyBox?.slug });
        } finally {
            state.isPricing = false;
            renderPresetOverview();
            syncDiscountNotice();
            renderSummary();
        }
    }

    async function addToCart() {
        if (!state.mainVariantId || state.isAdding) return;
        if (!state.priced || state.error) {
            schedulePrice();
            return;
        }
        state.isAdding = true;
        renderSummary();
        try {
            const response = await fetch(config.routes.addToCart, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload()),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || data.success === false) {
                const errors = data.errors ? Object.values(data.errors).flat() : [];
                throw new Error(errors[0] || data.message || i18n.add_failed);
            }
            window.cartUi?.updateBadges?.(data.cart_count || 0);
            const lines = selectedLines();
            const value = Number(data.gift_box?.total || state.priced?.total || 0);
            analytics('gift_add_to_cart_success', { box_slug: state.readyBox?.slug, gift_mode: state.readyBox ? 'customized_preset' : 'custom', value, currency: 'GEL', num_items: lines.length });
            window.storefrontAnalytics?.track?.('AddToCart', {
                content_ids: lines.map((line) => String(line.product.id)), content_name: state.readyBox?.title || i18n.custom_box,
                content_type: 'product_group', currency: 'GEL', value, num_items: lines.length,
                contents: lines.map((line) => ({ id: String(line.product.id), quantity: 1 })),
            });
            window.location.assign(data.redirect_url || config.routes.cart);
        } catch (error) {
            state.error = error.message || i18n.network_error;
            state.isAdding = false;
            analytics('gift_flow_error', { error_stage: 'add_to_cart', box_slug: state.readyBox?.slug });
            renderSummary();
        }
    }

    document.addEventListener('click', (event) => {
        const step = event.target.closest('[data-go-step]');
        if (step) { goStep(step.dataset.goStep); return; }

        const budget = event.target.closest('[data-budget]');
        if (budget) {
            state.budget_band = budget.dataset.budget;
            const currentMain = productForVariant(state.mainVariantId);
            if (currentMain && !budgetMatches(currentMain)) { state.mainVariantId = null; markPresetChanged(); }
            analytics('gift_item_selected', { item_type: 'budget', budget_band: state.budget_band });
            schedulePrice(); renderAndRestoreFocus('data-budget', state.budget_band); return;
        }

        const main = event.target.closest('[data-select-main]');
        if (main) {
            const nextId = Number(main.dataset.selectMain);
            const currentProduct = productForVariant(state.mainVariantId);
            const nextProduct = productForVariant(nextId);
            if (currentProduct?.id !== nextProduct?.id) markPresetChanged();
            state.mainVariantId = nextId;
            (nextProduct?.variants || []).forEach((variant) => state.addonVariantIds.delete(Number(variant.id)));
            analytics('gift_item_selected', { item_type: 'main', product_id: nextProduct?.id, variant_id: nextId, selected: true });
            schedulePrice(); renderAndRestoreFocus('data-select-main', nextId); return;
        }

        const addon = event.target.closest('[data-toggle-addon]');
        if (addon) {
            const id = Number(addon.dataset.toggleAddon);
            const product = productForVariant(id);
            const mainProduct = productForVariant(state.mainVariantId);
            if (!product || Number(product.id) === Number(mainProduct?.id)) return;
            const selectedIds = (product?.variants || []).map((variant) => Number(variant.id));
            const selectedId = selectedIds.find((variantId) => state.addonVariantIds.has(variantId));
            if (selectedId) state.addonVariantIds.delete(selectedId);
            else if (selectedLines().length < Number(config.maxItems || 4)) state.addonVariantIds.add(id);
            markPresetChanged();
            analytics('gift_item_selected', { item_type: 'addon', product_id: product?.id, variant_id: id, selected: !selectedId });
            schedulePrice(); renderAndRestoreFocus('data-toggle-addon', id); return;
        }

        const variant = event.target.closest('[data-select-variant]');
        if (variant) {
            const id = Number(variant.dataset.selectVariant);
            const mode = variant.dataset.variantMode;
            const product = productForVariant(id);
            if (!product || (mode === 'addon' && Number(product.id) === Number(productForVariant(state.mainVariantId)?.id))) return;
            if (mode === 'main') {
                state.mainVariantId = id;
                (product.variants || []).forEach((item) => state.addonVariantIds.delete(Number(item.id)));
            } else {
                const wasSelected = (product?.variants || []).some((item) => state.addonVariantIds.has(Number(item.id)));
                if (!wasSelected && selectedLines().length >= Number(config.maxItems || 4)) return;
                (product?.variants || []).forEach((item) => state.addonVariantIds.delete(Number(item.id)));
                state.addonVariantIds.add(id);
            }
            analytics('gift_item_selected', { item_type: 'variant', product_id: product?.id, variant_id: id, selected: true });
            schedulePrice(); renderAndRestoreFocus('data-select-variant', id); return;
        }

        const packaging = event.target.closest('[data-package]');
        if (packaging) {
            if (state.packaging_slug !== packaging.dataset.package) markPresetChanged();
            state.packaging_slug = packaging.dataset.package;
            analytics('gift_item_selected', { item_type: 'packaging', packaging_slug: state.packaging_slug, selected: true });
            schedulePrice(); renderAndRestoreFocus('data-package', state.packaging_slug); return;
        }

        if (event.target.closest('[data-skip-addons]')) { goStep(2); return; }
        if (event.target.closest('[data-prev]')) { goStep(state.step - 1); return; }
        if (event.target.closest('[data-next]')) {
            if (state.step === steps.length - 1) addToCart();
            else goStep(state.step + 1);
            return;
        }
        if (event.target.closest('[data-add-cart]')) addToCart();
    });

    document.addEventListener('input', (event) => {
        if (event.target.id !== 'gift-message') return;
        state.message = event.target.value.slice(0, Number(config.messageMaxLength || 300));
        const counter = document.querySelector('[data-message-count]');
        if (counter) counter.textContent = `${state.message.length}/${Number(config.messageMaxLength || 300)}`;
    });

    render(true);
    schedulePrice();
}());
</script>
@endpush
