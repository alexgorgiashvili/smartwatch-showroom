import './bootstrap';
import './lazy-load';
import Splide from '@splidejs/splide';
import '@splidejs/splide/css';
import { marked } from 'marked';
import DOMPurify from 'dompurify';

document.addEventListener('DOMContentLoaded', () => {
	const popularSplide = document.getElementById('popular-splide');
	if (popularSplide && !popularSplide.classList.contains('is-initialized')) {
		new Splide('#popular-splide', {
			type      : 'slide',
			gap       : '1rem',
			perPage   : 5,
			perMove   : 1,
			arrows    : true,
			pagination: true,
			omitEnd   : false,
			rewind    : false,
			trimSpace : false,
			breakpoints: {
				1280: { perPage: 4 },
				1024: { perPage: 3 },
				768:  { perPage: 2 },
				640:  {
					perPage  : 1,
					gap      : '0.75rem',
					padding  : { left: '0.75rem', right: '0.75rem' },
					focus    : 'center',
				},
			},
		}).mount();
	}

	const productSplide = document.getElementById('product-splide');
	if (productSplide && !productSplide.classList.contains('is-initialized')) {
		const splide = new Splide('#product-splide', {
			type: 'slide',
			perPage: 1,
			autoplay: false,
			pagination: true,
			arrows: true,
			speed: 400,
			gap: '0.5rem',
		}).mount();

		productSplide.__splide = splide;
	}

	const relatedSplide = document.getElementById('related-products-splide');
	if (relatedSplide && !relatedSplide.classList.contains('is-initialized')) {
		new Splide('#related-products-splide', {
			type: 'slide',
			gap: '1rem',
			arrows: true,
			pagination: true,
			perPage: 4,
			perMove: 1,
			drag: 'free',
			snap: true,
			rewind: true,
			updateOnMove: true,
			breakpoints: {
				1024: { perPage: 3 },
				768: { perPage: 2 },
				520: { perPage: 1.2, gap: '0.75rem' },
			},
		}).mount();
	}

	const lightboxRoot = document.getElementById('site-lightbox');
	const productGallery = document.getElementById('product-splide');
	if (lightboxRoot && productGallery) {
		const overlay = lightboxRoot.querySelector('[data-site-lightbox-overlay]');
		const closeBtn = lightboxRoot.querySelector('[data-site-lightbox-close]');
		const prevBtn = lightboxRoot.querySelector('[data-site-lightbox-prev]');
		const nextBtn = lightboxRoot.querySelector('[data-site-lightbox-next]');
		const imageEl = lightboxRoot.querySelector('[data-site-lightbox-image]');
		const captionEl = lightboxRoot.querySelector('[data-site-lightbox-caption]');
		const counterEl = lightboxRoot.querySelector('[data-site-lightbox-counter]');

		const triggerEls = Array.from(productGallery.querySelectorAll('[data-product-lightbox]'));
		const itemsByIndex = new Map();
		triggerEls.forEach((el) => {
			const idx = Number.parseInt(el.dataset.index || '', 10);
			const src = el.dataset.src || '';
			if (!Number.isFinite(idx) || !src) return;
			if (itemsByIndex.has(idx)) return;
			itemsByIndex.set(idx, { src, alt: el.dataset.alt || '' });
		});
		const items = Array.from(itemsByIndex.entries())
			.sort((a, b) => a[0] - b[0])
			.map(([, item]) => item);

		if (items.length > 0 && overlay && closeBtn && prevBtn && nextBtn && imageEl && captionEl && counterEl) {
			let activeIndex = 0;
			let lastFocused = null;
			let touchStartX = null;
			let touchStartY = null;

			const normalizeIndex = (idx) => {
				if (items.length === 0) return 0;
				const n = idx % items.length;
				return n < 0 ? n + items.length : n;
			};

			const preload = (idx) => {
				if (items.length < 2) return;
				const normalized = normalizeIndex(idx);
				const src = items[normalized]?.src;
				if (!src) return;
				const img = new Image();
				img.decoding = 'async';
				img.src = src;
			};

			const updateNav = () => {
				const shouldShow = items.length > 1;
				prevBtn.classList.toggle('hidden', !shouldShow);
				nextBtn.classList.toggle('hidden', !shouldShow);
			};

			const render = () => {
				const item = items[activeIndex];
				if (!item) return;
				imageEl.src = item.src;
				imageEl.alt = item.alt || '';
				captionEl.textContent = item.alt || '';
				counterEl.textContent = `${activeIndex + 1} / ${items.length}`;
				updateNav();
				preload(activeIndex + 1);
				preload(activeIndex - 1);
			};

			const open = (idx, focusEl = null) => {
				activeIndex = normalizeIndex(idx);
				lastFocused = focusEl;
				lightboxRoot.classList.remove('hidden');
				lightboxRoot.setAttribute('aria-hidden', 'false');
				document.body.classList.add('overflow-hidden');
				render();
				closeBtn.focus();
				document.addEventListener('keydown', onKeydown);
			};

			const close = () => {
				lightboxRoot.classList.add('hidden');
				lightboxRoot.setAttribute('aria-hidden', 'true');
				document.body.classList.remove('overflow-hidden');
				imageEl.src = '';
				imageEl.alt = '';
				captionEl.textContent = '';
				counterEl.textContent = '';
				document.removeEventListener('keydown', onKeydown);
				if (lastFocused && typeof lastFocused.focus === 'function') {
					lastFocused.focus();
				}
				lastFocused = null;
			};

			const prev = () => {
				activeIndex = normalizeIndex(activeIndex - 1);
				render();
			};

			const next = () => {
				activeIndex = normalizeIndex(activeIndex + 1);
				render();
			};

			const onKeydown = (e) => {
				if (e.key === 'Escape') {
					e.preventDefault();
					close();
					return;
				}
				if (items.length > 1 && (e.key === 'ArrowLeft' || e.key === 'ArrowRight')) {
					e.preventDefault();
					if (e.key === 'ArrowLeft') prev();
					else next();
				}
			};

			productGallery.addEventListener('click', (e) => {
				const trigger = e.target.closest('[data-product-lightbox]');
				if (!trigger) return;
				const idx = Number.parseInt(trigger.dataset.index || '', 10);
				if (!Number.isFinite(idx)) return;
				e.preventDefault();
				open(idx, trigger);
			});

			overlay.addEventListener('click', close);
			closeBtn.addEventListener('click', close);
			prevBtn.addEventListener('click', prev);
			nextBtn.addEventListener('click', next);

			imageEl.addEventListener('touchstart', (e) => {
				if (!e.touches || e.touches.length !== 1) return;
				touchStartX = e.touches[0].clientX;
				touchStartY = e.touches[0].clientY;
			}, { passive: true });

			imageEl.addEventListener('touchend', (e) => {
				if (items.length < 2) return;
				if (touchStartX === null || touchStartY === null) return;
				const touch = e.changedTouches && e.changedTouches[0];
				if (!touch) return;
				const dx = touch.clientX - touchStartX;
				const dy = touch.clientY - touchStartY;
				touchStartX = null;
				touchStartY = null;
				if (Math.abs(dx) < 40 || Math.abs(dx) < Math.abs(dy)) return;
				if (dx < 0) next();
				else prev();
			}, { passive: true });
		}
	}

	// ── Configure marked for chatbot ──
	marked.setOptions({
		breaks: true,
		gfm: true,
	});

	const renderMarkdown = (text) => {
		const normalizedText = String(text).replace(
			/\[([^\]]+)\]\(\[([^\]\s]+)\]\(\2\)?/g,
			'[$1]($2)'
		);
		const raw = marked.parse(normalizedText);
		return DOMPurify.sanitize(raw, {
			ALLOWED_TAGS: ['b', 'i', 'em', 'strong', 'a', 'br', 'p', 'ul', 'ol', 'li', 'code'],
			ALLOWED_ATTR: ['href', 'target', 'rel'],
		});
	};

	const withoutProductLinkBlock = (text) => {
		const linkBlockStart = String(text).search(/^\s*\[[^\]]+\]\s*\(/m);
		return linkBlockStart >= 0 ? String(text).slice(0, linkBlockStart).trimEnd() : text;
	};

	const widget = document.getElementById('chatbot-widget');
	if (!widget) {
		const isLocalhostRuntime = () => ['127.0.0.1', 'localhost'].includes(window.location.hostname);
		const subscribeToInboxChannel = (name) => {
			if (!window.Echo) {
				return null;
			}

			return isLocalhostRuntime()
				? window.Echo.channel(name)
				: window.Echo.private(name);
		};

		const getInboxBadgeElement = () => {
			const legacyBadge = document.getElementById('sidebar-inbox-badge');
			if (legacyBadge) {
				return legacyBadge;
			}

			const inboxLink = document.querySelector('a[href*="/admin/inbox"], a[href*="/inbox"]');
			if (!inboxLink) {
				return null;
			}

			let badge = inboxLink.querySelector('[data-inbox-badge]');
			if (!badge) {
				badge = document.createElement('span');
				badge.setAttribute('data-inbox-badge', '1');
				badge.setAttribute('data-unread-count', '0');
				badge.className = 'fi-badge fi-color-danger ms-2 d-none';
				badge.textContent = '0';
				inboxLink.appendChild(badge);
			}

			return badge;
		};

		const sidebarBadge = getInboxBadgeElement();
		const conversationList = document.getElementById('conversation-list');
		// Only set up Echo listener if NOT on inbox page (sidebar badge updates handled by inbox.blade.php)
		if (sidebarBadge && !conversationList && !window.location.href.includes('/admin/inbox') && window.Echo) {
			console.log('Setting up Echo listener for sidebar badge (non-inbox pages)');
			subscribeToInboxChannel('inbox')
				?.stopListening('.MessageReceived')
				.listen('.MessageReceived', (event) => {
					if (event?.message?.sender_type === 'admin') {
						return;
					}
					const current = parseInt(sidebarBadge.dataset.unreadCount || '0', 10);
					const next = Number.isNaN(current) ? 1 : current + 1;
					sidebarBadge.textContent = next;
					sidebarBadge.dataset.unreadCount = next;
					sidebarBadge.classList.toggle('d-none', next === 0);
					console.log('Sidebar badge updated on non-inbox page:', next);
				});
		}
		return;
	}

	const panel = widget.querySelector('.chatbot-panel');
	const toggleButton = widget.querySelector('[data-chatbot-toggle]');
	const closeButton = widget.querySelector('[data-chatbot-close]');
	const form = widget.querySelector('[data-chatbot-form]');
	const input = widget.querySelector('.chatbot-input');
	const messages = widget.querySelector('[data-chatbot-messages]');
	const endpoint = widget.dataset.endpoint;
	const historyEndpoint = widget.dataset.historyEndpoint || '';
	const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
	let conversationId = null;
	let isSending = false;
	let historyLoaded = false;
	let viewportSyncFrame = null;
	let viewportSyncTimers = [];

	const syncChatbotViewport = () => {
		const viewport = window.visualViewport;
		const layoutViewportHeight = window.innerHeight || document.documentElement.clientHeight;
		const viewportHeight = viewport?.height || layoutViewportHeight;
		const keyboardInset = viewport
			? Math.max(0, layoutViewportHeight - viewport.height - viewport.offsetTop)
			: 0;

		widget.style.setProperty('--chatbot-viewport-height', `${Math.round(viewportHeight)}px`);
		widget.style.setProperty('--chatbot-keyboard-inset', `${Math.round(keyboardInset)}px`);
	};

	const clearChatbotViewportSync = () => {
		if (viewportSyncFrame !== null) {
			cancelAnimationFrame(viewportSyncFrame);
			viewportSyncFrame = null;
		}

		viewportSyncTimers.forEach((timer) => window.clearTimeout(timer));
		viewportSyncTimers = [];
	};

	const scheduleChatbotViewportSync = (delays = [0, 120, 280]) => {
		clearChatbotViewportSync();
		syncChatbotViewport();

		viewportSyncFrame = requestAnimationFrame(() => {
			viewportSyncFrame = null;
			syncChatbotViewport();
		});

		delays
			.filter((delay) => delay > 0)
			.forEach((delay) => {
				const timer = window.setTimeout(() => {
					syncChatbotViewport();
					viewportSyncTimers = viewportSyncTimers.filter((activeTimer) => activeTimer !== timer);
				}, delay);

				viewportSyncTimers.push(timer);
			});
	};

	const focusChatbotInput = () => {
		if (!input) {
			return;
		}

		try {
			input.focus({ preventScroll: true });
		} catch (_) {
			input.focus();
		}

		scheduleChatbotViewportSync([120, 280, 420]);
		scrollMessagesToBottom();
	};

	syncChatbotViewport();
	window.addEventListener('resize', () => scheduleChatbotViewportSync([120, 280]), { passive: true });
	window.addEventListener('orientationchange', () => scheduleChatbotViewportSync([180, 360, 540]), { passive: true });
	window.visualViewport?.addEventListener('resize', () => scheduleChatbotViewportSync([120, 280]), { passive: true });
	window.visualViewport?.addEventListener('scroll', () => scheduleChatbotViewportSync([120, 280]), { passive: true });

	const addMessage = (text, role) => {
		const bubble = document.createElement('div');
		bubble.className = `chatbot-message ${role}`;
		if (role === 'bot') {
			bubble.innerHTML = renderMarkdown(text);
			bubble.querySelectorAll('a').forEach((a) => {
				a.setAttribute('target', '_blank');
				a.setAttribute('rel', 'noopener noreferrer');
			});
		} else {
			bubble.textContent = text;
		}
		messages.appendChild(bubble);
		messages.scrollTop = messages.scrollHeight;
	};

	const scrollMessagesToBottom = () => {
		messages.scrollTop = messages.scrollHeight;
		requestAnimationFrame(() => {
			messages.scrollTop = messages.scrollHeight;
		});
	};

	// ── Typing indicator (animated dots) ──
	const createTypingIndicator = () => {
		const bubble = document.createElement('div');
		bubble.className = 'chatbot-message bot chatbot-typing';
		bubble.innerHTML = '<span class="typing-dots"><span></span><span></span><span></span></span>';
		return bubble;
	};

	// ── Quick reply buttons ──
	const addQuickReplies = (replies) => {
		const existing = messages.querySelector('.chatbot-quick-replies');
		if (existing) existing.remove();

		const container = document.createElement('div');
		container.className = 'chatbot-quick-replies';
		replies.forEach((text) => {
			const btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'chatbot-quick-btn';
			btn.textContent = text;
			btn.addEventListener('click', () => {
				container.remove();
				input.value = text;
				form.requestSubmit();
			});
			container.appendChild(btn);
		});
		messages.appendChild(container);
		scrollMessagesToBottom();
	};

	// ── Product carousel ──
	const addCarousel = (products) => {
		const root = document.createElement('div');
		root.className = 'chatbot-carousel splide';

		const splideTrack = document.createElement('div');
		splideTrack.className = 'splide__track';

		const list = document.createElement('ul');
		list.className = 'splide__list';

		products.forEach((p) => {
			const slide = document.createElement('li');
			slide.className = 'splide__slide';

			const card = document.createElement('a');
			card.className = 'chatbot-carousel-card';
			card.href = p.url || '#';
			card.target = '_blank';
			card.rel = 'noopener noreferrer';
			const safeName = DOMPurify.sanitize(p.name || 'პროდუქტი');
			const safePrice = p.price ? DOMPurify.sanitize(String(p.price)) : '';
			const safeImage = p.image ? DOMPurify.sanitize(p.image) : '';
			const placeholder = `<div class="chatbot-carousel-placeholder" aria-hidden="true">${safeName.charAt(0).toUpperCase()}</div>`;
			const media = safeImage
				? `<div class="chatbot-carousel-media"><img src="${safeImage}" alt="${safeName}" class="chatbot-carousel-img" loading="lazy"></div>`
				: `<div class="chatbot-carousel-media">${placeholder}</div>`;
			card.innerHTML = `${media}<div class="chatbot-carousel-body"><p class="chatbot-carousel-name">${safeName}</p>${safePrice ? `<p class="chatbot-carousel-price">${safePrice}</p>` : ''}</div>`;

			const image = card.querySelector('.chatbot-carousel-img');
			if (image) {
				image.addEventListener('load', scrollMessagesToBottom, { once: true });
				image.addEventListener('error', () => {
					const mediaContainer = card.querySelector('.chatbot-carousel-media');
					if (mediaContainer) {
						mediaContainer.innerHTML = placeholder;
					}
					scrollMessagesToBottom();
				}, { once: true });
			}

			slide.appendChild(card);
			list.appendChild(slide);
		});

		splideTrack.appendChild(list);
		root.appendChild(splideTrack);
		messages.appendChild(root);

		const splide = new Splide(root, {
			type: 'slide',
			gap: '0.6rem',
			pagination: products.length > 1,
			arrows: false,
			drag: true,
			perMove: 1,
			autoWidth: true,
			padding: { right: '1rem' },
			classes: {
				pagination: 'splide__pagination chatbot-carousel-pagination',
				page: 'splide__pagination__page chatbot-carousel-page',
			},
		});

		splide.on('mounted', scrollMessagesToBottom);
		splide.mount();

		scrollMessagesToBottom();
	};

	const setOpenState = (open) => {
		if (open) {
			scheduleChatbotViewportSync([120, 280]);
			panel.classList.add('is-open');
			panel.setAttribute('aria-hidden', 'false');
			toggleButton.setAttribute('aria-expanded', 'true');
			requestAnimationFrame(() => {
				focusChatbotInput();
			});
			if (historyEndpoint && !historyLoaded) loadHistory();
		} else {
			clearChatbotViewportSync();
			scheduleChatbotViewportSync([120, 280]);
			panel.classList.remove('is-open');
			panel.setAttribute('aria-hidden', 'true');
			toggleButton.setAttribute('aria-expanded', 'false');
		}
	};

	toggleButton?.addEventListener('click', () => {
		setOpenState(!panel.classList.contains('is-open'));
	});

	closeButton?.addEventListener('click', () => {
		setOpenState(false);
	});

	input?.addEventListener('focus', () => {
		scheduleChatbotViewportSync([120, 280, 420]);
		scrollMessagesToBottom();
	});

	input?.addEventListener('blur', () => {
		scheduleChatbotViewportSync([120, 280, 420]);
	});

	// ── Load conversation history on open ──
	const loadHistory = async () => {
		if (historyLoaded || !historyEndpoint) return;
		historyLoaded = true;

		try {
			const res = await fetch(historyEndpoint, {
				cache: 'no-store',
				headers: {
					'Accept': 'application/json',
					'X-CSRF-TOKEN': csrfToken || '',
				},
			});
			if (!res.ok) return;
			const data = await res.json();
			if (data.conversation_id) conversationId = data.conversation_id;

			if (Array.isArray(data.messages) && data.messages.length > 0) {
				messages.innerHTML = '';
				data.messages
					.sort((a, b) => {
						const timeDifference = Date.parse(a.created_at || '') - Date.parse(b.created_at || '');
						return timeDifference || Number(a.id || 0) - Number(b.id || 0);
					})
					.forEach((msg) => {
					const role = msg.sender_type === 'customer' ? 'user' : 'bot';
					const hasProducts = role === 'bot'
						&& Array.isArray(msg.products)
						&& msg.products.length > 0;
					addMessage(hasProducts ? withoutProductLinkBlock(msg.content) : msg.content, role);
					if (hasProducts) {
						addCarousel(msg.products);
					}
					});
				return;
			}
		} catch {
			// ignore — fall through to greeting
		}

		showGreeting();
	};

	const showGreeting = () => {
		addMessage('გამარჯობა! MyTechnic ასისტენტი ვარ 👋 სიამოვნებით დაგეხმარებით. რა გაინტერესებთ?', 'bot');
		addQuickReplies([
			'🎯 რას გირჩევთ?',
			'💰 რა ფასები გაქვთ?',
			'📍 სად ხართ?',
			'📞 საკონტაქტო',
		]);
	};

	if (!historyEndpoint) {
		showGreeting();
	}

	form?.addEventListener('submit', async (event) => {
		event.preventDefault();
		if (isSending) return;

		const message = input.value.trim();
		if (!message) return;

		// Remove any existing quick replies
		const qr = messages.querySelector('.chatbot-quick-replies');
		if (qr) qr.remove();

		addMessage(message, 'user');
		input.value = '';
		isSending = true;

		const typingBubble = createTypingIndicator();
		messages.appendChild(typingBubble);
		messages.scrollTop = messages.scrollHeight;

		try {
			const response = await fetch(endpoint, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': csrfToken || '',
					'Accept': 'text/event-stream'
				},
				body: JSON.stringify({ message }),
			});

			if (!response.ok) {
				const errorData = await response.json().catch(() => null);
				typingBubble.remove();
				addMessage(errorData?.message || 'ამ ეტაპზე პასუხის გაცემა ვერ შევძელი. სცადეთ ცოტა მოგვიანებით.', 'bot');
				isSending = false;
				return;
			}

			// Parse SSE response
			const reader = response.body.getReader();
			const decoder = new TextDecoder();
			let buffer = '';

			while (true) {
				const { done, value } = await reader.read();
				if (done) break;

				buffer += decoder.decode(value, { stream: true });
				const lines = buffer.split('\n');
				buffer = lines.pop() || ''; // Keep the last incomplete line in buffer

				for (const line of lines) {
					if (line.startsWith('data: ')) {
						const dataStr = line.substring(6);
						if (dataStr.trim() === '') continue;

						try {
							const data = JSON.parse(dataStr);

							if (data.conversation_id) conversationId = data.conversation_id;

							// Update bubble text
							typingBubble.classList.remove('chatbot-typing');
							const hasProducts = Array.isArray(data.products) && data.products.length > 0;
							const responseMessage = data.message || 'ამ ეტაპზე პასუხის გაცემა ვერ შევძელი. სცადეთ ცოტა მოგვიანებით.';
							typingBubble.innerHTML = renderMarkdown(
								hasProducts ? withoutProductLinkBlock(responseMessage) : responseMessage
							);

							// Fix links
							typingBubble.querySelectorAll('a').forEach((a) => {
								a.setAttribute('target', '_blank');
								a.setAttribute('rel', 'noopener noreferrer');
							});

							// Show product carousel if backend provides products
							if (Array.isArray(data.products) && data.products.length > 0) {
								addCarousel(data.products);
							}

							// Show quick replies if backend provides them
							if (Array.isArray(data.quick_replies) && data.quick_replies.length > 0) {
								addQuickReplies(data.quick_replies);
							}

							messages.scrollTop = messages.scrollHeight;
						} catch (e) {
							console.error('Failed to parse SSE data', e);
						}
					}
				}
			}
		} catch (error) {
			typingBubble.remove();
			addMessage('ამ ეტაპზე პასუხის გაცემა ვერ შევძელი. სცადეთ ცოტა მოგვიანებით.', 'bot');
		} finally {
			isSending = false;
		}
	});
});
