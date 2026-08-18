const root = document.querySelector('[data-gift-box-experience]');

if (root) {
	const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
	const locale = document.documentElement.lang?.toLowerCase().startsWith('ka') ? 'ka' : 'en';
	const copy = window.GiftBoxCopy || {};

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
		const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		const saveData = Boolean(navigator.connection?.saveData);
		const webglAvailable = (() => {
			try {
				const canvas = document.createElement('canvas');
				return Boolean(window.WebGLRenderingContext && (canvas.getContext('webgl2') || canvas.getContext('webgl')));
			} catch (_) {
				return false;
			}
		})();

		if (!reducedMotion && !saveData && webglAvailable) {
			const start = async () => {
				try {
					const THREE = await import('three');
					initGiftScene(hero, THREE);
				} catch (_) {
					// The static poster remains a complete fallback if the optional 3D chunk fails.
				}
			};
			if ('requestIdleCallback' in window) {
				window.requestIdleCallback(start, { timeout: 1100 });
			} else {
				window.setTimeout(start, 320);
			}
		}
	}
}

function initGiftScene(hero, THREE) {
	const host = hero.querySelector('[data-gift-canvas]');
	if (!host || host.dataset.initialized === 'true') return;
	host.dataset.initialized = 'true';

	const scene = new THREE.Scene();
	const camera = new THREE.PerspectiveCamera(34, 1, 0.1, 100);
	camera.position.set(0, 1.3, 7.2);
	const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true, powerPreference: 'low-power' });
	renderer.setClearColor(0x000000, 0);
	renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5));
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
	addBox([0.34, 1.86, 2.68], [0, -0.28, 0.02], coral);
	addBox([3.42, 1.88, 0.3], [0, -0.28, 0.02], coral);

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
	const sparkCount = 48;
	const positions = new Float32Array(sparkCount * 3);
	for (let index = 0; index < sparkCount; index += 1) {
		positions[index * 3] = (Math.random() - 0.5) * 5.5;
		positions[index * 3 + 1] = Math.random() * 4 - 0.6;
		positions[index * 3 + 2] = (Math.random() - 0.5) * 2;
	}
	sparkGeometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
	geometries.push(sparkGeometry);
	const sparkMaterial = new THREE.PointsMaterial({ color: 0xf9d76e, size: 0.07, transparent: true, opacity: 0.8 });
	materials.push(sparkMaterial);
	const sparkles = new THREE.Points(sparkGeometry, sparkMaterial);
	scene.add(sparkles);

	let width = 0;
	let height = 0;
	let frame = 0;
	let disposed = false;
	let introStart = performance.now();
	let pointerX = 0;
	let pointerY = 0;
	let inView = true;

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

	const easeOut = (value) => 1 - ((1 - value) ** 3);
	const render = (time = performance.now()) => {
		if (disposed || !inView) return;
		resize();
		const progress = Math.min(1, (time - introStart) / 1450);
		const eased = easeOut(progress);
		lidPivot.rotation.x = -1.55 * eased;
		watch.position.y = 0.48 + (0.55 * eased);
		watch.rotation.y = -0.18 + (0.32 * eased);
		group.rotation.y += ((pointerX * 0.18) - group.rotation.y) * 0.075;
		group.rotation.x += ((-0.08 + pointerY * 0.08) - group.rotation.x) * 0.075;
		sparkMaterial.opacity = Math.min(0.85, eased * 1.15);
		renderer.render(scene, camera);
		if (progress < 1 || Math.abs((pointerX * 0.18) - group.rotation.y) > 0.002 || Math.abs((-0.08 + pointerY * 0.08) - group.rotation.x) > 0.002) {
			frame = requestAnimationFrame(render);
		}
	};

	const requestRender = () => {
		cancelAnimationFrame(frame);
		frame = requestAnimationFrame(render);
	};

	const pointerFine = window.matchMedia('(pointer: fine)').matches;
	const onPointerMove = (event) => {
		if (!pointerFine) return;
		const bounds = hero.getBoundingClientRect();
		pointerX = ((event.clientX - bounds.left) / Math.max(1, bounds.width) - 0.5) * 2;
		pointerY = ((event.clientY - bounds.top) / Math.max(1, bounds.height) - 0.5) * 2;
		requestRender();
	};
	hero.addEventListener('pointermove', onPointerMove, { passive: true });

	const resizeObserver = new ResizeObserver(requestRender);
	resizeObserver.observe(host);
	const intersectionObserver = new IntersectionObserver(([entry]) => {
		inView = entry.isIntersecting;
		if (inView) requestRender();
		else cancelAnimationFrame(frame);
	}, { rootMargin: '100px' });
	intersectionObserver.observe(hero);

	const dispose = () => {
		if (disposed) return;
		disposed = true;
		cancelAnimationFrame(frame);
		resizeObserver.disconnect();
		intersectionObserver.disconnect();
		hero.removeEventListener('pointermove', onPointerMove);
		geometries.forEach((geometry) => geometry.dispose());
		materials.forEach((material) => material.dispose());
		renderer.dispose();
		renderer.domElement.remove();
		hero.classList.remove('gift-3d-ready');
	};

	renderer.domElement.addEventListener('webglcontextlost', (event) => {
		event.preventDefault();
		dispose();
	}, { once: true });
	window.addEventListener('pagehide', dispose, { once: true });
	hero.classList.add('gift-3d-ready');
	requestRender();
}
