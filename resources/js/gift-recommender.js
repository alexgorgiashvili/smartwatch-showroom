const recommender = document.querySelector('[data-gift-recommender]');

if (recommender) {
	const copy = (() => {
		try {
			return JSON.parse(recommender.querySelector('[data-gift-recommendation-copy]')?.textContent || '{}');
		} catch (_) {
			return {};
		}
	})();
	const form = recommender.querySelector('[data-gift-recommendation-form]');
	const panel = recommender.querySelector('.gift-recommender-panel');
	const result = recommender.querySelector('[data-gift-recommendation-result]');
	const status = recommender.querySelector('[data-gift-recommendation-status]');
	const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
	let lastFocused = null;
	let currentRecommendation = null;
	let shownProductIds = [];
	let request = null;
	let historyPushed = false;

	const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
		'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
	}[character]));
	const analytics = (name, payload = {}) => {
		const safe = Object.fromEntries(Object.entries(payload).filter(([key, value]) => (
			['priority', 'result_type', 'trigger'].includes(key) && value !== null && value !== ''
		)));
		if (window.storefrontAnalytics?.trackCustom) window.storefrontAnalytics.trackCustom(name, safe);
		else window.storefrontAnalytics?.track?.(name, safe);
	};

	function open() {
		lastFocused = document.activeElement;
		recommender.classList.add('is-open');
		recommender.setAttribute('aria-hidden', 'false');
		document.body.classList.add('gift-modal-open');
		history.pushState({ giftRecommender: true }, '');
		historyPushed = true;
		requestAnimationFrame(() => panel.focus());
	}

	function close({ fromPopState = false } = {}) {
		if (!recommender.classList.contains('is-open')) return;
		recommender.classList.remove('is-open');
		recommender.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('gift-modal-open');
		lastFocused?.focus?.();
		lastFocused = null;
		if (!fromPopState && historyPushed) history.back();
		historyPushed = false;
	}

	function productImages(products = []) {
		return `<div class="gift-recommendation-products">${products.slice(0, 3).map((product) => `<img src="${escapeHtml(product.image)}" alt="${escapeHtml(product.name)}">`).join('')}</div>`;
	}

	function renderResponse(data) {
		currentRecommendation = data;
		const ready = data.ready_box;
		const custom = data.custom_start;
		const cards = [];
		if (ready) {
			cards.push(`<article class="gift-recommendation-card is-ready"><p>${escapeHtml(copy.ready)}</p><h3>${escapeHtml(ready.title)}</h3>${productImages(ready.products || ready.items)}<strong>${escapeHtml(ready.total_formatted || '')}</strong><a href="${escapeHtml(ready.builder_url)}" class="gift-primary-button">${escapeHtml(copy.apply)}</a></article>`);
		}
		if (custom) {
			cards.push(`<article class="gift-recommendation-card"><p>${escapeHtml(copy.custom)}</p><h3>${escapeHtml(custom.title || copy.custom)}</h3>${productImages(custom.products)}<span>${escapeHtml(custom.reason || '')}</span><strong>${escapeHtml(custom.total_formatted || '')}</strong><button type="button" class="gift-primary-button" data-apply-recommendation>${escapeHtml(copy.apply)}</button></article>`);
		}
		if (!cards.length) {
			const nextLabel = data.next_budget_label || data.next_budget_band || '';
			result.innerHTML = `<div class="gift-recommendation-empty"><p>${escapeHtml(copy.empty)}</p>${nextLabel ? `<button type="button" data-next-recommendation-budget="${escapeHtml(data.next_budget_band)}">${escapeHtml(String(copy.next_budget || '').replace(':budget', nextLabel))}</button>` : ''}</div>`;
			analytics('gift_surprise_used', { priority: data.priority, result_type: 'empty' });
			return;
		}
		result.innerHTML = `${cards.join('')}<button type="button" class="gift-recommendation-retry" data-retry-recommendation><i class="fa-solid fa-rotate" aria-hidden="true"></i>${escapeHtml(copy.retry)}</button>`;
		shownProductIds = Array.from(new Set([...shownProductIds, ...(custom?.product_ids || []), ...(ready?.product_ids || [])]));
		analytics('gift_surprise_used', { priority: data.priority, result_type: ready ? 'ready_box' : 'custom_start' });
	}

	async function submit() {
		request?.abort();
		request = new AbortController();
		const data = new FormData(form);
		const payload = {
			budget_band: data.get('budget_band'),
			priority: data.get('priority'),
			shown_product_ids: shownProductIds,
		};
		status.textContent = copy.loading || '';
		result.innerHTML = '';
		form.querySelector('button[type="submit"]').disabled = true;
		try {
			const response = await fetch(recommender.dataset.endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				signal: request.signal,
				headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
				body: JSON.stringify(payload),
			});
			const json = await response.json().catch(() => ({}));
			if (!response.ok) throw new Error(json.message || copy.error);
			status.textContent = '';
			renderResponse(json);
		} catch (error) {
			if (error.name !== 'AbortError') status.textContent = error.message || copy.error || '';
		} finally {
			form.querySelector('button[type="submit"]').disabled = false;
		}
	}

	document.addEventListener('click', (event) => {
		if (event.target.closest('[data-gift-help-open]')) { open(); return; }
		if (event.target.closest('[data-gift-help-close]')) { close(); return; }
		if (event.target.closest('[data-retry-recommendation]')) { submit(); return; }
		const nextBudget = event.target.closest('[data-next-recommendation-budget]');
		if (nextBudget) {
			const input = form.querySelector(`input[name="budget_band"][value="${CSS.escape(nextBudget.dataset.nextRecommendationBudget)}"]`);
			if (input) input.checked = true;
			submit();
			return;
		}
		if (event.target.closest('[data-apply-recommendation]') && currentRecommendation?.custom_start) {
			const recommendation = currentRecommendation;
			if (document.querySelector('[data-gift-builder-experience]')) {
				window.dispatchEvent(new CustomEvent('gift:recommendation', { detail: recommendation }));
				close();
				return;
			}
			window.sessionStorage.setItem('gift-builder:recommendation:v1', JSON.stringify(recommendation));
			window.location.assign(recommendation.custom_start.builder_url || '/gift-box-builder');
		}
	});

	form.addEventListener('submit', (event) => { event.preventDefault(); submit(); });
	document.addEventListener('keydown', (event) => {
		if (!recommender.classList.contains('is-open')) return;
		if (event.key === 'Escape') { event.preventDefault(); close(); return; }
		if (event.key !== 'Tab') return;
		const focusable = Array.from(panel.querySelectorAll('button:not([disabled]), a[href], input:not([disabled]), [tabindex]:not([tabindex="-1"])'));
		if (!focusable.length) return;
		if (event.shiftKey && document.activeElement === focusable[0]) { event.preventDefault(); focusable[focusable.length - 1].focus(); }
		else if (!event.shiftKey && document.activeElement === focusable[focusable.length - 1]) { event.preventDefault(); focusable[0].focus(); }
	});
	window.addEventListener('popstate', () => close({ fromPopState: true }));
}
