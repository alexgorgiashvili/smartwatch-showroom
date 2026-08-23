const root = document.querySelector('[data-gift-box-experience]');

if (root) {
	const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
	const locale = document.documentElement.lang?.toLowerCase().startsWith('ka') ? 'ka' : 'en';
	const copyElement = document.getElementById('gift-box-copy');
	const giftCopy = (() => {
		try {
			return JSON.parse(copyElement?.textContent || '{}');
		} catch (_) {
			return {};
		}
	})();
	const copy = giftCopy.quick || {};

	const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
		'&': '&amp;',
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#039;',
	}[character]));

	const money = (value) => `${Number(value || 0).toFixed(2)} ₾`;
	const analytics = (name, payload = {}) => {
		const safePayload = Object.fromEntries(
			Object.entries(payload).filter(([, value]) => value !== '' && value !== null && typeof value !== 'undefined'),
		);

		if (window.storefrontAnalytics?.trackCustom) {
			window.storefrontAnalytics.trackCustom(name, safePayload);
		} else if (window.storefrontAnalytics?.track) {
			window.storefrontAnalytics.track(name, safePayload);
		}
	};

	const standardAnalytics = (name, payload = {}) => {
		window.storefrontAnalytics?.track?.(name, payload);
	};

	analytics('gift_landing_view', {
		page_path: window.location.pathname,
		gift_mode: 'landing',
	});

	const mobileCta = root.querySelector('[data-gift-mobile-cta]');
	const heroActions = root.querySelector('.gift-hero-actions');
	if (mobileCta && heroActions && 'IntersectionObserver' in window) {
		const actionObserver = new IntersectionObserver(([entry]) => {
			mobileCta.hidden = entry.isIntersecting;
		}, { threshold: 0.2 });
		actionObserver.observe(heroActions);
	}

	document.addEventListener('click', (event) => {
		const pathLink = event.target.closest('[data-gift-path]');
		if (pathLink) {
			analytics('gift_path_selected', {
				gift_path: pathLink.dataset.giftPath,
				box_slug: pathLink.dataset.boxSlug,
			});
			if ((pathLink.dataset.giftPath || '').startsWith('custom_builder')) {
				analytics('gift_customize_start', {
					gift_mode: 'custom',
					box_slug: pathLink.dataset.boxSlug,
				});
			}
		}

		const customize = event.target.closest('[data-gift-customize]');
		if (customize) {
			analytics('gift_customize_start', {
				box_slug: customize.dataset.boxSlug,
				gift_mode: 'preset',
			});
		}
	});

	const observedBoxes = document.querySelectorAll('[data-gift-box-card]');
	if ('IntersectionObserver' in window && observedBoxes.length) {
		const seen = new Set();
		const observer = new IntersectionObserver((entries) => {
			entries.forEach((entry) => {
				if (!entry.isIntersecting || entry.intersectionRatio < 0.35) return;
				const slug = entry.target.dataset.boxSlug || '';
				if (seen.has(slug)) return;
				seen.add(slug);
				analytics('gift_box_view', { box_slug: slug });
				observer.unobserve(entry.target);
			});
		}, { threshold: [0.35] });
		observedBoxes.forEach((box) => observer.observe(box));
	}

	const modal = document.querySelector('[data-gift-quick-modal]');
	if (modal) {
		const title = modal.querySelector('[data-gift-quick-title]');
		const body = modal.querySelector('[data-gift-quick-body]');
		const status = modal.querySelector('[data-gift-quick-status]');
		let opener = null;
		let currentBox = null;
		let currentEndpoint = '';
		let abortController = null;

		const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

		const setStatus = (message = '', type = 'neutral') => {
			if (!status) return;
			status.textContent = message;
			status.className = 'gift-quick-status';
			status.classList.toggle('is-error', type === 'error');
			status.classList.toggle('is-success', type === 'success');
			status.hidden = !message;
		};

		const closeModal = () => {
			abortController?.abort();
			abortController = null;
			modal.classList.remove('is-open');
			modal.setAttribute('aria-hidden', 'true');
			document.body.classList.remove('gift-modal-open');
			currentBox = null;
			currentEndpoint = '';
			window.setTimeout(() => opener?.focus(), 160);
		};

		const openModal = (trigger) => {
			opener = trigger;
			modal.classList.add('is-open');
			modal.setAttribute('aria-hidden', 'false');
			document.body.classList.add('gift-modal-open');
			title.textContent = trigger.dataset.boxTitle || copy.quick_buy_title || '';
			body.innerHTML = '<div class="gift-quick-loading" aria-hidden="true"><span></span><span></span><span></span></div>';
			setStatus();
			requestAnimationFrame(() => modal.querySelector('[data-gift-quick-close]')?.focus());
		};

		const normalizeItem = (item) => {
			const product = item.product || item;
			const variants = Array.isArray(product.variants) ? product.variants : (Array.isArray(item.variants) ? item.variants : []);
			const defaultVariantId = Number(item.selected_variant_id || item.default_variant_id || product.selected_variant_id || variants[0]?.id || 0);
			return {
				itemId: Number(item.item_id || item.id || 0),
				productId: Number(product.id || item.product_id || 0),
				name: product.name || item.name || '',
				image: product.image || item.image || '',
				price: Number(product.price || item.price || 0),
				role: item.role || 'addon',
				variants,
				defaultVariantId,
			};
		};

		const renderBox = (payload) => {
			const box = payload.box || payload.gift_box || payload;
			const items = (Array.isArray(box.items) ? box.items : []).map(normalizeItem);
			currentBox = { ...box, items };
			title.textContent = box.title || box.label || copy.quick_buy_title || '';

			const productRows = items.map((item) => {
				const variants = item.variants.filter((variant) => Number(variant.available_quantity ?? 1) > 0);
				const variantControl = variants.length > 1 ? `
					<fieldset class="gift-variant-fieldset">
						<legend>${escapeHtml(copy.choose_color || 'Color')}</legend>
						<div class="gift-variant-grid">
							${variants.map((variant) => {
								const selected = Number(variant.id) === item.defaultVariantId;
								return `
									<label class="gift-variant-choice">
										<input type="radio" name="variant_${item.productId}" value="${Number(variant.id)}" ${selected ? 'checked' : ''}>
										<span>${variant.color_hex ? `<i style="--gift-color:${escapeHtml(variant.color_hex)}"></i>` : ''}${escapeHtml(variant.color_name || variant.name || copy.default_variant || '')}</span>
									</label>`;
							}).join('')}
						</div>
					</fieldset>` : `<input type="hidden" name="variant_${item.productId}" value="${Number(variants[0]?.id || item.defaultVariantId)}">`;

				return `
					<section class="gift-quick-product">
						<img src="${escapeHtml(item.image)}" alt="" loading="lazy">
						<div class="gift-quick-product-copy">
							<p class="gift-quick-role">${escapeHtml(item.role === 'main' ? (copy.main_gift || 'Main gift') : (copy.addon || 'Add-on'))}</p>
							<h3>${escapeHtml(item.name)}</h3>
							<p class="gift-quick-price">${money(item.price)}</p>
						</div>
						${variantControl}
					</section>`;
			}).join('');

			const originalTotal = Number(box.original_total || (Number(box.total || 0) + Number(box.discount?.amount || box.discount_amount || 0)));
			const total = Number(box.total || 0);
			const discount = Number(box.discount?.amount || box.discount_amount || Math.max(0, originalTotal - total));

			body.innerHTML = `
				<form data-gift-quick-form class="gift-quick-form" novalidate>
					<div class="gift-quick-products">${productRows}</div>
					<div class="gift-quick-package">
						<span><i class="fa-solid fa-gift" aria-hidden="true"></i>${escapeHtml(copy.packaging || 'Packaging')}</span>
						<strong>${escapeHtml(box.packaging_label || '')}${Number(box.packaging_amount || 0) > 0 ? ` · ${money(box.packaging_amount)}` : ''}</strong>
					</div>
					<details class="gift-message-details">
						<summary><span><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>${escapeHtml(copy.add_message || 'Add greeting')}</span><small>${escapeHtml(copy.optional || 'Optional')}</small></summary>
						<label for="gift-quick-message">${escapeHtml(copy.message_label || 'Greeting card message')}</label>
						<textarea id="gift-quick-message" name="message" maxlength="${Number(box.message_max_length || 300)}" rows="3" placeholder="${escapeHtml(copy.message_placeholder || '')}"></textarea>
					</details>
					<div class="gift-quick-totals" aria-live="polite">
						${discount > 0 ? `<div><span>${escapeHtml(copy.old_price || 'Before')}</span><del>${money(originalTotal)}</del></div><div class="is-discount"><span>${escapeHtml(copy.discount || 'Discount')}</span><strong>−${money(discount)}</strong></div>` : ''}
						<div class="is-total"><span>${escapeHtml(copy.total || 'Total')}</span><strong>${money(total)}</strong></div>
					</div>
					<button type="submit" class="gift-primary-button gift-quick-submit">
						<span>${escapeHtml(copy.add_to_cart || 'Add to cart')}</span>
						<i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
					</button>
					<p class="gift-quick-check-note"><i class="fa-solid fa-shield-heart" aria-hidden="true"></i>${escapeHtml(copy.server_check || '')}</p>
				</form>`;
		};

		document.addEventListener('click', async (event) => {
			const trigger = event.target.closest('[data-gift-quick-open]');
			if (trigger) {
				event.preventDefault();
				openModal(trigger);
				currentEndpoint = trigger.dataset.addUrl || '';
				analytics('gift_quick_buy_open', { box_slug: trigger.dataset.boxSlug });
				abortController = new AbortController();

				try {
					const response = await fetch(trigger.dataset.optionsUrl, {
						headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
						credentials: 'same-origin',
						signal: abortController.signal,
					});
					const payload = await response.json().catch(() => ({}));
					if (!response.ok) throw new Error(payload.message || copy.load_error || 'Unable to load gift box.');
					renderBox(payload);
				} catch (error) {
					if (error.name === 'AbortError') return;
					body.innerHTML = `<div class="gift-quick-empty"><i class="fa-solid fa-box-open" aria-hidden="true"></i><p>${escapeHtml(error.message || copy.load_error || '')}</p><button type="button" data-gift-quick-close>${escapeHtml(copy.close || 'Close')}</button></div>`;
					analytics('gift_flow_error', { box_slug: trigger.dataset.boxSlug, error_stage: 'options' });
				}
				return;
			}

			if (event.target.closest('[data-gift-quick-close]')) {
				event.preventDefault();
				closeModal();
			}
		});

		modal.addEventListener('click', (event) => {
			if (event.target === modal) closeModal();
		});

		modal.addEventListener('keydown', (event) => {
			if (event.key === 'Escape') {
				event.preventDefault();
				closeModal();
				return;
			}

			if (event.key !== 'Tab') return;
			const focusable = Array.from(modal.querySelectorAll(focusableSelector)).filter((element) => element.offsetParent !== null);
			if (!focusable.length) return;
			const first = focusable[0];
			const last = focusable[focusable.length - 1];
			if (event.shiftKey && document.activeElement === first) {
				event.preventDefault();
				last.focus();
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				first.focus();
			}
		});

		modal.addEventListener('submit', async (event) => {
			const form = event.target.closest('[data-gift-quick-form]');
			if (!form || !currentBox || !currentEndpoint) return;
			event.preventDefault();

			const submit = form.querySelector('[type="submit"]');
			const formData = new FormData(form);
			const items = currentBox.items.map((item) => ({
				product_id: item.productId,
				variant_id: Number(formData.get(`variant_${item.productId}`) || item.defaultVariantId),
			}));
			const payload = {
				items: currentBox.items.map((item) => ({
					item_id: item.itemId,
					variant_id: Number(formData.get(`variant_${item.productId}`) || item.defaultVariantId),
				})),
				message: String(formData.get('message') || '').slice(0, 300),
			};

			submit.disabled = true;
			submit.classList.add('is-loading');
			setStatus(copy.rechecking || '', 'neutral');

			try {
				const response = await fetch(currentEndpoint, {
					method: 'POST',
					headers: {
						Accept: 'application/json',
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': csrfToken,
						'X-Requested-With': 'XMLHttpRequest',
					},
					credentials: 'same-origin',
					body: JSON.stringify(payload),
				});
				const data = await response.json().catch(() => ({}));
				if (!response.ok || data.success === false) {
					const errors = data.errors ? Object.values(data.errors).flat() : [];
					throw new Error(errors[0] || data.message || copy.add_error || 'Unable to add gift box.');
				}

				window.cartUi?.updateBadges?.(data.cart_count || 0);
				const giftBox = data.gift_box || currentBox;
				const value = Number(giftBox.total || currentBox.total || 0);
				const contentIds = items.map((item) => String(item.product_id));
				analytics('gift_add_to_cart_success', {
					box_slug: currentBox.slug,
					gift_mode: 'quick_buy',
					value,
					currency: 'GEL',
					num_items: items.length,
				});
				standardAnalytics('AddToCart', {
					content_ids: contentIds,
					content_name: currentBox.title || currentBox.label || '',
					content_type: 'product_group',
					currency: 'GEL',
					value,
					num_items: items.length,
					contents: items.map((item) => ({ id: String(item.product_id), quantity: 1 })),
				});
				setStatus(data.message || copy.added || '', 'success');
				window.setTimeout(() => {
					window.location.assign(data.redirect_url || currentBox.cart_url || '/cart');
				}, 350);
			} catch (error) {
				setStatus(error.message || copy.add_error || '', 'error');
				analytics('gift_flow_error', { box_slug: currentBox.slug, error_stage: 'add_to_cart' });
				submit.disabled = false;
				submit.classList.remove('is-loading');
			}
		});
	}

	const hero = root.querySelector('[data-gift-hero]');
	if (hero) {
		initializeGiftStage(hero, giftCopy.experience || {}, analytics);
	}
}

export function chooseGiftRenderer({ reducedMotion = false, saveData = false, effectiveType = '', webgl = false, effectsDisabled = false } = {}) {
	if (reducedMotion || effectsDisabled) return 'static';
	if (saveData || ['slow-2g', '2g'].includes(effectiveType)) return 'css';
	return webgl ? 'three' : 'css';
}

export function rendererForGiftTap(desiredRenderer, sceneReady) {
	return desiredRenderer === 'three' && !sceneReady ? 'css' : desiredRenderer;
}

export function nextGiftOpenState(currentState, action) {
	if (action === 'tap' && currentState === 'open') return 'replay';
	if (action === 'tap' && currentState !== 'opening') return 'opening';
	if (action === 'complete') return 'open';
	return currentState;
}

function webglAvailable() {
	try {
		const canvas = document.createElement('canvas');
		return Boolean(window.WebGLRenderingContext && (canvas.getContext('webgl2') || canvas.getContext('webgl')));
	} catch (_) {
		return false;
	}
}

function initializeGiftStage(hero, copy, analytics) {
	const stage = hero.querySelector('[data-gift-stage]');
	const trigger = stage?.querySelector('[data-gift-open]');
	const triggerLabel = stage?.querySelector('[data-gift-open-label]');
	const status = stage?.querySelector('[data-gift-open-status]');
	const effectsToggle = stage?.querySelector('[data-gift-effects]');
	if (!stage || !trigger || !triggerLabel || !status) return;

	const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	const connection = navigator.connection || {};
	let effectsDisabled = false;
	let desiredRenderer = chooseGiftRenderer({
		reducedMotion,
		saveData: Boolean(connection.saveData),
		effectiveType: connection.effectiveType || '',
		webgl: webglAvailable(),
		effectsDisabled,
	});
	let scene = null;
	let loading = false;
	let openingTimer = 0;
	let state = 'static_closed';

	const setRenderer = (renderer) => {
		stage.dataset.renderer = renderer;
		analytics('gift_animation_tier', { renderer });
	};
	setRenderer(desiredRenderer);

	const setState = (nextState) => {
		state = nextState;
		stage.dataset.giftState = nextState;
	};

	const complete = (renderer, triggerType, elapsed) => {
		window.clearTimeout(openingTimer);
		setState('open');
		trigger.disabled = false;
		triggerLabel.textContent = copy.replay_open || 'Open again';
		status.textContent = copy.open_status || 'The gift box is open.';
		analytics('gift_open_complete', { renderer, trigger: triggerType, elapsed_ms: elapsed });
		if ('vibrate' in navigator) navigator.vibrate(10);
	};

	const open = () => {
		if (state === 'opening') return;
		const replaying = nextGiftOpenState(state, 'tap') === 'replay';
		const startedAt = performance.now();
		if (replaying) setState('replay');
		requestAnimationFrame(() => {
			setState('opening');
			trigger.disabled = true;
			status.textContent = copy.opening_status || 'The gift box is opening.';
			analytics('gift_open_start', { renderer: stage.dataset.renderer, trigger: replaying ? 'replay' : 'tap' });
			if ('vibrate' in navigator) navigator.vibrate(10);

			if (stage.dataset.renderer === 'three' && scene) {
				scene.open(replaying, () => {
					const elapsed = Math.round(performance.now() - startedAt);
					complete('three', replaying ? 'replay' : 'tap', elapsed);
				});
				return;
			}

			// CSS reacts on the same frame, including taps that arrive before Three.js is ready.
			const tapRenderer = rendererForGiftTap(desiredRenderer, Boolean(scene));
			if (tapRenderer !== desiredRenderer) {
				setRenderer(tapRenderer);
				desiredRenderer = tapRenderer;
			}
			const duration = reducedMotion || effectsDisabled ? 0 : 1000;
			openingTimer = window.setTimeout(() => {
				const elapsed = Math.round(performance.now() - startedAt);
				complete(stage.dataset.renderer || 'css', replaying ? 'replay' : 'tap', elapsed);
			}, duration);
		});
	};

	trigger.addEventListener('click', open);
	effectsToggle?.addEventListener('click', () => {
		effectsDisabled = !effectsDisabled;
		effectsToggle.setAttribute('aria-pressed', String(effectsDisabled));
		effectsToggle.querySelector('span').textContent = effectsDisabled
			? (copy.enable_effects || 'Turn effects on')
			: (copy.disable_effects || 'Turn effects off');
		if (effectsDisabled) {
			scene?.dispose();
			scene = null;
			desiredRenderer = 'static';
			setRenderer('static');
			return;
		}
		desiredRenderer = chooseGiftRenderer({
			reducedMotion,
			saveData: Boolean(connection.saveData),
			effectiveType: connection.effectiveType || '',
			webgl: webglAvailable(),
			effectsDisabled,
		});
		setRenderer(desiredRenderer);
	});

	if (desiredRenderer !== 'three') return;

	const loadThree = async () => {
		if (loading || scene || desiredRenderer !== 'three') return;
		loading = true;
		setState('enhancing');
		try {
			const THREE = await import('three');
			if (desiredRenderer !== 'three' || state === 'opening' || state === 'open') return;
			scene = initGiftScene(hero, THREE, () => {
				desiredRenderer = 'css';
				setRenderer('css');
			});
			setRenderer('three');
			setState('closed_ready');
		} catch (_) {
			desiredRenderer = 'css';
			setRenderer('css');
			setState('static_closed');
		} finally {
			loading = false;
		}
	};

	const afterFirstPaint = (callback) => requestAnimationFrame(() => requestAnimationFrame(callback));
	if ('IntersectionObserver' in window) {
		const proximityObserver = new IntersectionObserver(([entry]) => {
			if (!entry.isIntersecting) return;
			proximityObserver.disconnect();
			afterFirstPaint(loadThree);
		}, { rootMargin: '220px' });
		proximityObserver.observe(stage);
	} else {
		afterFirstPaint(loadThree);
	}
}

function initGiftScene(hero, THREE, onContextLost) {
	const host = hero.querySelector('[data-gift-canvas]');
	if (!host || host.dataset.initialized === 'true') return null;
	host.dataset.initialized = 'true';

	const scene = new THREE.Scene();
	const camera = new THREE.PerspectiveCamera(34, 1, 0.1, 100);
	camera.position.set(0, 1.3, 7.2);
	const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true, powerPreference: 'low-power' });
	renderer.setClearColor(0x000000, 0);
	renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, window.matchMedia('(max-width: 767px)').matches ? 1.25 : 1.5));
	renderer.outputColorSpace = THREE.SRGBColorSpace;
	renderer.domElement.setAttribute('aria-hidden', 'true');
	renderer.domElement.tabIndex = -1;
	host.appendChild(renderer.domElement);

	const grape = new THREE.MeshStandardMaterial({ color: 0x6f3bcc, roughness: 0.38, metalness: 0.04 });
	const grapeDark = new THREE.MeshStandardMaterial({ color: 0x39205f, roughness: 0.45 });
	const coral = new THREE.MeshStandardMaterial({ color: 0xff806f, roughness: 0.32 });
	const gold = new THREE.MeshStandardMaterial({ color: 0xf9d76e, roughness: 0.28, metalness: 0.18 });
	const watchFace = new THREE.MeshStandardMaterial({ color: 0x111827, roughness: 0.18, metalness: 0.36 });
	const materials = [grape, grapeDark, coral, gold, watchFace];
	const geometries = [];
	const group = new THREE.Group();
	group.rotation.x = -0.08;
	scene.add(group);

	const addBox = (size, position, material, parent = group) => {
		const geometry = new THREE.BoxGeometry(...size);
		geometries.push(geometry);
		const mesh = new THREE.Mesh(geometry, material);
		mesh.position.set(...position);
		mesh.castShadow = true;
		mesh.receiveShadow = true;
		parent.add(mesh);
		return mesh;
	};

	addBox([3.3, 1.75, 2.55], [0, -0.35, 0], grape);
	addBox([3.38, 0.12, 2.63], [0, 0.5, 0], grapeDark);
	const ribbonVertical = addBox([0.34, 1.86, 2.68], [0, -0.28, 0.02], coral);
	const ribbonHorizontal = addBox([3.42, 1.88, 0.3], [0, -0.28, 0.02], coral);

	const lidPivot = new THREE.Group();
	lidPivot.position.set(0, 0.56, -1.22);
	group.add(lidPivot);
	addBox([3.55, 0.3, 2.78], [0, 0, 1.33], grape, lidPivot);
	addBox([0.38, 0.36, 2.88], [0, 0.02, 1.33], gold, lidPivot);
	addBox([3.63, 0.36, 0.34], [0, 0.02, 1.33], gold, lidPivot);

	const watch = new THREE.Group();
	watch.position.set(-0.45, 0.48, 0.05);
	group.add(watch);
	addBox([0.78, 1.05, 0.3], [0, 0, 0], watchFace, watch);
	addBox([0.38, 1.45, 0.14], [0, -1.15, -0.03], coral, watch);
	addBox([0.38, 1.45, 0.14], [0, 1.15, -0.03], coral, watch);
	const faceGeometry = new THREE.PlaneGeometry(0.56, 0.78);
	geometries.push(faceGeometry);
	const faceMaterial = new THREE.MeshBasicMaterial({ color: 0xbafc69 });
	materials.push(faceMaterial);
	const face = new THREE.Mesh(faceGeometry, faceMaterial);
	face.position.z = 0.157;
	watch.add(face);

	const ambient = new THREE.HemisphereLight(0xfff6eb, 0x39205f, 2.2);
	scene.add(ambient);
	const key = new THREE.DirectionalLight(0xffffff, 4.2);
	key.position.set(4, 6, 5);
	scene.add(key);
	const fill = new THREE.PointLight(0xff806f, 18, 9);
	fill.position.set(-3, 2.4, 3);
	scene.add(fill);

	const sparkGeometry = new THREE.BufferGeometry();
	const sparkCount = 18;
	const positions = new Float32Array(sparkCount * 3);
	for (let index = 0; index < sparkCount; index += 1) {
		positions[index * 3] = (Math.random() - 0.5) * 5.5;
		positions[index * 3 + 1] = Math.random() * 4 - 0.6;
		positions[index * 3 + 2] = (Math.random() - 0.5) * 2;
	}
	sparkGeometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
	geometries.push(sparkGeometry);
	const sparkMaterial = new THREE.PointsMaterial({ color: 0xf9d76e, size: 0.08, transparent: true, opacity: 0 });
	materials.push(sparkMaterial);
	const sparkles = new THREE.Points(sparkGeometry, sparkMaterial);
	scene.add(sparkles);

	let width = 0;
	let height = 0;
	let frame = 0;
	let disposed = false;
	let animationStart = 0;
	let animationProgress = 0;
	let onAnimationComplete = null;
	let tiltX = 0;
	let tiltY = 0;
	let pointerStart = null;
	let inView = true;
	let animating = false;

	const resize = () => {
		const bounds = host.getBoundingClientRect();
		const nextWidth = Math.max(1, Math.round(bounds.width));
		const nextHeight = Math.max(1, Math.round(bounds.height));
		if (nextWidth === width && nextHeight === height) return;
		width = nextWidth;
		height = nextHeight;
		renderer.setSize(width, height, false);
		camera.aspect = width / height;
		camera.updateProjectionMatrix();
	};

	const clamp = (value, min, max) => Math.max(min, Math.min(max, value));
	const easeOut = (value) => 1 - ((1 - value) ** 3);
	const render = (time = performance.now()) => {
		if (disposed || !inView) return;
		resize();
		if (animating) animationProgress = Math.min(1, (time - animationStart) / 1000);
		const ribbonProgress = easeOut(clamp(animationProgress / 0.22, 0, 1));
		const lidProgress = easeOut(clamp((animationProgress - 0.14) / 0.58, 0, 1));
		const watchProgress = easeOut(clamp((animationProgress - 0.4) / 0.52, 0, 1));
		const sparkleProgress = clamp((animationProgress - 0.68) / 0.2, 0, 1);
		ribbonVertical.rotation.z = ribbonProgress * 0.08;
		ribbonHorizontal.scale.x = 1 - (ribbonProgress * 0.12);
		lidPivot.rotation.x = -1.55 * lidProgress;
		watch.position.y = 0.48 + (0.58 * watchProgress);
		watch.rotation.y = -0.18 + (0.32 * watchProgress);
		group.rotation.y += ((tiltX * 0.14) - group.rotation.y) * 0.14;
		group.rotation.x += ((-0.08 + tiltY * 0.14) - group.rotation.x) * 0.14;
		sparkMaterial.opacity = Math.min(0.84, sparkleProgress);
		renderer.render(scene, camera);
		if (animating && animationProgress >= 1) {
			animating = false;
			const callback = onAnimationComplete;
			onAnimationComplete = null;
			callback?.();
		}
		if (animating || pointerStart) {
			frame = requestAnimationFrame(render);
		}
	};

	const requestRender = () => {
		cancelAnimationFrame(frame);
		frame = requestAnimationFrame(render);
	};

	const onPointerDown = (event) => {
		pointerStart = { x: event.clientX, y: event.clientY };
		host.setPointerCapture?.(event.pointerId);
	};
	const onPointerMove = (event) => {
		if (!pointerStart) return;
		const bounds = host.getBoundingClientRect();
		tiltX = clamp((event.clientX - pointerStart.x) / Math.max(1, bounds.width), -1, 1);
		tiltY = clamp((event.clientY - pointerStart.y) / Math.max(1, bounds.height), -1, 1);
		requestRender();
	};
	const onPointerUp = () => {
		pointerStart = null;
		tiltX = 0;
		tiltY = 0;
		requestRender();
	};
	host.addEventListener('pointerdown', onPointerDown, { passive: true });
	host.addEventListener('pointermove', onPointerMove, { passive: true });
	host.addEventListener('pointerup', onPointerUp, { passive: true });
	host.addEventListener('pointercancel', onPointerUp, { passive: true });

	const resizeObserver = new ResizeObserver(requestRender);
	resizeObserver.observe(host);
	const intersectionObserver = new IntersectionObserver(([entry]) => {
		inView = entry.isIntersecting;
		if (inView) requestRender();
		else cancelAnimationFrame(frame);
	}, { rootMargin: '100px' });
	intersectionObserver.observe(host);

	const onVisibilityChange = () => {
		if (document.hidden) cancelAnimationFrame(frame);
		else if (inView && (animating || pointerStart)) requestRender();
	};
	document.addEventListener('visibilitychange', onVisibilityChange);

	const dispose = () => {
		if (disposed) return;
		disposed = true;
		cancelAnimationFrame(frame);
		resizeObserver.disconnect();
		intersectionObserver.disconnect();
		document.removeEventListener('visibilitychange', onVisibilityChange);
		host.removeEventListener('pointerdown', onPointerDown);
		host.removeEventListener('pointermove', onPointerMove);
		host.removeEventListener('pointerup', onPointerUp);
		host.removeEventListener('pointercancel', onPointerUp);
		geometries.forEach((geometry) => geometry.dispose());
		materials.forEach((material) => material.dispose());
		renderer.dispose();
		renderer.domElement.remove();
		delete host.dataset.initialized;
		hero.classList.remove('gift-3d-ready');
	};

	renderer.domElement.addEventListener('webglcontextlost', (event) => {
		event.preventDefault();
		dispose();
		onContextLost?.();
	}, { once: true });
	window.addEventListener('pagehide', dispose, { once: true });
	hero.classList.add('gift-3d-ready');
	requestRender();

	return {
		open(replay, callback) {
			cancelAnimationFrame(frame);
			animationProgress = 0;
			animationStart = performance.now();
			onAnimationComplete = callback;
			animating = true;
			if (replay) renderer.render(scene, camera);
			requestRender();
		},
		dispose,
	};
}
