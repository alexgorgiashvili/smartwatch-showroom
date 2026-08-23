const root = document.querySelector('[data-gift-builder-experience]');

export const GIFT_DRAFT_KEY = 'gift-builder:draft:v2';
export const GIFT_DRAFT_TTL = 24 * 60 * 60 * 1000;

export function readGiftDraft(storage, now = Date.now()) {
	try {
		const draft = JSON.parse(storage?.getItem(GIFT_DRAFT_KEY) || 'null');
		if (!draft || draft.version !== 2 || !Number.isFinite(draft.savedAt) || now - draft.savedAt > GIFT_DRAFT_TTL) {
			storage?.removeItem(GIFT_DRAFT_KEY);
			return null;
		}
		return draft;
	} catch (_) {
		return null;
	}
}

export function safeAnalyticsPayload(payload = {}) {
	const allowed = new Set([
		'renderer', 'trigger', 'elapsed_ms', 'priority', 'result_type', 'recovery_action',
		'experiment_variant', 'step_number', 'step_name', 'gift_mode', 'box_slug',
		'item_type', 'product_id', 'variant_id', 'selected', 'budget_band', 'packaging_slug',
		'value', 'currency', 'num_items', 'error_stage',
	]);
	return Object.fromEntries(Object.entries(payload).filter(([key, value]) => (
		allowed.has(key) && value !== '' && value !== null && typeof value !== 'undefined'
	)));
}

export function chooseBuilderRestore({ hasDirectSelection = false, recommendation = null, draft = null } = {}) {
	if (hasDirectSelection) return { apply: null, offerDraft: Boolean(draft) };
	if (recommendation) return { apply: 'recommendation', offerDraft: false };
	if (draft) return { apply: 'draft', offerDraft: false };
	return { apply: null, offerDraft: false };
}

if (root) initializeBuilder();

function initializeBuilder() {
	const configElement = document.getElementById('gift-builder-config');
	let pageConfig = {};
	try {
		pageConfig = JSON.parse(configElement?.textContent || '{}');
	} catch (_) {
		return;
	}

	const config = pageConfig.builder || {};
	const i18n = pageConfig.i18n || {};
	const common = pageConfig.common || {};
	const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
	const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	const first = (items) => (Array.isArray(items) && items.length ? items[0] : null);
	const bySlug = (items, slug) => (items || []).find((item) => item.slug === slug) || null;
	const money = (value) => `${Number(value || 0).toFixed(2)} ₾`;
	const tr = (key, replacements = {}) => Object.entries(replacements).reduce(
		(value, [name, replacement]) => String(value).replace(`:${name}`, replacement),
		i18n[key] || key,
	);
	const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
		'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
	}[character]));
	const analytics = (name, payload = {}) => {
		const safe = safeAnalyticsPayload(payload);
		if (window.storefrontAnalytics?.trackCustom) window.storefrontAnalytics.trackCustom(name, safe);
		else window.storefrontAnalytics?.track?.(name, safe);
	};

	const initialReadyBox = typeof config.initial?.ready_box === 'object' && config.initial.ready_box
		? config.initial.ready_box
		: (config.initial?.ready_box ? { slug: config.initial.ready_box, title: i18n.preset_box } : null);
	const hasDirectSelection = Boolean(initialReadyBox || config.initial?.selected_variant_id);
	const storedDraft = readGiftDraft(window.sessionStorage);
	const pendingRecommendation = (() => {
		if (hasDirectSelection) return null;
		try {
			return JSON.parse(window.sessionStorage.getItem('gift-builder:recommendation:v1') || 'null');
		} catch (_) {
			return null;
		}
	})();
	const restoreDecision = chooseBuilderRestore({ hasDirectSelection, recommendation: pendingRecommendation, draft: storedDraft });

	const state = {
		step: initialReadyBox ? 2 : 0,
		recipient_type: config.initial?.recipient_type || 'other',
		occasion: config.initial?.occasion || 'just_because',
		budget_band: bySlug(config.budgetBands, config.initial?.budget_band)?.slug || first(config.budgetBands)?.slug || 'under_100',
		packaging_slug: config.initial?.packaging_slug || first(config.packaging)?.slug || 'standard',
		message: '',
		mainVariantId: config.initial?.selected_variant_id ? Number(config.initial.selected_variant_id) : null,
		addonVariantIds: new Set((config.initial?.addon_variant_ids || []).map(Number)),
		readyBox: initialReadyBox,
		discountRetained: initialReadyBox ? config.initial?.ready_box?.discount_retained !== false : false,
		priced: null,
		error: null,
		isPricing: false,
		isAdding: false,
		removed: null,
	};

	const steps = [
		{ key: 'main', label: i18n.step_watch, icon: 'fa-clock' },
		{ key: 'addons', label: i18n.step_addons, icon: 'fa-puzzle-piece' },
		{ key: 'finish', label: i18n.step_finish, icon: 'fa-gift' },
	];
	const trackedSteps = new Set();
	let priceTimer = 0;
	let priceRequest = null;
	let sheetMode = null;
	let sheetProduct = null;
	let lastFocused = null;
	let pushedSheetHistory = false;

	const productForVariant = (variantId) => {
		const id = Number(variantId);
		return (config.products || []).find((product) => (product.variants || []).some((variant) => Number(variant.id) === id)) || null;
	};
	const variantForProduct = (product, variantId) => (
		(product?.variants || []).find((variant) => Number(variant.id) === Number(variantId)) || first(product?.variants || [])
	);
	const selectedLines = () => {
		const lines = [];
		const main = productForVariant(state.mainVariantId);
		if (main) lines.push({ product: main, variant: variantForProduct(main, state.mainVariantId), role: 'main' });
		state.addonVariantIds.forEach((variantId) => {
			const product = productForVariant(variantId);
			if (product) lines.push({ product, variant: variantForProduct(product, variantId), role: 'addon' });
		});
		return lines;
	};
	const payload = () => ({
		recipient_type: state.recipient_type,
		occasion: state.occasion,
		budget_band: state.budget_band,
		packaging_slug: state.packaging_slug,
		message: state.message,
		ready_box_slug: state.readyBox?.slug || null,
		items: selectedLines().map((line) => ({ variant_id: Number(line.variant.id), quantity: 1, role: line.role })),
	});
	const roleMatches = (product, role) => (role === 'main'
		? ['main', 'both'].includes(product.role)
		: ['addon', 'both'].includes(product.role));
	const budgetMatches = (product) => {
		if (state.budget_band === 'all') return true;
		const band = bySlug(config.budgetBands, state.budget_band);
		if (!band) return true;
		if (band.min != null && Number(product.price) < Number(band.min)) return false;
		return band.max == null || Number(product.price) <= Number(band.max);
	};
	const productsFor = (role) => {
		const mainProductId = productForVariant(state.mainVariantId)?.id;
		return (config.products || [])
			.filter((product) => (product.variants || []).length && roleMatches(product, role))
			.filter((product) => role !== 'addon' || Number(product.id) !== Number(mainProductId))
			.filter((product) => role !== 'main' || budgetMatches(product));
	};

	function serializeDraft() {
		return {
			version: 2,
			savedAt: Date.now(),
			step: state.step,
			budget_band: state.budget_band,
			recipient_type: state.recipient_type,
			occasion: state.occasion,
			mainVariantId: state.mainVariantId,
			addonVariantIds: Array.from(state.addonVariantIds),
			packaging_slug: state.packaging_slug,
			message: state.message,
		};
	}

	function saveDraft() {
		if (!state.mainVariantId && !state.message) return;
		window.sessionStorage.setItem(GIFT_DRAFT_KEY, JSON.stringify(serializeDraft()));
	}

	function applyDraft(draft) {
		if (!draft) return;
		state.step = Math.max(0, Math.min(steps.length - 1, Number(draft.step || 0)));
		state.budget_band = draft.budget_band || state.budget_band;
		state.recipient_type = draft.recipient_type || state.recipient_type;
		state.occasion = draft.occasion || state.occasion;
		state.mainVariantId = Number(draft.mainVariantId) || null;
		state.addonVariantIds = new Set((draft.addonVariantIds || []).map(Number));
		state.packaging_slug = draft.packaging_slug || state.packaging_slug;
		state.message = String(draft.message || '').slice(0, Number(config.messageMaxLength || 300));
		analytics('gift_builder_draft_restore', { trigger: 'session' });
	}

	function applyRecommendation(recommendation) {
		const start = recommendation?.custom_start || recommendation;
		if (!start?.main_variant_id) return;
		state.mainVariantId = Number(start.main_variant_id);
		state.addonVariantIds = new Set((start.addon_variant_ids || []).map(Number));
		state.packaging_slug = start.packaging_slug || state.packaging_slug;
		state.budget_band = start.budget_band || state.budget_band;
		state.step = 0;
		state.readyBox = null;
		state.discountRetained = false;
		window.sessionStorage.removeItem('gift-builder:recommendation:v1');
		saveDraft();
		render(true);
		schedulePrice();
	}

	if (restoreDecision.apply === 'recommendation') applyRecommendation(pendingRecommendation);
	else if (restoreDecision.apply === 'draft') applyDraft(storedDraft);

	function renderDraftOffer() {
		const host = document.getElementById('gift-draft-restore');
		if (!host || !restoreDecision.offerDraft || !storedDraft) return;
		host.hidden = false;
		host.innerHTML = `<p><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>${escapeHtml(i18n.draft_available)}</p>
			<div><button type="button" data-restore-draft>${escapeHtml(i18n.restore_draft)}</button><button type="button" data-dismiss-draft>${escapeHtml(i18n.dismiss_draft)}</button></div>`;
	}

	function markPresetChanged() {
		if (!state.readyBox) return;
		state.discountRetained = false;
	}

	function productCard(product, mode) {
		const variantIds = (product.variants || []).map((variant) => Number(variant.id));
		const selectedAddonId = variantIds.find((id) => state.addonVariantIds.has(id));
		const selected = mode === 'main' ? variantIds.includes(Number(state.mainVariantId)) : Boolean(selectedAddonId);
		const activeVariant = variantForProduct(product, mode === 'main' ? state.mainVariantId : selectedAddonId);
		const disabled = mode === 'addon' && !selected && selectedLines().length >= Number(config.maxItems || 4);
		const action = mode === 'main' ? 'data-select-main' : 'data-toggle-addon';
		const defaultId = Number(selectedAddonId || first(product.variants)?.id || 0);
		const facts = [product.note, product.short_description].filter(Boolean).slice(0, 2);

		return `<article class="gift-product-card ${selected ? 'is-selected' : ''} ${disabled ? 'is-disabled' : ''}">
			<button type="button" class="gift-product-select" ${action}="${defaultId}" role="${mode === 'main' ? 'radio' : 'checkbox'}" aria-checked="${selected}" ${disabled ? 'disabled' : ''}>
				<div class="gift-product-media"><img src="${escapeHtml(activeVariant?.thumbnail_image || product.image)}" alt="${escapeHtml(product.name)}" loading="lazy" decoding="async">${product.badge ? `<span>${escapeHtml(product.badge)}</span>` : ''}<i class="fa-solid ${selected ? 'fa-circle-check' : 'fa-plus'}" aria-hidden="true"></i></div>
				<div class="gift-product-copy"><h3>${escapeHtml(product.name)}</h3><p>${escapeHtml(product.price_formatted || money(product.price))}</p>${facts.map((fact) => `<small>${escapeHtml(fact)}</small>`).join('')}</div>
			</button>
			<button type="button" class="gift-product-detail-button" data-product-details="${Number(product.id)}">${escapeHtml(i18n.details)}<i class="fa-solid fa-chevron-up" aria-hidden="true"></i></button>
			${(product.variants || []).length ? `<fieldset class="gift-product-variants"><legend>${escapeHtml(i18n.choose_color)}</legend><div>${product.variants.map((variant) => {
				const pressed = Number(variant.id) === Number(activeVariant?.id) && selected;
				return `<button type="button" data-select-variant="${Number(variant.id)}" data-variant-mode="${mode}" aria-pressed="${pressed}" ${disabled ? 'disabled' : ''}>${variant.color_hex ? `<i style="--gift-color:${escapeHtml(variant.color_hex)}"></i>` : ''}<span>${escapeHtml(variant.color_name || variant.name)}</span></button>`;
			}).join('')}</div></fieldset>` : ''}
		</article>`;
	}

	function renderProgress() {
		const progress = document.getElementById('gift-progress');
		const percent = ((state.step + 1) / steps.length) * 100;
		progress.innerHTML = `<div class="gift-progress-top"><span>${escapeHtml(tr('step_of', { current: state.step + 1, total: steps.length }))}</span><strong>${escapeHtml(steps[state.step].label)}</strong></div>
			<div class="gift-progress-track" role="progressbar" aria-valuemin="1" aria-valuemax="${steps.length}" aria-valuenow="${state.step + 1}" aria-label="${escapeHtml(i18n.progress_label)}"><span style="width:${percent}%"></span></div>
			<div class="gift-progress-steps">${steps.map((step, index) => `<button type="button" data-go-step="${index}" class="${index === state.step ? 'is-current' : index < state.step ? 'is-complete' : ''}" ${index === state.step ? 'aria-current="step"' : ''}><span><i class="fa-solid ${index < state.step ? 'fa-check' : step.icon}" aria-hidden="true"></i></span><small>${escapeHtml(step.label)}</small></button>`).join('')}</div>`;
	}

	function renderPresetOverview() {
		const overview = document.getElementById('gift-preset-overview');
		if (!state.readyBox) { overview.hidden = true; overview.innerHTML = ''; return; }
		overview.hidden = false;
		overview.innerHTML = `<div class="gift-preset-icon"><i class="fa-solid fa-box-open" aria-hidden="true"></i></div><div class="gift-preset-copy"><p>${escapeHtml(i18n.editing_ready_box)}</p><h2>${escapeHtml(state.readyBox.title || i18n.preset_box)}</h2><span class="${state.discountRetained ? 'is-retained' : 'is-removed'}"><i class="fa-solid ${state.discountRetained ? 'fa-tag' : 'fa-circle-info'}" aria-hidden="true"></i>${escapeHtml(state.discountRetained ? i18n.preset_discount_retained : i18n.preset_discount_removed)}</span></div><div class="gift-preset-actions"><button type="button" data-go-step="0">${escapeHtml(i18n.change_watch)}</button><button type="button" data-go-step="1">${escapeHtml(i18n.change_addons)}</button><button type="button" data-go-step="2">${escapeHtml(i18n.change_package)}</button></div>`;
	}

	function emptyMainHtml() {
		const currentIndex = (config.budgetBands || []).findIndex((band) => band.slug === state.budget_band);
		const nextBand = (config.budgetBands || [])[currentIndex + 1];
		return `<div class="gift-builder-empty"><p>${escapeHtml(i18n.no_products)}</p><div><button type="button" data-budget="all">${escapeHtml(i18n.all_watches || 'All watches')}</button>${nextBand ? `<button type="button" data-budget="${escapeHtml(nextBand.slug)}">${escapeHtml(nextBand.label)}</button>` : ''}</div></div>`;
	}

	function renderContent() {
		const content = document.getElementById('gift-content');
		const step = steps[state.step].key;
		let html = '';
		if (step === 'main') {
			const products = productsFor('main');
			html = `<div class="gift-step-heading"><div><span>1</span><div><h2 tabindex="-1">${escapeHtml(i18n.watch_title)}</h2><p>${escapeHtml(i18n.watch_text)}</p></div></div><small>${escapeHtml(tr('available_count', { count: products.length }))}</small></div>
				<fieldset class="gift-budget-filter"><legend>${escapeHtml(i18n.budget_filter)}</legend><div>${(config.budgetBands || []).map((option) => `<button type="button" data-budget="${escapeHtml(option.slug)}" aria-pressed="${option.slug === state.budget_band}" class="${option.slug === state.budget_band ? 'is-selected' : ''}">${escapeHtml(option.label)} <small>${productsForBudget(option.slug)}</small></button>`).join('')}</div></fieldset>
				<div class="gift-product-grid" role="radiogroup" aria-label="${escapeHtml(i18n.watch_title)}">${products.length ? products.map((product) => productCard(product, 'main')).join('') : emptyMainHtml()}</div>`;
		}
		if (step === 'addons') {
			const products = productsFor('addon');
			html = `<div class="gift-step-heading"><div><span>2</span><div><h2 tabindex="-1">${escapeHtml(i18n.addons_title)}</h2><p>${escapeHtml(tr('addons_text_v2', { count: Math.max(0, Number(config.maxItems || 4) - 1) }))}</p></div></div><button type="button" data-skip-addons class="gift-skip-button">${escapeHtml(i18n.skip_addons)}<i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button></div><p class="gift-selection-count"><i class="fa-solid fa-box" aria-hidden="true"></i>${escapeHtml(tr('addon_selected_count', { count: state.addonVariantIds.size, max: Math.max(0, Number(config.maxItems || 4) - 1) }))}</p><div class="gift-product-grid" role="group" aria-label="${escapeHtml(i18n.addons_title)}">${products.length ? products.map((product) => productCard(product, 'addon')).join('') : `<p class="gift-builder-empty">${escapeHtml(i18n.no_addons)}</p>`}</div>`;
		}
		if (step === 'finish') {
			html = `<div class="gift-step-heading"><div><span>3</span><div><h2 tabindex="-1">${escapeHtml(i18n.finish_title)}</h2><p>${escapeHtml(i18n.finish_text)}</p></div></div></div><div class="gift-finish-grid"><fieldset class="gift-packaging-options"><legend>${escapeHtml(i18n.packaging)}</legend>${(config.packaging || []).map((option) => `<button type="button" data-package="${escapeHtml(option.slug)}" aria-pressed="${option.slug === state.packaging_slug}" class="${option.slug === state.packaging_slug ? 'is-selected' : ''}"><span class="gift-package-icon"><i class="fa-solid ${option.slug === 'premium' ? 'fa-gem' : 'fa-gift'}" aria-hidden="true"></i></span><span><strong>${escapeHtml(option.label)}</strong><small>${escapeHtml(tr('capacity', { count: option.capacity_units }))}</small></span><b>${Number(option.price || 0) ? money(option.price) : escapeHtml(common.free || i18n.free)}</b><i class="fa-solid fa-circle-check" aria-hidden="true"></i></button>`).join('')}</fieldset><div class="gift-card-message"><div class="gift-message-heading"><span><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i></span><div><h3>${escapeHtml(i18n.card_message)}</h3><p>${escapeHtml(i18n.message_optional_help)}</p></div></div><div class="gift-message-suggestions">${(i18n.message_suggestions || []).map((suggestion) => `<button type="button" data-message-suggestion="${escapeHtml(suggestion)}">${escapeHtml(suggestion)}</button>`).join('')}</div><label for="gift-message" class="sr-only">${escapeHtml(i18n.card_message)}</label><textarea id="gift-message" maxlength="${Number(config.messageMaxLength || 300)}" rows="5" placeholder="${escapeHtml(i18n.message_placeholder)}">${escapeHtml(state.message)}</textarea><div><small>${escapeHtml(i18n.optional)}</small><span data-message-count>${state.message.length}/${Number(config.messageMaxLength || 300)}</span></div><div class="gift-message-preview" aria-label="${escapeHtml(i18n.card_message)}">${escapeHtml(state.message || i18n.message_placeholder)}</div></div></div>${state.readyBox ? `<div data-discount-warning class="gift-discount-warning ${state.discountRetained ? 'hidden' : ''}"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><p><strong>${escapeHtml(i18n.discount_changed_title)}</strong><span>${escapeHtml(i18n.discount_changed_text)}</span></p></div>` : ''}`;
		}
		content.innerHTML = `${html}<div class="gift-step-actions"><button type="button" data-prev class="gift-secondary-button ${state.step === 0 ? 'is-invisible' : ''}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i>${escapeHtml(i18n.back)}</button><button type="button" data-next class="gift-primary-button" ${state.step === 0 && !state.mainVariantId ? 'disabled' : ''}>${escapeHtml(state.step === steps.length - 1 ? i18n.add_box : i18n.continue)}<i class="fa-solid ${state.step === steps.length - 1 ? 'fa-cart-plus' : 'fa-arrow-right'}" aria-hidden="true"></i></button></div>`;
	}

	function productsForBudget(slug) {
		const previous = state.budget_band;
		state.budget_band = slug;
		const count = productsFor('main').length;
		state.budget_band = previous;
		return count;
	}

	function totals() {
		const packaging = bySlug(config.packaging, state.packaging_slug);
		const fallbackSubtotal = selectedLines().reduce((sum, line) => sum + Number(line.product.price || 0), 0);
		const itemSubtotal = Number(state.priced?.items_subtotal ?? fallbackSubtotal);
		const packagingAmount = Number(state.priced?.packaging_amount ?? packaging?.price ?? 0);
		const discount = Number(state.priced?.discount_amount || state.priced?.discount?.amount || 0);
		return { packaging, itemSubtotal, packagingAmount, discount, total: Number(state.priced?.total ?? (itemSubtotal + packagingAmount - discount)) };
	}

	function summaryLinesHtml({ editable = false } = {}) {
		if (!selectedLines().length) return `<p class="gift-summary-empty">${escapeHtml(i18n.choose_main)}</p>`;
		return selectedLines().map((line) => `<div class="gift-summary-line"><img src="${escapeHtml(line.variant?.thumbnail_image || line.product.image)}" alt="" loading="lazy"><div><p>${escapeHtml(line.product.name)}</p><small>${escapeHtml(line.role === 'main' ? i18n.main_gift : i18n.addon)} · ${escapeHtml(line.variant?.color_name || line.variant?.name || '')}</small></div><strong>${escapeHtml(line.product.price_formatted || money(line.product.price))}</strong>${editable ? `<div class="gift-summary-line-actions"><button type="button" data-go-step="${line.role === 'main' ? 0 : 1}" aria-label="${escapeHtml(i18n.edit)}"><i class="fa-solid fa-pen" aria-hidden="true"></i></button><button type="button" data-remove-variant="${Number(line.variant.id)}" aria-label="${escapeHtml(i18n.remove)}"><i class="fa-solid fa-trash" aria-hidden="true"></i></button></div>` : ''}</div>`).join('');
	}

	function totalsHtml() {
		const values = totals();
		return `<dl><div><dt>${escapeHtml(i18n.products_total)}</dt><dd>${money(values.itemSubtotal)}</dd></div><div><dt>${escapeHtml(i18n.packaging)} <small>${escapeHtml(values.packaging?.label || '')}</small></dt><dd>${values.packagingAmount ? money(values.packagingAmount) : escapeHtml(common.free || i18n.free)}</dd></div>${values.discount > 0 ? `<div class="is-discount"><dt>${escapeHtml(i18n.gift_discount)}</dt><dd>−${money(values.discount)}</dd></div>` : ''}<div class="is-total"><dt>${escapeHtml(common.total || i18n.total)}</dt><dd>${money(values.total)}</dd></div></dl>`;
	}

	function renderSummary() {
		const summary = document.getElementById('gift-summary');
		const mobile = document.getElementById('gift-mobile-bar');
		const values = totals();
		const canAdd = Boolean(state.mainVariantId && state.priced && !state.error && !state.isAdding && !state.isPricing);
		const warnings = Array.isArray(state.priced?.warnings) ? state.priced.warnings : [];
		summary.innerHTML = `<div class="gift-summary-card"><header><div><p>${escapeHtml(i18n.your_box)}</p><h2>${escapeHtml(i18n.summary)}</h2></div><span>${selectedLines().length}/${Number(config.maxItems || 4)}</span></header><div class="gift-summary-lines">${summaryLinesHtml()}</div>${totalsHtml()}<div class="gift-summary-live" aria-live="polite">${state.isPricing ? `<p class="is-loading"><i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i>${escapeHtml(i18n.rechecking_price)}</p>` : ''}${state.error ? `<p class="is-error"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>${escapeHtml(state.error)} <button type="button" data-stock-recover>${escapeHtml(i18n.try_alternative)}</button></p>` : ''}${warnings.map((warning) => `<p class="is-warning"><i class="fa-solid fa-circle-info" aria-hidden="true"></i>${escapeHtml(warning.message || warning)}</p>`).join('')}</div><button type="button" data-add-cart class="gift-primary-button" ${canAdd ? '' : 'disabled'}><i class="fa-solid fa-cart-plus" aria-hidden="true"></i>${escapeHtml(state.isAdding ? i18n.adding : i18n.add_box)}</button><p class="gift-summary-secure"><i class="fa-solid fa-lock" aria-hidden="true"></i>${escapeHtml(i18n.secure_checkout)}</p></div>`;
		const firstImage = selectedLines()[0]?.variant?.thumbnail_image || selectedLines()[0]?.product?.image || '';
		mobile.dataset.packaging = state.packaging_slug;
		mobile.innerHTML = `<button type="button" class="gift-mini-box" data-summary-open aria-label="${escapeHtml(i18n.my_box)}">${firstImage ? `<img src="${escapeHtml(firstImage)}" alt="">` : '<i class="fa-solid fa-gift" aria-hidden="true"></i>'}<span>${selectedLines().length}/${Number(config.maxItems || 4)}</span></button><div><span>${escapeHtml(i18n.my_box)}</span><strong>${money(values.total)}</strong></div><button type="button" ${state.step === steps.length - 1 ? 'data-add-cart' : 'data-next'} class="gift-primary-button" ${(state.step === 0 && !state.mainVariantId) || (state.step === steps.length - 1 && !canAdd) ? 'disabled' : ''}>${escapeHtml(state.step === steps.length - 1 ? i18n.add_to_cart : i18n.next)}<i class="fa-solid ${state.step === steps.length - 1 ? 'fa-cart-plus' : 'fa-arrow-right'}" aria-hidden="true"></i></button>`;
		if (sheetMode === 'summary') renderSheetBody();
	}

	function render(trackStep = false) {
		renderPresetOverview();
		renderProgress();
		renderContent();
		renderSummary();
		if (trackStep || !trackedSteps.has(state.step)) {
			trackedSteps.add(state.step);
			analytics('gift_builder_step_view', { step_number: state.step + 1, step_name: steps[state.step].key, gift_mode: state.readyBox ? 'preset' : 'custom', box_slug: state.readyBox?.slug });
		}
	}

	function goStep(index, focus = true) {
		const update = () => { state.step = Math.max(0, Math.min(steps.length - 1, Number(index))); render(true); saveDraft(); };
		if (document.startViewTransition && !reducedMotion) document.startViewTransition(update);
		else update();
		if (focus) requestAnimationFrame(() => { document.querySelector('#gift-content h2')?.focus({ preventScroll: true }); document.getElementById('gift-builder-app')?.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' }); });
	}

	function flyToBox(source) {
		if (reducedMotion || !source) return;
		const target = document.querySelector('.gift-mini-box');
		const image = source.querySelector('img');
		if (!target || !image) return;
		const from = image.getBoundingClientRect();
		const to = target.getBoundingClientRect();
		const clone = image.cloneNode();
		Object.assign(clone.style, { position: 'fixed', zIndex: '180', left: `${from.left}px`, top: `${from.top}px`, width: `${from.width}px`, height: `${from.height}px`, borderRadius: '16px', objectFit: 'cover', pointerEvents: 'none' });
		document.body.appendChild(clone);
		clone.animate([{ transform: 'translate(0,0) scale(1)', opacity: 0.95 }, { transform: `translate(${to.left - from.left}px,${to.top - from.top}px) scale(0.18)`, opacity: 0.2 }], { duration: 300, easing: 'cubic-bezier(.2,.8,.2,1)' }).finished.finally(() => clone.remove());
	}

	function schedulePrice() {
		window.clearTimeout(priceTimer);
		priceTimer = window.setTimeout(refreshPrice, 220);
	}

	async function refreshPrice() {
		priceRequest?.abort();
		if (!state.mainVariantId) { state.priced = null; state.error = null; state.isPricing = false; renderSummary(); return; }
		priceRequest = new AbortController();
		state.isPricing = true;
		state.error = null;
		renderSummary();
		try {
			const response = await fetch(config.routes.price, { method: 'POST', credentials: 'same-origin', signal: priceRequest.signal, headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(payload()) });
			const data = await response.json().catch(() => ({}));
			if (!response.ok) throw new Error((data.errors ? Object.values(data.errors).flat()[0] : null) || data.message || i18n.pricing_failed);
			state.priced = data.gift_box;
			state.error = null;
			if (typeof state.priced?.discount_retained === 'boolean') state.discountRetained = state.priced.discount_retained;
		} catch (error) {
			if (error.name === 'AbortError') return;
			state.priced = null;
			state.error = error.message || i18n.network_error;
			analytics('gift_flow_error', { error_stage: 'pricing', box_slug: state.readyBox?.slug });
		} finally {
			state.isPricing = false;
			renderPresetOverview();
			renderSummary();
		}
	}

	async function showCompletion(redirectUrl) {
		const completion = root.querySelector('[data-gift-completion]');
		completion.hidden = false;
		analytics('gift_completion_shown', { trigger: 'server_success' });
		if ('vibrate' in navigator) navigator.vibrate(20);
		if (!reducedMotion && !navigator.connection?.saveData) {
			import('canvas-confetti').then(({ default: confetti }) => confetti({ particleCount: 30, spread: 52, ticks: 70, scalar: 0.72, disableForReducedMotion: true, useWorker: true })).catch(() => {});
			await new Promise((resolve) => window.setTimeout(resolve, 650));
		}
		window.location.assign(redirectUrl || config.routes.cart);
	}

	async function addToCart() {
		if (!state.mainVariantId || state.isAdding) return;
		if (!state.priced || state.error) { schedulePrice(); return; }
		state.isAdding = true;
		renderSummary();
		try {
			const response = await fetch(config.routes.addToCart, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(payload()) });
			const data = await response.json().catch(() => ({}));
			if (!response.ok || data.success === false) throw new Error((data.errors ? Object.values(data.errors).flat()[0] : null) || data.message || i18n.add_failed);
			window.cartUi?.updateBadges?.(data.cart_count || 0);
			const lines = selectedLines();
			const value = Number(data.gift_box?.total || state.priced?.total || 0);
			analytics('gift_add_to_cart_success', { box_slug: state.readyBox?.slug, gift_mode: state.readyBox ? 'customized_preset' : 'custom', value, currency: 'GEL', num_items: lines.length });
			window.sessionStorage.removeItem(GIFT_DRAFT_KEY);
			await showCompletion(data.redirect_url);
		} catch (error) {
			state.error = error.message || i18n.network_error;
			state.isAdding = false;
			analytics('gift_flow_error', { error_stage: 'add_to_cart', box_slug: state.readyBox?.slug });
			renderSummary();
		}
	}

	function renderSheetBody() {
		const sheet = document.getElementById('gift-builder-sheet');
		const title = sheet.querySelector('[data-builder-sheet-title]');
		const body = sheet.querySelector('[data-builder-sheet-body]');
		const status = sheet.querySelector('[data-builder-sheet-status]');
		status.innerHTML = state.removed ? `${escapeHtml(i18n.remove)} <button type="button" data-undo-remove>${escapeHtml(i18n.undo)}</button>` : '';
		if (sheetMode === 'details' && sheetProduct) {
			title.textContent = i18n.product_details;
			body.innerHTML = `<div class="gift-product-detail"><img src="${escapeHtml(sheetProduct.image)}" alt="${escapeHtml(sheetProduct.name)}"><h3>${escapeHtml(sheetProduct.name)}</h3><strong>${escapeHtml(sheetProduct.price_formatted || money(sheetProduct.price))}</strong>${sheetProduct.note ? `<p>${escapeHtml(sheetProduct.note)}</p>` : ''}${sheetProduct.short_description ? `<p>${escapeHtml(sheetProduct.short_description)}</p>` : ''}<button type="button" class="gift-primary-button" data-builder-sheet-close>${escapeHtml(common.close || i18n.close_summary)}</button></div>`;
			return;
		}
		title.textContent = i18n.my_box;
		const slots = Array.from({ length: Number(config.maxItems || 4) }, (_, index) => selectedLines()[index]);
		body.innerHTML = `<div class="gift-sheet-slots">${slots.map((line) => line ? `<span><img src="${escapeHtml(line.variant?.thumbnail_image || line.product.image)}" alt="${escapeHtml(line.product.name)}"></span>` : '<span class="is-empty"><i class="fa-solid fa-plus" aria-hidden="true"></i></span>').join('')}</div><div class="gift-summary-lines">${summaryLinesHtml({ editable: true })}</div>${totalsHtml()}<div class="gift-sheet-message"><strong>${escapeHtml(i18n.card_message)}</strong><p>${escapeHtml(state.message || i18n.optional)}</p></div>`;
	}

	function openSheet(mode, product = null, pushHistory = true) {
		const sheet = document.getElementById('gift-builder-sheet');
		sheetMode = mode;
		sheetProduct = product;
		lastFocused = document.activeElement;
		renderSheetBody();
		sheet.classList.add('is-open');
		sheet.setAttribute('aria-hidden', 'false');
		document.body.classList.add('gift-modal-open');
		if (pushHistory) { history.pushState({ giftBuilderSheet: true }, ''); pushedSheetHistory = true; }
		requestAnimationFrame(() => sheet.querySelector('.gift-builder-sheet-panel')?.focus());
		if (mode === 'summary') analytics('gift_builder_summary_open', { trigger: 'mini_box' });
		if (mode === 'details') analytics('gift_product_detail_open', { product_id: product?.id });
	}

	function closeSheet({ fromPopState = false } = {}) {
		if (!sheetMode) return;
		const sheet = document.getElementById('gift-builder-sheet');
		sheet.classList.remove('is-open');
		sheet.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('gift-modal-open');
		sheetMode = null;
		sheetProduct = null;
		lastFocused?.focus?.();
		lastFocused = null;
		if (!fromPopState && pushedSheetHistory) history.back();
		pushedSheetHistory = false;
	}

	function recoverStock() {
		const selected = new Set(selectedLines().map((line) => Number(line.product.id)));
		const replacement = (config.products || []).find((product) => !selected.has(Number(product.id)) && roleMatches(product, state.addonVariantIds.size ? 'addon' : 'main'));
		const variantId = Number(first(replacement?.variants || [])?.id || 0);
		if (!variantId) return;
		if (state.addonVariantIds.size) {
			const last = Array.from(state.addonVariantIds).pop();
			state.addonVariantIds.delete(last);
			state.addonVariantIds.add(variantId);
		} else state.mainVariantId = variantId;
		state.error = null;
		analytics('gift_stock_recovery', { recovery_action: 'alternative' });
		saveDraft();
		render();
		schedulePrice();
	}

	document.addEventListener('click', (event) => {
		const step = event.target.closest('[data-go-step]');
		if (step) { closeSheet(); goStep(step.dataset.goStep); return; }
		const budget = event.target.closest('[data-budget]');
		if (budget) { state.budget_band = budget.dataset.budget; const current = productForVariant(state.mainVariantId); if (current && !budgetMatches(current)) state.mainVariantId = null; markPresetChanged(); saveDraft(); analytics('gift_item_selected', { item_type: 'budget', budget_band: state.budget_band }); render(); schedulePrice(); return; }
		const sourceCard = event.target.closest('.gift-product-card');
		const main = event.target.closest('[data-select-main]');
		if (main) { const id = Number(main.dataset.selectMain); const next = productForVariant(id); if (productForVariant(state.mainVariantId)?.id !== next?.id) markPresetChanged(); state.mainVariantId = id; (next?.variants || []).forEach((variant) => state.addonVariantIds.delete(Number(variant.id))); render(); flyToBox(sourceCard); saveDraft(); analytics('gift_item_selected', { item_type: 'main', product_id: next?.id, variant_id: id, selected: true }); schedulePrice(); return; }
		const addon = event.target.closest('[data-toggle-addon]');
		if (addon) { const id = Number(addon.dataset.toggleAddon); const product = productForVariant(id); const existing = (product?.variants || []).map((variant) => Number(variant.id)).find((variantId) => state.addonVariantIds.has(variantId)); if (existing) state.addonVariantIds.delete(existing); else if (selectedLines().length < Number(config.maxItems || 4)) state.addonVariantIds.add(id); markPresetChanged(); render(); flyToBox(sourceCard); saveDraft(); analytics('gift_item_selected', { item_type: 'addon', product_id: product?.id, variant_id: id, selected: !existing }); schedulePrice(); return; }
		const variant = event.target.closest('[data-select-variant]');
		if (variant) { const id = Number(variant.dataset.selectVariant); const product = productForVariant(id); if (variant.dataset.variantMode === 'main') state.mainVariantId = id; else { (product?.variants || []).forEach((item) => state.addonVariantIds.delete(Number(item.id))); state.addonVariantIds.add(id); } render(); saveDraft(); schedulePrice(); return; }
		const packaging = event.target.closest('[data-package]');
		if (packaging) { if (state.packaging_slug !== packaging.dataset.package) markPresetChanged(); state.packaging_slug = packaging.dataset.package; render(); saveDraft(); analytics('gift_item_selected', { item_type: 'packaging', packaging_slug: state.packaging_slug, selected: true }); schedulePrice(); return; }
		const suggestion = event.target.closest('[data-message-suggestion]');
		if (suggestion) { state.message = suggestion.dataset.messageSuggestion.slice(0, Number(config.messageMaxLength || 300)); render(); saveDraft(); requestAnimationFrame(() => document.getElementById('gift-message')?.focus()); return; }
		const details = event.target.closest('[data-product-details]');
		if (details) { openSheet('details', (config.products || []).find((product) => Number(product.id) === Number(details.dataset.productDetails))); return; }
		if (event.target.closest('[data-summary-open]')) { openSheet('summary'); return; }
		if (event.target.closest('[data-builder-sheet-close]')) { closeSheet(); return; }
		const remove = event.target.closest('[data-remove-variant]');
		if (remove) { const id = Number(remove.dataset.removeVariant); const role = id === Number(state.mainVariantId) ? 'main' : 'addon'; state.removed = { id, role }; if (role === 'main') state.mainVariantId = null; else state.addonVariantIds.delete(id); render(); saveDraft(); schedulePrice(); return; }
		if (event.target.closest('[data-undo-remove]') && state.removed) { if (state.removed.role === 'main') state.mainVariantId = state.removed.id; else state.addonVariantIds.add(state.removed.id); state.removed = null; render(); saveDraft(); schedulePrice(); return; }
		if (event.target.closest('[data-stock-recover]')) { recoverStock(); return; }
		if (event.target.closest('[data-restore-draft]')) { applyDraft(storedDraft); document.getElementById('gift-draft-restore').hidden = true; render(true); schedulePrice(); return; }
		if (event.target.closest('[data-dismiss-draft]')) { window.sessionStorage.removeItem(GIFT_DRAFT_KEY); document.getElementById('gift-draft-restore').hidden = true; return; }
		if (event.target.closest('[data-skip-addons]')) { goStep(2); return; }
		if (event.target.closest('[data-prev]')) { goStep(state.step - 1); return; }
		if (event.target.closest('[data-next]')) { if (state.step === steps.length - 1) addToCart(); else goStep(state.step + 1); return; }
		if (event.target.closest('[data-add-cart]')) addToCart();
	});

	document.addEventListener('input', (event) => {
		if (event.target.id !== 'gift-message') return;
		state.message = event.target.value.slice(0, Number(config.messageMaxLength || 300));
		document.querySelector('[data-message-count]').textContent = `${state.message.length}/${Number(config.messageMaxLength || 300)}`;
		document.querySelector('.gift-message-preview').textContent = state.message || i18n.message_placeholder;
		saveDraft();
	});

	document.addEventListener('keydown', (event) => {
		if (!sheetMode) return;
		if (event.key === 'Escape') { event.preventDefault(); closeSheet(); return; }
		if (event.key !== 'Tab') return;
		const panel = document.querySelector('.gift-builder-sheet-panel');
		const focusable = Array.from(panel.querySelectorAll('button:not([disabled]), a[href], input:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'));
		if (!focusable.length) return;
		const firstControl = focusable[0];
		const lastControl = focusable[focusable.length - 1];
		if (event.shiftKey && document.activeElement === firstControl) { event.preventDefault(); lastControl.focus(); }
		else if (!event.shiftKey && document.activeElement === lastControl) { event.preventDefault(); firstControl.focus(); }
	});

	window.addEventListener('popstate', () => { if (sheetMode) closeSheet({ fromPopState: true }); });
	window.addEventListener('gift:recommendation', (event) => applyRecommendation(event.detail));
	window.visualViewport?.addEventListener('resize', () => {
		const keyboardOpen = window.innerHeight - window.visualViewport.height > 140;
		document.getElementById('gift-mobile-bar')?.classList.toggle('is-keyboard-open', keyboardOpen);
	}, { passive: true });

	renderDraftOffer();
	render(true);
	schedulePrice();
}
