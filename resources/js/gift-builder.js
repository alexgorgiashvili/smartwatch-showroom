import { gsap } from 'gsap';
import { Flip } from 'gsap/Flip.js';
import { MotionPathPlugin } from 'gsap/MotionPathPlugin.js';

gsap.registerPlugin(Flip, MotionPathPlugin);

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

export function giftSlotAssignments(lines = [], maxItems = 4) {
	const limit = Math.max(1, Number(maxItems) || 1);
	const main = lines.find((line) => line?.role === 'main') || null;
	const addons = lines.filter((line) => line?.role === 'addon').slice(0, limit - 1);
	return [main, ...addons, ...Array(Math.max(0, limit - 1 - addons.length)).fill(null)];
}

export function isCurrentPriceRequest(requestId, currentRequestId) {
	return Number(requestId) === Number(currentRequestId);
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
	let priceRequestSequence = 0;
	let sheetMode = null;
	let sheetProduct = null;
	let lastFocused = null;
	let pushedSheetHistory = false;
	let activeContentStep = null;
	let activeProductSignature = '';

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
		const defaultId = Number((mode === 'main' && selected ? activeVariant?.id : selectedAddonId) || first(product.variants)?.id || 0);
		const facts = [product.note, product.short_description].filter(Boolean).slice(0, 2);

		return `<article class="gift-product-card ${selected ? 'is-selected' : ''} ${disabled ? 'is-disabled' : ''}" data-gift-product-card data-product-id="${Number(product.id)}" data-gift-product-mode="${escapeHtml(mode)}" data-flip-id="gift-product-${Number(product.id)}">
			<button type="button" class="gift-product-select" ${action}="${defaultId}" role="${mode === 'main' ? 'radio' : 'checkbox'}" aria-checked="${selected}" ${disabled ? 'disabled' : ''}>
				<div class="gift-product-media"><img data-gift-card-image data-variant-id="${Number(activeVariant?.id || 0)}" src="${escapeHtml(activeVariant?.thumbnail_image || product.image)}" alt="${escapeHtml(product.name)}" loading="lazy" decoding="async">${product.badge ? `<span>${escapeHtml(product.badge)}</span>` : ''}<i class="fa-solid ${selected ? 'fa-circle-check' : 'fa-plus'}" aria-hidden="true"></i></div>
				<div class="gift-product-copy"><h3>${escapeHtml(product.name)}</h3><p>${escapeHtml(product.price_formatted || money(product.price))}</p>${facts.map((fact) => `<small>${escapeHtml(fact)}</small>`).join('')}</div>
			</button>
			<button type="button" class="gift-product-detail-button" data-product-details="${Number(product.id)}">${escapeHtml(i18n.details)}<i class="fa-solid fa-chevron-up" aria-hidden="true"></i></button>
			${(product.variants || []).length ? `<fieldset class="gift-product-variants"><legend>${escapeHtml(i18n.choose_color)}</legend><div>${product.variants.map((variant) => {
				const pressed = Number(variant.id) === Number(activeVariant?.id) && selected;
				return `<button type="button" data-select-variant="${Number(variant.id)}" data-variant-mode="${mode}" aria-pressed="${pressed}" ${disabled ? 'disabled' : ''}>${variant.color_hex ? `<i style="--gift-color:${escapeHtml(variant.color_hex)}"></i>` : ''}<span>${escapeHtml(variant.color_name || variant.name)}</span></button>`;
			}).join('')}</div></fieldset>` : ''}
		</article>`;
	}

	function createNode(html) {
		const template = document.createElement('template');
		template.innerHTML = html.trim();
		return template.content.firstElementChild;
	}

	function runFlip(nodes, update, options = {}) {
		const elements = (nodes || []).filter(Boolean);
		const flipState = !reducedMotion && elements.length ? Flip.getState(elements) : null;
		update();
		if (flipState) {
			Flip.from(flipState, {
				duration: options.duration || 0.2,
				ease: 'power2.out',
				absolute: Boolean(options.absolute),
				stagger: options.stagger || 0,
			});
		}
	}

	function productCardState(product, mode) {
		const variantIds = (product.variants || []).map((variant) => Number(variant.id));
		const selectedAddonId = variantIds.find((id) => state.addonVariantIds.has(id));
		const selected = mode === 'main' ? variantIds.includes(Number(state.mainVariantId)) : Boolean(selectedAddonId);
		const activeVariant = variantForProduct(product, mode === 'main' ? state.mainVariantId : selectedAddonId);
		const disabled = mode === 'addon' && !selected && selectedLines().length >= Number(config.maxItems || 4);
		const defaultId = Number((mode === 'main' && selected ? activeVariant?.id : selectedAddonId) || first(product.variants)?.id || 0);
		return { selected, activeVariant, disabled, defaultId };
	}

	function patchProductCard(card, product, mode) {
		const { selected, activeVariant, disabled, defaultId } = productCardState(product, mode);
		card.classList.toggle('is-selected', selected);
		card.classList.toggle('is-disabled', disabled);
		const select = card.querySelector('.gift-product-select');
		delete select.dataset.selectMain;
		delete select.dataset.toggleAddon;
		if (mode === 'main') select.dataset.selectMain = String(defaultId);
		else select.dataset.toggleAddon = String(defaultId);
		select.setAttribute('aria-checked', String(selected));
		select.disabled = disabled;
		const image = card.querySelector('[data-gift-card-image]');
		const source = activeVariant?.thumbnail_image || activeVariant?.image || product.image || '';
		if (image && source && image.getAttribute('src') !== source) {
			image.setAttribute('src', source);
			image.dataset.variantId = String(Number(activeVariant?.id || 0));
			if (!reducedMotion) gsap.fromTo(image, { autoAlpha: 0.35, scale: 0.96 }, { autoAlpha: 1, scale: 1, duration: 0.2, ease: 'power2.out', overwrite: 'auto' });
		}
		const icon = card.querySelector('.gift-product-media > i');
		if (icon) icon.className = `fa-solid ${selected ? 'fa-circle-check' : 'fa-plus'}`;
		card.querySelectorAll('[data-select-variant]').forEach((button) => {
			const isActive = Number(button.dataset.selectVariant) === Number(activeVariant?.id) && selected;
			button.setAttribute('aria-pressed', String(isActive));
			button.disabled = disabled;
		});
	}

	function renderProductCards(products, mode) {
		const grid = document.getElementById('gift-product-grid');
		if (!grid) return;
		const signature = `${mode}:${mode === 'main' ? state.budget_band : ''}:${products.map((product) => Number(product.id)).join(',')}`;
		if (!products.length) {
			if (activeProductSignature !== signature) {
				grid.innerHTML = mode === 'main' ? emptyMainHtml() : `<p class="gift-builder-empty">${escapeHtml(i18n.no_addons)}</p>`;
				activeProductSignature = signature;
			}
			return;
		}

		Array.from(grid.children).filter((node) => !node.matches('[data-gift-product-card]')).forEach((node) => node.remove());
		const existing = new Map(Array.from(grid.querySelectorAll('[data-gift-product-card]')).map((card) => [Number(card.dataset.productId), card]));
		if (signature === activeProductSignature && existing.size === products.length) {
			products.forEach((product) => {
				const card = existing.get(Number(product.id));
				if (card) patchProductCard(card, product, mode);
			});
			return;
		}
		const priorCards = Array.from(existing.values());
		runFlip(priorCards, () => {
			products.forEach((product, index) => {
				let card = existing.get(Number(product.id));
				if (!card || card.dataset.giftProductMode !== mode) {
					card = createNode(productCard(product, mode));
				}
				patchProductCard(card, product, mode);
				const current = grid.children[index];
				if (current !== card) grid.insertBefore(card, current || null);
				existing.delete(Number(product.id));
			});
			existing.forEach((card) => card.remove());
		}, { duration: 0.18, absolute: signature !== activeProductSignature });
		activeProductSignature = signature;
	}

	function patchLiveGiftBox() {
		const preview = document.getElementById('gift-live-preview');
		if (!preview) return;
		const lines = selectedLines();
		const maxItems = Number(config.maxItems || 4);
		const box = preview.querySelector('[data-gift-live-box]');
		if (!box) return;
		box.dataset.packaging = state.packaging_slug || 'standard';
		box.classList.toggle('is-ready', Boolean(state.mainVariantId && state.step === steps.length - 1));
		preview.querySelector('[data-gift-live-count]').textContent = `${lines.length}/${maxItems}`;
		const assigned = giftSlotAssignments(lines, maxItems);
		Array.from({ length: maxItems }, (_, index) => {
			const slotName = index === 0 ? 'main' : `addon-${index}`;
			const slot = preview.querySelector(`[data-gift-slot="${slotName}"]`);
			if (!slot) return;
			const line = assigned[index];
			const media = slot.querySelector('.gift-live-slot-media');
			let image = media.querySelector('img');
			if (!line) {
				slot.classList.remove('is-filled');
				slot.removeAttribute('data-variant-id');
				if (image) image.remove();
				if (!media.querySelector('i')) media.innerHTML = `<i class="fa-solid ${index === 0 ? 'fa-clock' : 'fa-plus'}" aria-hidden="true"></i>`;
				return;
			}
			const source = line.variant?.thumbnail_image || line.variant?.image || line.product.image || '';
			const previousId = Number(slot.dataset.variantId || 0);
			slot.classList.add('is-filled');
			slot.dataset.variantId = String(Number(line.variant?.id || 0));
			media.querySelector('i')?.remove();
			if (!image) {
				image = document.createElement('img');
				image.loading = 'lazy';
				image.decoding = 'async';
				media.append(image);
			}
			image.dataset.flipId = `gift-variant-${Number(line.variant?.id || 0)}`;
			image.setAttribute('data-flip-id', `gift-variant-${Number(line.variant?.id || 0)}`);
			image.alt = line.product.name || '';
			if (source && image.getAttribute('src') !== source) {
				image.setAttribute('src', source);
				if (!reducedMotion) gsap.fromTo(image, { autoAlpha: 0.3, scale: 0.96 }, { autoAlpha: 1, scale: 1, duration: 0.2, ease: 'power2.out', overwrite: 'auto' });
			}
			if (!reducedMotion && previousId !== Number(line.variant?.id || 0)) {
				gsap.fromTo(slot, { scale: 0.92, autoAlpha: 0.55 }, { scale: 1, autoAlpha: 1, duration: 0.18, ease: 'back.out(1.5)', overwrite: 'auto' });
			}
		});
		const main = assigned[0];
		const addons = assigned.slice(1).filter(Boolean).length;
		preview.querySelector('[data-gift-live-status]').textContent = main
			? `${main.product.name}${addons ? `, ${addons} ${i18n.addon || 'add-on'}` : ''}`
			: i18n.choose_main;
	}

	function renderProgress() {
		const progress = document.getElementById('gift-progress');
		if (!progress.dataset.ready) {
			progress.innerHTML = `<div class="gift-progress-top"><span data-gift-progress-copy></span><strong data-gift-progress-label></strong></div>
				<div class="gift-progress-track" role="progressbar" aria-valuemin="1" aria-valuemax="${steps.length}" aria-label="${escapeHtml(i18n.progress_label)}"><span data-gift-progress-fill></span></div>
				<div class="gift-progress-steps">${steps.map((step, index) => `<button type="button" data-go-step="${index}" data-gift-progress-step="${index}"><span><i aria-hidden="true"></i></span><small>${escapeHtml(step.label)}</small></button>`).join('')}</div>`;
			progress.dataset.ready = 'true';
		}
		const percent = ((state.step + 1) / steps.length) * 100;
		progress.querySelector('[data-gift-progress-copy]').textContent = tr('step_of', { current: state.step + 1, total: steps.length });
		progress.querySelector('[data-gift-progress-label]').textContent = steps[state.step].label;
		const track = progress.querySelector('.gift-progress-track');
		track.setAttribute('aria-valuenow', String(state.step + 1));
		progress.querySelector('[data-gift-progress-fill]').style.transform = `scaleX(${percent / 100})`;
		progress.querySelectorAll('[data-gift-progress-step]').forEach((button, index) => {
			const isCurrent = index === state.step;
			const complete = index < state.step;
			button.classList.toggle('is-current', isCurrent);
			button.classList.toggle('is-complete', complete);
			button.toggleAttribute('aria-current', isCurrent);
			button.querySelector('i').className = `fa-solid ${complete ? 'fa-check' : steps[index].icon}`;
		});
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

	function renderStepShell(step) {
		const content = document.getElementById('gift-content');
		let body = '';
		if (step === 'main') {
			body = `<div class="gift-step-heading"><div><span>1</span><div><h2 tabindex="-1">${escapeHtml(i18n.watch_title)}</h2><p>${escapeHtml(i18n.watch_text)}</p></div></div><small data-gift-available-count></small></div>
				<fieldset class="gift-budget-filter"><legend>${escapeHtml(i18n.budget_filter)}</legend><div>${(config.budgetBands || []).map((option) => `<button type="button" data-budget="${escapeHtml(option.slug)}"><span>${escapeHtml(option.label)}</span> <small></small></button>`).join('')}</div></fieldset>
				<div id="gift-product-grid" class="gift-product-grid" role="radiogroup" aria-label="${escapeHtml(i18n.watch_title)}"></div>`;
		}
		if (step === 'addons') {
			body = `<div class="gift-step-heading"><div><span>2</span><div><h2 tabindex="-1">${escapeHtml(i18n.addons_title)}</h2><p>${escapeHtml(tr('addons_text_v2', { count: Math.max(0, Number(config.maxItems || 4) - 1) }))}</p></div></div><button type="button" data-skip-addons class="gift-skip-button">${escapeHtml(i18n.skip_addons)}<i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button></div>
				<p class="gift-selection-count" data-gift-addon-count><i class="fa-solid fa-box" aria-hidden="true"></i><span></span></p>
				<div id="gift-product-grid" class="gift-product-grid" role="group" aria-label="${escapeHtml(i18n.addons_title)}"></div>`;
		}
		if (step === 'finish') {
			body = `<div class="gift-step-heading"><div><span>3</span><div><h2 tabindex="-1">${escapeHtml(i18n.finish_title)}</h2><p>${escapeHtml(i18n.finish_text)}</p></div></div></div>
				<div class="gift-finish-grid"><fieldset class="gift-packaging-options"><legend>${escapeHtml(i18n.packaging)}</legend>${(config.packaging || []).map((option) => `<button type="button" data-package="${escapeHtml(option.slug)}"><span class="gift-package-icon"><i class="fa-solid ${option.slug === 'premium' ? 'fa-gem' : 'fa-gift'}" aria-hidden="true"></i></span><span><strong>${escapeHtml(option.label)}</strong><small>${escapeHtml(tr('capacity', { count: option.capacity_units }))}</small></span><b>${Number(option.price || 0) ? money(option.price) : escapeHtml(common.free || i18n.free)}</b><i class="fa-solid fa-circle-check" aria-hidden="true"></i></button>`).join('')}</fieldset>
				<div class="gift-card-message"><div class="gift-message-heading"><span><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i></span><div><h3>${escapeHtml(i18n.card_message)}</h3><p>${escapeHtml(i18n.message_optional_help)}</p></div></div><div class="gift-message-suggestions">${(i18n.message_suggestions || []).map((suggestion) => `<button type="button" data-message-suggestion="${escapeHtml(suggestion)}">${escapeHtml(suggestion)}</button>`).join('')}</div><label for="gift-message" class="sr-only">${escapeHtml(i18n.card_message)}</label><textarea id="gift-message" maxlength="${Number(config.messageMaxLength || 300)}" rows="5" placeholder="${escapeHtml(i18n.message_placeholder)}"></textarea><div><small>${escapeHtml(i18n.optional)}</small><span data-message-count></span></div><div class="gift-message-preview" aria-label="${escapeHtml(i18n.card_message)}"></div></div></div>
				${state.readyBox ? `<div data-discount-warning class="gift-discount-warning"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><p><strong>${escapeHtml(i18n.discount_changed_title)}</strong><span>${escapeHtml(i18n.discount_changed_text)}</span></p></div>` : ''}`;
		}
		content.innerHTML = `${body}<div class="gift-step-actions"><button type="button" data-prev class="gift-secondary-button"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i>${escapeHtml(i18n.back)}</button><button type="button" data-next class="gift-primary-button"><span></span><i aria-hidden="true"></i></button></div>`;
		activeContentStep = step;
		activeProductSignature = '';
	}

	function renderContent() {
		const step = steps[state.step].key;
		if (activeContentStep !== step) renderStepShell(step);
		const content = document.getElementById('gift-content');
		if (step === 'main') {
			const products = productsFor('main');
			content.querySelector('[data-gift-available-count]').textContent = tr('available_count', { count: products.length });
			content.querySelectorAll('.gift-budget-filter [data-budget]').forEach((button) => {
				const selected = button.dataset.budget === state.budget_band;
				button.classList.toggle('is-selected', selected);
				button.setAttribute('aria-pressed', String(selected));
				button.querySelector('small').textContent = productsForBudget(button.dataset.budget);
			});
			renderProductCards(products, 'main');
		}
		if (step === 'addons') {
			content.querySelector('[data-gift-addon-count] span').textContent = tr('addon_selected_count', { count: state.addonVariantIds.size, max: Math.max(0, Number(config.maxItems || 4) - 1) });
			renderProductCards(productsFor('addon'), 'addon');
		}
		if (step === 'finish') {
			content.querySelectorAll('[data-package]').forEach((button) => {
				const selected = button.dataset.package === state.packaging_slug;
				button.classList.toggle('is-selected', selected);
				button.setAttribute('aria-pressed', String(selected));
			});
			const message = content.querySelector('#gift-message');
			if (message && message.value !== state.message) message.value = state.message;
			content.querySelector('[data-message-count]').textContent = `${state.message.length}/${Number(config.messageMaxLength || 300)}`;
			content.querySelector('.gift-message-preview').textContent = state.message || i18n.message_placeholder;
			content.querySelector('[data-discount-warning]')?.classList.toggle('hidden', state.discountRetained);
		}
		const previous = content.querySelector('[data-prev]');
		previous.classList.toggle('is-invisible', state.step === 0);
		const next = content.querySelector('[data-next]');
		const isFinish = state.step === steps.length - 1;
		next.disabled = state.step === 0 ? !state.mainVariantId : (isFinish ? !canAddToCart() : false);
		next.querySelector('span').textContent = isFinish ? i18n.add_box : i18n.continue;
		next.querySelector('i').className = `fa-solid ${isFinish ? 'fa-cart-plus' : 'fa-arrow-right'}`;
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

	function canAddToCart() {
		return Boolean(state.mainVariantId && state.priced && !state.error && !state.isAdding && !state.isPricing);
	}

	function summaryLineKey(line) {
		return `${line.role}-${Number(line.product?.id || 0)}`;
	}

	function summaryLineNode(line) {
		return createNode(`<div class="gift-summary-line" data-gift-summary-line data-summary-key="${escapeHtml(summaryLineKey(line))}" data-variant-id="${Number(line.variant?.id || 0)}" data-flip-id="gift-summary-${escapeHtml(summaryLineKey(line))}"><img loading="lazy" alt=""><div><p></p><small></small></div><strong></strong></div>`);
	}

	function patchSummaryLine(node, line) {
		const image = node.querySelector('img');
		const source = line.variant?.thumbnail_image || line.variant?.image || line.product.image || '';
		if (source && image.getAttribute('src') !== source) {
			image.setAttribute('src', source);
			if (!reducedMotion) gsap.fromTo(image, { autoAlpha: 0.35, scale: 0.96 }, { autoAlpha: 1, scale: 1, duration: 0.18, ease: 'power2.out', overwrite: 'auto' });
		}
		node.dataset.variantId = String(Number(line.variant?.id || 0));
		image.alt = line.product.name || '';
		node.querySelector('p').textContent = line.product.name || '';
		node.querySelector('small').textContent = `${line.role === 'main' ? i18n.main_gift : i18n.addon} · ${line.variant?.color_name || line.variant?.name || ''}`;
		node.querySelector('strong').textContent = line.product.price_formatted || money(line.product.price);
	}

	function ensureSummaryShell() {
		const summary = document.getElementById('gift-summary');
		if (summary.dataset.ready) return summary;
		summary.innerHTML = `<div class="gift-summary-card"><header><div><p>${escapeHtml(i18n.your_box)}</p><h2>${escapeHtml(i18n.summary)}</h2></div><span data-gift-summary-count></span></header>
			<div class="gift-summary-lines" data-gift-summary-lines></div>
			<dl data-gift-summary-totals><div><dt>${escapeHtml(i18n.products_total)}</dt><dd data-gift-items-total></dd></div><div><dt>${escapeHtml(i18n.packaging)} <small data-gift-packaging-label></small></dt><dd data-gift-packaging-total></dd></div><div class="is-discount" data-gift-discount-row hidden><dt>${escapeHtml(i18n.gift_discount)}</dt><dd data-gift-discount-total></dd></div><div class="is-total"><dt>${escapeHtml(common.total || i18n.total)}</dt><dd data-gift-total></dd></div></dl>
			<div class="gift-summary-live" data-gift-summary-live role="status" aria-live="polite"></div><div data-gift-summary-error role="alert" aria-live="assertive"></div>
			<button type="button" data-add-cart class="gift-primary-button"><i class="fa-solid fa-cart-plus" aria-hidden="true"></i><span></span></button><p class="gift-summary-secure"><i class="fa-solid fa-lock" aria-hidden="true"></i>${escapeHtml(i18n.secure_checkout)}</p></div>`;
		summary.dataset.ready = 'true';
		return summary;
	}

	function patchSummaryLines(host) {
		const lines = selectedLines();
		if (!lines.length) {
			if (!host.querySelector('.gift-summary-empty')) host.innerHTML = `<p class="gift-summary-empty">${escapeHtml(i18n.choose_main)}</p>`;
			return;
		}
		Array.from(host.children).filter((node) => !node.matches('[data-gift-summary-line]')).forEach((node) => node.remove());
		const existing = new Map(Array.from(host.querySelectorAll('[data-gift-summary-line]')).map((node) => [node.dataset.summaryKey, node]));
		runFlip(Array.from(existing.values()), () => {
			lines.forEach((line, index) => {
				const key = summaryLineKey(line);
				let node = existing.get(key);
				if (!node) node = summaryLineNode(line);
				patchSummaryLine(node, line);
				const current = host.children[index];
				if (current !== node) host.insertBefore(node, current || null);
				existing.delete(key);
			});
			existing.forEach((node) => node.remove());
		}, { duration: 0.2, absolute: true });
	}

	function ensureMobileBar() {
		const mobile = document.getElementById('gift-mobile-bar');
		if (mobile.dataset.ready) return mobile;
		mobile.innerHTML = `<button type="button" class="gift-mini-box" data-summary-open aria-label="${escapeHtml(i18n.my_box)}"><i class="fa-solid fa-gift" aria-hidden="true"></i><span data-gift-mobile-count></span></button><div><span>${escapeHtml(i18n.my_box)}</span><strong data-gift-mobile-total></strong></div><button type="button" class="gift-primary-button" data-gift-mobile-action><span></span><i aria-hidden="true"></i></button>`;
		mobile.dataset.ready = 'true';
		return mobile;
	}

	function patchMobileBar(values, canAdd) {
		const mobile = ensureMobileBar();
		const lines = selectedLines();
		const firstImage = lines[0]?.variant?.thumbnail_image || lines[0]?.variant?.image || lines[0]?.product?.image || '';
		const miniBox = mobile.querySelector('.gift-mini-box');
		let image = miniBox.querySelector('img');
		if (firstImage) {
			miniBox.querySelector('i')?.remove();
			if (!image) { image = document.createElement('img'); image.alt = ''; miniBox.prepend(image); }
			if (image.getAttribute('src') !== firstImage) image.setAttribute('src', firstImage);
		} else {
			image?.remove();
			if (!miniBox.querySelector('i')) miniBox.insertAdjacentHTML('afterbegin', '<i class="fa-solid fa-gift" aria-hidden="true"></i>');
		}
		mobile.dataset.packaging = state.packaging_slug;
		mobile.querySelector('[data-gift-mobile-count]').textContent = `${lines.length}/${Number(config.maxItems || 4)}`;
		mobile.querySelector('[data-gift-mobile-total]').textContent = money(values.total);
		const action = mobile.querySelector('[data-gift-mobile-action]');
		const isFinish = state.step === steps.length - 1;
		action.toggleAttribute('data-add-cart', isFinish);
		action.toggleAttribute('data-next', !isFinish);
		action.disabled = isFinish ? !canAdd : (state.step === 0 && !state.mainVariantId);
		action.querySelector('span').textContent = isFinish ? i18n.add_to_cart : i18n.next;
		action.querySelector('i').className = `fa-solid ${isFinish ? 'fa-cart-plus' : 'fa-arrow-right'}`;
	}

	function renderSummary() {
		const summary = ensureSummaryShell();
		const values = totals();
		const canAdd = canAddToCart();
		const warnings = Array.isArray(state.priced?.warnings) ? state.priced.warnings : [];
		summary.querySelector('[data-gift-summary-count]').textContent = `${selectedLines().length}/${Number(config.maxItems || 4)}`;
		patchSummaryLines(summary.querySelector('[data-gift-summary-lines]'));
		summary.querySelector('[data-gift-items-total]').textContent = money(values.itemSubtotal);
		summary.querySelector('[data-gift-packaging-label]').textContent = values.packaging?.label || '';
		summary.querySelector('[data-gift-packaging-total]').textContent = values.packagingAmount ? money(values.packagingAmount) : (common.free || i18n.free);
		const discountRow = summary.querySelector('[data-gift-discount-row]');
		discountRow.hidden = values.discount <= 0;
		summary.querySelector('[data-gift-discount-total]').textContent = values.discount > 0 ? `−${money(values.discount)}` : '';
		summary.querySelector('[data-gift-total]').textContent = money(values.total);
		const live = summary.querySelector('[data-gift-summary-live]');
		live.innerHTML = `${state.isPricing ? `<p class="is-loading"><i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i>${escapeHtml(i18n.rechecking_price)}</p>` : ''}${warnings.map((warning) => `<p class="is-warning"><i class="fa-solid fa-circle-info" aria-hidden="true"></i>${escapeHtml(warning.message || warning)}</p>`).join('')}`;
		const error = summary.querySelector('[data-gift-summary-error]');
		error.innerHTML = state.error ? `<p class="gift-summary-error is-error"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>${escapeHtml(state.error)} <button type="button" data-stock-recover>${escapeHtml(i18n.try_alternative)}</button></p>` : '';
		const add = summary.querySelector('[data-add-cart]');
		add.disabled = !canAdd;
		add.querySelector('span').textContent = state.isAdding ? i18n.adding : i18n.add_box;
		patchMobileBar(values, canAdd);
		if (sheetMode === 'summary') renderSheetBody();
	}

	function render(trackStep = false) {
		renderPresetOverview();
		renderProgress();
		renderContent();
		patchLiveGiftBox();
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

	function flyToBox(source, variantId) {
		if (reducedMotion || !source) return;
		const target = document.querySelector(`[data-gift-slot][data-variant-id="${Number(variantId)}"] .gift-live-slot-media`);
		const image = source.querySelector('img');
		if (!target || !image) return;
		const from = image.getBoundingClientRect();
		const to = target.getBoundingClientRect();
		if (!from.width || !from.height || !to.width || !to.height) return;
		const clone = image.cloneNode();
		Object.assign(clone.style, { position: 'fixed', zIndex: '180', left: `${from.left}px`, top: `${from.top}px`, width: `${from.width}px`, height: `${from.height}px`, borderRadius: '16px', objectFit: 'contain', pointerEvents: 'none', transformOrigin: 'center center' });
		document.body.appendChild(clone);
		const deltaX = to.left + (to.width / 2) - from.left - (from.width / 2);
		const deltaY = to.top + (to.height / 2) - from.top - (from.height / 2);
		const lift = Math.min(150, Math.max(56, Math.abs(deltaX) * 0.18));
		gsap.fromTo(clone, { x: 0, y: 0, scale: 1, autoAlpha: 0.98 }, {
			duration: 0.4,
			motionPath: { path: [{ x: 0, y: 0 }, { x: deltaX * 0.48, y: deltaY - lift }, { x: deltaX, y: deltaY }], curviness: 1.25 },
			scale: Math.min(0.68, Math.max(0.28, to.width / from.width)),
			autoAlpha: 0.1,
			ease: 'power2.inOut',
			onComplete: () => clone.remove(),
		});
	}

	function schedulePrice() {
		window.clearTimeout(priceTimer);
		priceRequestSequence += 1;
		priceRequest?.abort();
		priceRequest = null;
		if (!state.mainVariantId) {
			state.priced = null;
			state.error = null;
			state.isPricing = false;
			renderSummary();
			return;
		}
		state.isPricing = true;
		state.error = null;
		renderSummary();
		const requestId = priceRequestSequence;
		priceTimer = window.setTimeout(() => refreshPrice(requestId), 220);
	}

	async function refreshPrice(requestId = priceRequestSequence) {
		if (!isCurrentPriceRequest(requestId, priceRequestSequence)) return;
		if (!state.mainVariantId) return;
		const controller = new AbortController();
		priceRequest = controller;
		try {
			const response = await fetch(config.routes.price, { method: 'POST', credentials: 'same-origin', signal: controller.signal, headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(payload()) });
			const data = await response.json().catch(() => ({}));
			if (!isCurrentPriceRequest(requestId, priceRequestSequence) || controller !== priceRequest) return;
			if (!response.ok) throw new Error((data.errors ? Object.values(data.errors).flat()[0] : null) || data.message || i18n.pricing_failed);
			state.priced = data.gift_box;
			state.error = null;
			if (typeof state.priced?.discount_retained === 'boolean') state.discountRetained = state.priced.discount_retained;
		} catch (error) {
			if (error.name === 'AbortError' || !isCurrentPriceRequest(requestId, priceRequestSequence) || controller !== priceRequest) return;
			state.priced = null;
			state.error = error.message || i18n.network_error;
			analytics('gift_flow_error', { error_stage: 'pricing', box_slug: state.readyBox?.slug });
		} finally {
			if (!isCurrentPriceRequest(requestId, priceRequestSequence) || controller !== priceRequest) return;
			state.isPricing = false;
			priceRequest = null;
			renderPresetOverview();
			renderSummary();
		}
	}

	async function showCompletion(redirectUrl) {
		const completion = root.querySelector('[data-gift-completion]');
		completion.hidden = false;
		analytics('gift_completion_shown', { trigger: 'server_success' });
		if (!reducedMotion && 'vibrate' in navigator) navigator.vibrate(20);
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

	function sheetFocusSelector(element) {
		if (!element?.matches) return null;
		if (element.matches('[data-remove-variant]')) return `[data-remove-variant="${Number(element.dataset.removeVariant)}"]`;
		if (element.matches('[data-go-step]')) return `[data-go-step="${Number(element.dataset.goStep)}"]`;
		if (element.matches('[data-undo-remove]')) return '[data-undo-remove]';
		if (element.matches('.gift-builder-sheet-panel [data-builder-sheet-close]')) return '.gift-builder-sheet-panel [data-builder-sheet-close]';
		return null;
	}

	function restoreSheetFocus(sheet, selector) {
		if (!selector) return;
		requestAnimationFrame(() => {
			const control = sheet.querySelector(selector)
				|| sheet.querySelector('[data-undo-remove]')
				|| sheet.querySelector('.gift-builder-sheet-panel [data-builder-sheet-close]');
			if (control && !control.disabled) control.focus({ preventScroll: true });
		});
	}

	function renderSheetBody() {
		const sheet = document.getElementById('gift-builder-sheet');
		const focusSelector = sheet.contains(document.activeElement) ? sheetFocusSelector(document.activeElement) : null;
		const title = sheet.querySelector('[data-builder-sheet-title]');
		const body = sheet.querySelector('[data-builder-sheet-body]');
		const status = sheet.querySelector('[data-builder-sheet-status]');
		status.innerHTML = state.removed ? `${escapeHtml(i18n.remove)} <button type="button" data-undo-remove>${escapeHtml(i18n.undo)}</button>` : '';
		if (sheetMode === 'details' && sheetProduct) {
			title.textContent = i18n.product_details;
			body.innerHTML = `<div class="gift-product-detail"><img src="${escapeHtml(sheetProduct.image)}" alt="${escapeHtml(sheetProduct.name)}"><h3>${escapeHtml(sheetProduct.name)}</h3><strong>${escapeHtml(sheetProduct.price_formatted || money(sheetProduct.price))}</strong>${sheetProduct.note ? `<p>${escapeHtml(sheetProduct.note)}</p>` : ''}${sheetProduct.short_description ? `<p>${escapeHtml(sheetProduct.short_description)}</p>` : ''}<button type="button" class="gift-primary-button" data-builder-sheet-close>${escapeHtml(common.close || i18n.close_summary)}</button></div>`;
			restoreSheetFocus(sheet, focusSelector);
			return;
		}
		title.textContent = i18n.my_box;
		const slots = Array.from({ length: Number(config.maxItems || 4) }, (_, index) => selectedLines()[index]);
		body.innerHTML = `<div class="gift-sheet-slots">${slots.map((line) => line ? `<span><img src="${escapeHtml(line.variant?.thumbnail_image || line.product.image)}" alt="${escapeHtml(line.product.name)}"></span>` : '<span class="is-empty"><i class="fa-solid fa-plus" aria-hidden="true"></i></span>').join('')}</div><div class="gift-summary-lines">${summaryLinesHtml({ editable: true })}</div>${totalsHtml()}<div class="gift-sheet-message"><strong>${escapeHtml(i18n.card_message)}</strong><p>${escapeHtml(state.message || i18n.optional)}</p></div>`;
		restoreSheetFocus(sheet, focusSelector);
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
		root.querySelector('.gift-builder-intro')?.setAttribute('inert', '');
		root.querySelector('.gift-builder-workspace')?.setAttribute('inert', '');
		document.getElementById('gift-mobile-bar')?.setAttribute('inert', '');
		if (pushHistory) { history.pushState({ giftBuilderSheet: true }, ''); pushedSheetHistory = true; }
		requestAnimationFrame(() => sheet.querySelector('.gift-builder-sheet-panel [data-builder-sheet-close]')?.focus());
		if (mode === 'summary') analytics('gift_builder_summary_open', { trigger: 'mini_box' });
		if (mode === 'details') analytics('gift_product_detail_open', { product_id: product?.id });
	}

	function closeSheet({ fromPopState = false } = {}) {
		if (!sheetMode) return;
		const sheet = document.getElementById('gift-builder-sheet');
		sheet.classList.remove('is-open');
		sheet.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('gift-modal-open');
		root.querySelector('.gift-builder-intro')?.removeAttribute('inert');
		root.querySelector('.gift-builder-workspace')?.removeAttribute('inert');
		document.getElementById('gift-mobile-bar')?.removeAttribute('inert');
		sheetMode = null;
		sheetProduct = null;
		if (lastFocused?.isConnected) lastFocused.focus?.();
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
		markPresetChanged();
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
		if (budget) { state.budget_band = budget.dataset.budget; const current = productForVariant(state.mainVariantId); if (current && !budgetMatches(current)) { state.mainVariantId = null; markPresetChanged(); } saveDraft(); analytics('gift_item_selected', { item_type: 'budget', budget_band: state.budget_band }); render(); schedulePrice(); return; }
		const sourceCard = event.target.closest('.gift-product-card');
		const main = event.target.closest('[data-select-main]');
		if (main) { const id = Number(main.dataset.selectMain); const next = productForVariant(id); if (productForVariant(state.mainVariantId)?.id !== next?.id) markPresetChanged(); state.mainVariantId = id; (next?.variants || []).forEach((variant) => state.addonVariantIds.delete(Number(variant.id))); state.removed = null; render(); flyToBox(sourceCard, id); saveDraft(); analytics('gift_item_selected', { item_type: 'main', product_id: next?.id, variant_id: id, selected: true }); schedulePrice(); return; }
		const addon = event.target.closest('[data-toggle-addon]');
		if (addon) { const id = Number(addon.dataset.toggleAddon); const product = productForVariant(id); const existing = (product?.variants || []).map((variant) => Number(variant.id)).find((variantId) => state.addonVariantIds.has(variantId)); const canAdd = selectedLines().length < Number(config.maxItems || 4); if (!existing && !canAdd) return; if (existing) state.addonVariantIds.delete(existing); else state.addonVariantIds.add(id); markPresetChanged(); state.removed = null; render(); if (!existing) flyToBox(sourceCard, id); saveDraft(); analytics('gift_item_selected', { item_type: 'addon', product_id: product?.id, variant_id: id, selected: !existing }); schedulePrice(); return; }
		const variant = event.target.closest('[data-select-variant]');
		if (variant) { const id = Number(variant.dataset.selectVariant); const product = productForVariant(id); if (variant.dataset.variantMode === 'main') state.mainVariantId = id; else { (product?.variants || []).forEach((item) => state.addonVariantIds.delete(Number(item.id))); state.addonVariantIds.add(id); } state.removed = null; render(); saveDraft(); schedulePrice(); return; }
		const packaging = event.target.closest('[data-package]');
		if (packaging) { if (state.packaging_slug !== packaging.dataset.package) markPresetChanged(); state.packaging_slug = packaging.dataset.package; state.removed = null; render(); saveDraft(); analytics('gift_item_selected', { item_type: 'packaging', packaging_slug: state.packaging_slug, selected: true }); schedulePrice(); return; }
		const suggestion = event.target.closest('[data-message-suggestion]');
		if (suggestion) { state.message = suggestion.dataset.messageSuggestion.slice(0, Number(config.messageMaxLength || 300)); render(); saveDraft(); requestAnimationFrame(() => document.getElementById('gift-message')?.focus()); return; }
		const details = event.target.closest('[data-product-details]');
		if (details) { openSheet('details', (config.products || []).find((product) => Number(product.id) === Number(details.dataset.productDetails))); return; }
		if (event.target.closest('[data-summary-open]')) { openSheet('summary'); return; }
		if (event.target.closest('[data-builder-sheet-close]')) { closeSheet(); return; }
		const remove = event.target.closest('[data-remove-variant]');
		if (remove) { const id = Number(remove.dataset.removeVariant); const role = id === Number(state.mainVariantId) ? 'main' : 'addon'; state.removed = { id, role }; if (role === 'main') state.mainVariantId = null; else state.addonVariantIds.delete(id); markPresetChanged(); render(); saveDraft(); schedulePrice(); return; }
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

	document.addEventListener('focusin', (event) => {
		if (!sheetMode) return;
		const sheet = document.getElementById('gift-builder-sheet');
		if (sheet?.contains(event.target)) return;
		sheet?.querySelector('.gift-builder-sheet-panel [data-builder-sheet-close]')?.focus({ preventScroll: true });
	});

	document.addEventListener('keydown', (event) => {
		const mainChoice = event.target.closest('[data-select-main]');
		if (mainChoice && ['ArrowLeft', 'ArrowUp', 'ArrowRight', 'ArrowDown'].includes(event.key)) {
			const choices = Array.from(document.querySelectorAll('#gift-product-grid [data-select-main]:not([disabled])'));
			const current = choices.indexOf(mainChoice);
			if (current >= 0 && choices.length > 1) {
				event.preventDefault();
				const direction = ['ArrowLeft', 'ArrowUp'].includes(event.key) ? -1 : 1;
				const next = choices[(current + direction + choices.length) % choices.length];
				next.focus();
				next.click();
			}
			return;
		}
		const colorChoice = event.target.closest('[data-select-variant]');
		if (colorChoice && ['ArrowLeft', 'ArrowUp', 'ArrowRight', 'ArrowDown'].includes(event.key)) {
			const choices = Array.from(colorChoice.closest('.gift-product-variants')?.querySelectorAll('[data-select-variant]:not([disabled])') || []);
			const current = choices.indexOf(colorChoice);
			if (current >= 0 && choices.length > 1) {
				event.preventDefault();
				const direction = ['ArrowLeft', 'ArrowUp'].includes(event.key) ? -1 : 1;
				const next = choices[(current + direction + choices.length) % choices.length];
				next.focus();
				next.click();
			}
			return;
		}
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
