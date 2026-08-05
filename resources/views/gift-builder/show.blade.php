@extends('layouts.app')

@section('title', 'Gift Box Builder')
@section('meta_description', 'Build a gift box with MyTechnic smart watches and add-ons.')

@section('content')
    <section class="bg-slate-50 py-6 sm:py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-primary-600">MyTechnic Gift Box</p>
                    <h1 class="mt-1 text-2xl font-extrabold text-gray-950 sm:text-3xl">{{ __('storefront.gift_builder.title') }}</h1>
                    <p class="mt-2 max-w-2xl text-sm text-gray-600">{{ __('storefront.gift_builder.subtitle') }}</p>
                </div>
                <a href="{{ route('landing.gift-guide') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-primary-700 hover:text-primary-800">
                    <i class="fa-solid fa-book-open text-xs"></i>
                    Gift Guide
                </a>
            </div>

            <div id="gift-builder-app" class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_360px]">
                <div class="min-w-0">
                    <div id="gift-stepper" class="sticky top-16 z-20 mb-4 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-sm"></div>
                    <div id="gift-content" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6"></div>
                </div>

                <aside id="gift-summary" class="hidden lg:block"></aside>
            </div>
        </div>
    </section>

    <div id="gift-mobile-bar" class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white p-3 shadow-[0_-10px_30px_rgba(15,23,42,0.12)] lg:hidden"></div>
@endsection

@push('scripts')
<script>
(function () {
    const config = @json($builderConfig);
    const i18n = @json(trans('storefront.gift_builder'));
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const tr = (key, replacements = {}) => Object.entries(replacements).reduce(
        (value, [name, replacement]) => String(value).replace(`:${name}`, replacement),
        i18n[key] || key
    );

    const steps = [
        { key: 'recipient', label: i18n.steps[0] },
        { key: 'occasion', label: i18n.steps[1] },
        { key: 'main', label: i18n.steps[2] },
        { key: 'addons', label: i18n.steps[3] },
        { key: 'card', label: i18n.steps[4] },
        { key: 'review', label: i18n.steps[5] }
    ];

    const first = (items) => Array.isArray(items) && items.length ? items[0] : null;
    const bySlug = (items, slug) => (items || []).find((item) => item.slug === slug) || null;
    const money = (value) => `${Number(value || 0).toFixed(2)} ₾`;

    const state = {
        step: 0,
        recipient_type: config.initial.recipient_type || first(config.recipients)?.slug || 'other',
        occasion: config.initial.occasion || first(config.occasions)?.slug || 'just_because',
        budget_band: config.initial.budget_band || 'all',
        packaging_slug: config.initial.packaging_slug || first(config.packaging)?.slug || 'standard',
        message: '',
        mainVariantId: config.initial.selected_variant_id || null,
        addonVariantIds: new Set(),
        priced: null,
        warnings: [],
        error: null,
        isPricing: false,
        isAdding: false
    };

    function productForVariant(variantId) {
        const id = Number(variantId);
        return (config.products || []).find((product) => (product.variants || []).some((variant) => Number(variant.id) === id)) || null;
    }

    function variantForProduct(product, variantId) {
        return (product?.variants || []).find((variant) => Number(variant.id) === Number(variantId)) || first(product?.variants || []);
    }

    function selectedLines() {
        const lines = [];
        const mainProduct = productForVariant(state.mainVariantId);
        if (mainProduct) {
            lines.push({ product: mainProduct, variant: variantForProduct(mainProduct, state.mainVariantId), role: 'main' });
        }

        Array.from(state.addonVariantIds).forEach((variantId) => {
            const product = productForVariant(variantId);
            if (product) {
                lines.push({ product, variant: variantForProduct(product, variantId), role: 'addon' });
            }
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
            items: selectedLines().map((line) => ({
                variant_id: Number(line.variant.id),
                quantity: 1,
                role: line.role
            }))
        };
    }

    function roleMatches(product, role) {
        if (role === 'main') return ['main', 'both'].includes(product.role);
        if (role === 'addon') return ['addon', 'both'].includes(product.role);
        return ['main', 'addon', 'both'].includes(product.role);
    }

    function tagMatches(tags, selected) {
        return !Array.isArray(tags) || tags.length === 0 || tags.includes(selected);
    }

    function budgetMatches(product) {
        if (!state.budget_band || state.budget_band === 'all') return true;
        if (product.budget_band && product.budget_band !== 'all') return product.budget_band === state.budget_band;

        const band = bySlug(config.budgetBands, state.budget_band);
        if (!band) return true;
        if (band.min !== null && Number(product.price) < Number(band.min)) return false;
        if (band.max !== null && Number(product.price) > Number(band.max)) return false;
        return true;
    }

    function productsFor(role) {
        return (config.products || [])
            .filter((product) => (product.variants || []).length > 0)
            .filter((product) => roleMatches(product, role))
            .filter((product) => tagMatches(product.recipient_tags, state.recipient_type))
            .filter((product) => tagMatches(product.occasion_tags, state.occasion))
            .filter((product) => budgetMatches(product));
    }

    function canSelectAddon(product) {
        const selectedVariantId = (product.variants || [])
            .map((variant) => Number(variant.id))
            .find((variantId) => state.addonVariantIds.has(variantId));

        if (selectedVariantId) return true;
        return selectedLines().length < Number(config.maxItems || 4);
    }

    function optionButton(type, option, selectedSlug) {
        const selected = option.slug === selectedSlug;
        return `
            <button type="button" data-option-type="${type}" data-option-value="${option.slug}" class="group rounded-xl border p-4 text-left transition ${selected ? 'border-primary-500 bg-primary-50 ring-2 ring-primary-100' : 'border-slate-200 bg-white hover:border-primary-200 hover:bg-primary-50/40'}">
                <span class="block text-sm font-bold text-gray-950">${escapeHtml(option.label)}</span>
                ${option.description ? `<span class="mt-1 block text-xs text-gray-500">${escapeHtml(option.description)}</span>` : ''}
            </button>
        `;
    }

    function productCard(product, mode) {
        const productVariantIds = (product.variants || []).map((variant) => Number(variant.id));
        const selectedAddonVariantId = productVariantIds.find((variantId) => state.addonVariantIds.has(variantId)) || null;
        const selectedVariantId = mode === 'main'
            ? Number(state.mainVariantId)
            : Number(selectedAddonVariantId || first(product.variants)?.id);
        const isSelected = mode === 'main'
            ? (product.variants || []).some((variant) => Number(variant.id) === Number(state.mainVariantId))
            : selectedAddonVariantId !== null;
        const disabled = mode === 'addon' && !isSelected && !canSelectAddon(product);
        const actionAttr = mode === 'main' ? 'data-select-main' : 'data-toggle-addon';
        const actionVariantId = selectedAddonVariantId || first(product.variants)?.id || '';

        return `
            <article class="flex h-full flex-col overflow-hidden rounded-2xl border ${isSelected ? 'border-primary-500 ring-2 ring-primary-100' : 'border-slate-200'} bg-white shadow-sm">
                <button type="button" ${actionAttr}="${actionVariantId}" ${disabled ? 'disabled' : ''} class="flex h-full flex-col text-left disabled:cursor-not-allowed disabled:opacity-50">
                    <div class="relative aspect-[4/3] bg-slate-100">
                        <img src="${escapeAttr(product.image)}" alt="${escapeAttr(product.name)}" loading="lazy" class="h-full w-full object-cover">
                        ${product.badge ? `<span class="absolute left-3 top-3 rounded-full bg-white/90 px-2.5 py-1 text-[11px] font-bold text-primary-700 shadow-sm">${escapeHtml(product.badge)}</span>` : ''}
                    </div>
                    <div class="flex flex-1 flex-col p-4">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-sm font-bold text-gray-950">${escapeHtml(product.name)}</h3>
                            <span class="shrink-0 text-sm font-extrabold text-primary-700">${escapeHtml(product.price_formatted)}</span>
                        </div>
                        ${product.short_description ? `<p class="mt-2 line-clamp-2 text-xs text-gray-500">${escapeHtml(product.short_description)}</p>` : ''}
                        ${product.note ? `<p class="mt-2 rounded-lg bg-slate-50 px-2.5 py-2 text-xs text-gray-600">${escapeHtml(product.note)}</p>` : ''}
                        <div class="mt-auto pt-4">
                            <span class="inline-flex w-full items-center justify-center gap-2 rounded-full ${isSelected ? 'bg-primary-600 text-white' : 'border border-slate-200 text-gray-700'} px-4 py-2 text-xs font-bold">
                                <i class="fa-solid ${isSelected ? 'fa-check' : mode === 'main' ? 'fa-gift' : 'fa-plus'} text-[10px]"></i>
                                ${isSelected ? i18n.selected : mode === 'main' ? i18n.choose_main_action : i18n.add_to_box}
                            </span>
                        </div>
                    </div>
                </button>
                ${(product.variants || []).length > 1 ? `
                    <div class="border-t border-slate-100 p-3">
                        <div class="flex flex-wrap gap-2">
                            ${(product.variants || []).map((variant) => `
                                <button type="button" data-select-variant="${variant.id}" data-variant-mode="${mode}" class="rounded-full border px-3 py-1 text-[11px] font-semibold ${Number(variant.id) === selectedVariantId ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-slate-200 text-gray-600 hover:border-primary-200'}">
                                    ${variant.color_hex ? `<span class="mr-1 inline-block h-2.5 w-2.5 rounded-full align-middle" style="background:${variant.color_hex}"></span>` : ''}
                                    ${escapeHtml(variant.name)}
                                </button>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}
            </article>
        `;
    }

    function selectedSummaryHtml(compact) {
        const lines = selectedLines();
        if (!lines.length) {
            return `<p class="rounded-xl border border-dashed border-slate-200 px-4 py-6 text-center text-sm text-gray-500">${escapeHtml(i18n.choose_main)}</p>`;
        }

        return lines.map((line) => `
            <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3">
                <img src="${escapeAttr(line.product.image)}" alt="${escapeAttr(line.product.name)}" class="${compact ? 'h-10 w-10' : 'h-12 w-12'} rounded-lg object-cover">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-bold text-gray-900">${escapeHtml(line.product.name)}</p>
                    <p class="text-xs text-gray-500">${escapeHtml(line.variant?.name || '')} • ${line.role === 'main' ? @js(__('storefront.common.main')) : @js(__('storefront.common.addon'))}</p>
                </div>
                <p class="shrink-0 text-sm font-bold text-primary-700">${escapeHtml(line.product.price_formatted)}</p>
            </div>
        `).join('');
    }

    function renderStepper() {
        const stepper = document.getElementById('gift-stepper');
        stepper.innerHTML = `
            <div class="flex min-w-max gap-1">
                ${steps.map((step, index) => `
                    <button type="button" data-go-step="${index}" class="flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-bold transition ${index === state.step ? 'bg-primary-600 text-white shadow-sm' : index < state.step ? 'bg-primary-50 text-primary-700' : 'text-gray-500 hover:bg-slate-100'}">
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full ${index === state.step ? 'bg-white/20' : 'bg-slate-100'}">${index + 1}</span>
                        ${step.label}
                    </button>
                `).join('')}
            </div>
        `;
    }

    function renderContent() {
        const content = document.getElementById('gift-content');
        const step = steps[state.step].key;
        let html = '';

        if (step === 'recipient') {
            html = `
                <div class="mb-5">
                    <h2 class="text-xl font-extrabold text-gray-950">${escapeHtml(i18n.recipient_title)}</h2>
                    <p class="mt-1 text-sm text-gray-500">${escapeHtml(i18n.recipient_text)}</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">${config.recipients.map((option) => optionButton('recipient_type', option, state.recipient_type)).join('')}</div>
            `;
        }

        if (step === 'occasion') {
            html = `
                <div class="mb-5">
                    <h2 class="text-xl font-extrabold text-gray-950">${escapeHtml(i18n.occasion_title)}</h2>
                    <p class="mt-1 text-sm text-gray-500">${escapeHtml(i18n.occasion_text)}</p>
                </div>
                <div class="mb-6">
                    <h3 class="mb-3 text-sm font-bold text-gray-900">${escapeHtml(i18n.starter_presets)}</h3>
                    <div class="grid gap-3 md:grid-cols-3">
                        ${config.presets.map((preset) => `
                            <button type="button" data-preset="${preset.slug}" class="rounded-xl border border-slate-200 bg-white p-4 text-left transition hover:border-primary-200 hover:bg-primary-50/40">
                                <span class="block text-sm font-bold text-gray-950">${escapeHtml(preset.label)}</span>
                                ${preset.description ? `<span class="mt-1 block text-xs text-gray-500">${escapeHtml(preset.description)}</span>` : ''}
                            </button>
                        `).join('')}
                    </div>
                </div>
                <div class="grid gap-6 lg:grid-cols-2">
                    <div>
                        <h3 class="mb-3 text-sm font-bold text-gray-900">${escapeHtml(i18n.occasion)}</h3>
                        <div class="grid gap-3 sm:grid-cols-2">${config.occasions.map((option) => optionButton('occasion', option, state.occasion)).join('')}</div>
                    </div>
                    <div>
                        <h3 class="mb-3 text-sm font-bold text-gray-900">${escapeHtml(i18n.budget)}</h3>
                        <div class="grid gap-3 sm:grid-cols-2">${config.budgetBands.map((option) => optionButton('budget_band', option, state.budget_band)).join('')}</div>
                    </div>
                </div>
            `;
        }

        if (step === 'main') {
            const products = productsFor('main');
            html = `
                <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-xl font-extrabold text-gray-950">${escapeHtml(i18n.main_title)}</h2>
                        <p class="mt-1 text-sm text-gray-500">${escapeHtml(i18n.main_text)}</p>
                    </div>
                    <span class="text-xs font-semibold text-gray-500">${escapeHtml(tr('local_products', { count: products.length }))}</span>
                </div>
                ${products.length ? `<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">${products.map((product) => productCard(product, 'main')).join('')}</div>` : `<p class="rounded-xl border border-dashed border-slate-200 p-6 text-center text-sm text-gray-500">${escapeHtml(i18n.no_products)}</p>`}
            `;
        }

        if (step === 'addons') {
            const products = productsFor('addon');
            html = `
                <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-xl font-extrabold text-gray-950">${escapeHtml(i18n.addons_title)}</h2>
                        <p class="mt-1 text-sm text-gray-500">${escapeHtml(tr('addons_text', { count: config.maxItems }))}</p>
                    </div>
                    <span class="text-xs font-semibold text-gray-500">${escapeHtml(tr('selected_count', { count: `${selectedLines().length}/${config.maxItems}` }))}</span>
                </div>
                ${products.length ? `<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">${products.map((product) => productCard(product, 'addon')).join('')}</div>` : `<p class="rounded-xl border border-dashed border-slate-200 p-6 text-center text-sm text-gray-500">${escapeHtml(i18n.no_addons)}</p>`}
            `;
        }

        if (step === 'card') {
            html = `
                <div class="mb-5">
                    <h2 class="text-xl font-extrabold text-gray-950">${escapeHtml(i18n.packaging_title)}</h2>
                    <p class="mt-1 text-sm text-gray-500">${escapeHtml(tr('packaging_text', { count: config.messageMaxLength }))}</p>
                </div>
                <div class="grid gap-6 lg:grid-cols-2">
                    <div>
                        <h3 class="mb-3 text-sm font-bold text-gray-900">${escapeHtml(i18n.packaging)}</h3>
                        <div class="grid gap-3">
                            ${config.packaging.map((packaging) => `
                                <button type="button" data-package="${packaging.slug}" class="rounded-xl border p-4 text-left transition ${packaging.slug === state.packaging_slug ? 'border-primary-500 bg-primary-50 ring-2 ring-primary-100' : 'border-slate-200 bg-white hover:border-primary-200 hover:bg-primary-50/40'}">
                                    <span class="flex items-center justify-between gap-3">
                                        <span class="font-bold text-gray-950">${escapeHtml(packaging.label)}</span>
                                        <span class="font-extrabold text-primary-700">${money(packaging.price)}</span>
                                    </span>
                                    <span class="mt-1 block text-xs text-gray-500">${escapeHtml(tr('capacity', { count: packaging.capacity_units }))}</span>
                                </button>
                            `).join('')}
                        </div>
                    </div>
                    <div>
                        <label for="gift-message" class="mb-3 block text-sm font-bold text-gray-900">${escapeHtml(i18n.card_message)}</label>
                        <textarea id="gift-message" maxlength="${config.messageMaxLength}" rows="8" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-primary-500 focus:ring-primary-500" placeholder="${escapeAttr(i18n.message_placeholder)}">${escapeHtml(state.message)}</textarea>
                        <div class="mt-2 flex justify-between text-xs text-gray-500">
                            <span>${escapeHtml(i18n.optional)}</span>
                            <span>${state.message.length}/${config.messageMaxLength}</span>
                        </div>
                    </div>
                </div>
            `;
        }

        if (step === 'review') {
            html = `
                <div class="mb-5">
                    <h2 class="text-xl font-extrabold text-gray-950">${escapeHtml(i18n.review)}</h2>
                    <p class="mt-1 text-sm text-gray-500">${escapeHtml(i18n.validation_note)}</p>
                </div>
                <div class="grid gap-5 lg:grid-cols-2">
                    <div class="space-y-3">${selectedSummaryHtml(false)}</div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <dl class="space-y-2 text-sm text-gray-700">
                            <div class="flex justify-between"><dt>${escapeHtml(i18n.recipient)}</dt><dd class="font-semibold">${escapeHtml(bySlug(config.recipients, state.recipient_type)?.label || state.recipient_type)}</dd></div>
                            <div class="flex justify-between"><dt>${escapeHtml(i18n.occasion)}</dt><dd class="font-semibold">${escapeHtml(bySlug(config.occasions, state.occasion)?.label || state.occasion)}</dd></div>
                            <div class="flex justify-between"><dt>${escapeHtml(i18n.budget)}</dt><dd class="font-semibold">${escapeHtml(bySlug(config.budgetBands, state.budget_band)?.label || state.budget_band)}</dd></div>
                            <div class="flex justify-between"><dt>${escapeHtml(i18n.packaging)}</dt><dd class="font-semibold">${escapeHtml(bySlug(config.packaging, state.packaging_slug)?.label || state.packaging_slug)}</dd></div>
                            ${state.message ? `<div class="border-t border-slate-200 pt-2"><dt class="mb-1 text-gray-500">${escapeHtml(i18n.message)}</dt><dd class="rounded-xl bg-white p-3 text-gray-700">“${escapeHtml(state.message)}”</dd></div>` : ''}
                        </dl>
                    </div>
                </div>
            `;
        }

        content.innerHTML = `
            ${html}
            <div class="mt-6 flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:justify-between">
                <button type="button" data-prev class="inline-flex items-center justify-center gap-2 rounded-full border border-slate-200 px-5 py-2.5 text-sm font-bold text-gray-700 hover:border-primary-200 hover:text-primary-700 ${state.step === 0 ? 'invisible' : ''}">
                    <i class="fa-solid fa-arrow-left text-xs"></i> ${escapeHtml(i18n.back)}
                </button>
                <button type="button" data-next class="inline-flex items-center justify-center gap-2 rounded-full bg-primary-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-primary-700">
                    ${state.step === steps.length - 1 ? escapeHtml(i18n.add_to_cart) : escapeHtml(i18n.continue)} <i class="fa-solid fa-arrow-right text-xs"></i>
                </button>
            </div>
        `;
    }

    function renderSummary() {
        const summary = document.getElementById('gift-summary');
        const mobile = document.getElementById('gift-mobile-bar');
        const packaging = bySlug(config.packaging, state.packaging_slug);
        const total = state.priced?.total ?? selectedLines().reduce((sum, line) => sum + Number(line.product.price || 0), 0) + Number(packaging?.price || 0);
        const disabled = !state.mainVariantId || state.isAdding || !state.priced || Boolean(state.error);
        const warnings = state.priced?.warnings || [];

        const body = `
            <div class="sticky top-24 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base font-extrabold text-gray-950">${escapeHtml(i18n.summary)}</h2>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">${selectedLines().length}/${config.maxItems}</span>
                </div>
                <div class="mt-4 space-y-3">${selectedSummaryHtml(true)}</div>
                <dl class="mt-4 space-y-2 text-sm text-gray-700">
                    <div class="flex justify-between"><dt>${escapeHtml(i18n.packaging)}</dt><dd class="font-semibold">${escapeHtml(packaging?.label || state.packaging_slug)}</dd></div>
                    <div class="flex justify-between border-t border-slate-100 pt-2 text-base font-extrabold text-gray-950"><dt>${escapeHtml(@js(__('storefront.common.total')))}</dt><dd class="text-primary-700">${money(total)}</dd></div>
                </dl>
                ${state.error ? `<p class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">${escapeHtml(state.error)}</p>` : ''}
                ${warnings.length ? `<div class="mt-3 space-y-2">${warnings.map((warning) => `<p class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">${escapeHtml(warning.message)}</p>`).join('')}</div>` : ''}
                <button type="button" data-add-cart class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-full bg-primary-600 px-5 py-3 text-sm font-bold text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50" ${disabled ? 'disabled' : ''}>
                    <i class="fa-solid fa-cart-plus text-xs"></i> ${state.isAdding ? escapeHtml(i18n.adding) : escapeHtml(i18n.add_box)}
                </button>
            </div>
        `;

        summary.innerHTML = body;
        mobile.innerHTML = `
            <div class="flex items-center gap-3">
                <button type="button" data-go-step="${Math.min(state.step + 1, steps.length - 1)}" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-slate-200 text-gray-700">
                    <i class="fa-solid fa-list-check"></i>
                </button>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-semibold text-gray-500">${escapeHtml(tr('selected_count', { count: selectedLines().length }))} • ${escapeHtml(packaging?.label || state.packaging_slug)}</p>
                    <p class="text-lg font-extrabold text-primary-700">${money(total)}</p>
                </div>
                <button type="button" data-next class="inline-flex shrink-0 items-center justify-center rounded-full bg-primary-600 px-4 py-2.5 text-sm font-bold text-white">
                    ${state.step === steps.length - 1 ? escapeHtml(i18n.cart) : escapeHtml(i18n.next)}
                </button>
            </div>
        `;
    }

    function render() {
        renderStepper();
        renderContent();
        renderSummary();
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char]));
    }

    function escapeAttr(value) {
        return escapeHtml(value).replace(/`/g, '&#096;');
    }

    let priceTimer = null;
    function schedulePrice() {
        clearTimeout(priceTimer);
        priceTimer = setTimeout(refreshPrice, 250);
    }

    async function refreshPrice() {
        if (!state.mainVariantId) {
            state.priced = null;
            state.error = null;
            renderSummary();
            return;
        }

        state.isPricing = true;
        try {
            const response = await fetch(config.routes.price, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(payload())
            });
            const data = await response.json();
            if (!response.ok) {
                const errors = data.errors ? Object.values(data.errors).flat() : [];
                state.error = errors[0] || data.message || i18n.pricing_failed;
                state.priced = null;
                renderSummary();
                return;
            }
            state.priced = data.gift_box;
            state.error = null;
            renderSummary();
        } catch (error) {
            state.error = @js(__('storefront.messages.network_error'));
            renderSummary();
        } finally {
            state.isPricing = false;
        }
    }

    function applyPreset(slug) {
        const preset = bySlug(config.presets, slug);
        if (!preset) return;
        state.recipient_type = preset.recipient_type || state.recipient_type;
        state.occasion = preset.occasion || state.occasion;
        state.budget_band = preset.budget_band || state.budget_band;
        state.packaging_slug = preset.packaging_slug || state.packaging_slug;
        state.step = 2;
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
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(payload())
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                const errors = data.errors ? Object.values(data.errors).flat() : [];
                state.error = errors[0] || data.message || i18n.add_failed;
                state.isAdding = false;
                render();
                return;
            }

            if (window.cartUi && typeof window.cartUi.updateBadges === 'function') {
                window.cartUi.updateBadges(data.cart_count || 0);
            }
            window.location.href = data.redirect_url || config.routes.cart;
        } catch (error) {
            state.error = @js(__('storefront.messages.network_error'));
            state.isAdding = false;
            render();
        }
    }

    document.addEventListener('click', function (event) {
        const option = event.target.closest('[data-option-type]');
        if (option) {
            state[option.getAttribute('data-option-type')] = option.getAttribute('data-option-value');
            schedulePrice();
            render();
            return;
        }

        const preset = event.target.closest('[data-preset]');
        if (preset) {
            applyPreset(preset.getAttribute('data-preset'));
            schedulePrice();
            render();
            return;
        }

        const stepButton = event.target.closest('[data-go-step]');
        if (stepButton) {
            state.step = Math.max(0, Math.min(steps.length - 1, Number(stepButton.getAttribute('data-go-step'))));
            render();
            return;
        }

        const main = event.target.closest('[data-select-main]');
        if (main) {
            state.mainVariantId = Number(main.getAttribute('data-select-main'));
            state.addonVariantIds.delete(state.mainVariantId);
            schedulePrice();
            render();
            return;
        }

        const addon = event.target.closest('[data-toggle-addon]');
        if (addon) {
            const variantId = Number(addon.getAttribute('data-toggle-addon'));
            if (state.addonVariantIds.has(variantId)) {
                state.addonVariantIds.delete(variantId);
            } else if (variantId !== Number(state.mainVariantId) && selectedLines().length < Number(config.maxItems || 4)) {
                state.addonVariantIds.add(variantId);
            }
            schedulePrice();
            render();
            return;
        }

        const variant = event.target.closest('[data-select-variant]');
        if (variant) {
            const variantId = Number(variant.getAttribute('data-select-variant'));
            const mode = variant.getAttribute('data-variant-mode');
            if (mode === 'main') {
                state.mainVariantId = variantId;
                state.addonVariantIds.delete(variantId);
            } else {
                const product = productForVariant(variantId);
                const alreadySelected = (product?.variants || []).some((item) => state.addonVariantIds.has(Number(item.id)));
                if (!alreadySelected && selectedLines().length >= Number(config.maxItems || 4)) {
                    return;
                }
                if (variantId === Number(state.mainVariantId)) {
                    return;
                }
                (product?.variants || []).forEach((item) => state.addonVariantIds.delete(Number(item.id)));
                state.addonVariantIds.add(variantId);
            }
            schedulePrice();
            render();
            return;
        }

        const packaging = event.target.closest('[data-package]');
        if (packaging) {
            state.packaging_slug = packaging.getAttribute('data-package');
            schedulePrice();
            render();
            return;
        }

        if (event.target.closest('[data-prev]')) {
            state.step = Math.max(0, state.step - 1);
            render();
            return;
        }

        if (event.target.closest('[data-next]')) {
            if (state.step === steps.length - 1) {
                addToCart();
            } else {
                state.step = Math.min(steps.length - 1, state.step + 1);
                render();
            }
            return;
        }

        if (event.target.closest('[data-add-cart]')) {
            addToCart();
        }
    });

    document.addEventListener('input', function (event) {
        if (event.target && event.target.id === 'gift-message') {
            state.message = event.target.value.slice(0, Number(config.messageMaxLength || 300));
            schedulePrice();
            renderSummary();
        }
    });

    render();
    schedulePrice();
}());
</script>
@endpush
