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
			autoWidth : true,
			padding   : { right: '10%' },
			arrows    : true,
			pagination: true,
			snap      : true,
			drag      : 'free',
			breakpoints: {
				1024: { padding: { right: '5%' } },
				768:  { padding: { right: '8%' } },
				640:  { padding: { right: '12%' } },
			},
		}).mount();
	}

	const productSplide = document.getElementById('product-splide');
	if (productSplide && !productSplide.classList.contains('is-initialized')) {
		new Splide('#product-splide', {
			type: 'slide',
			perPage: 1,
			autoplay: false,
			pagination: true,
			arrows: true,
			speed: 400,
			gap: '0.5rem',
		}).mount();
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

	// ── Configure marked for chatbot ──
	marked.setOptions({
		breaks: true,
		gfm: true,
	});

	const renderMarkdown = (text) => {
		const raw = marked.parse(text);
		return DOMPurify.sanitize(raw, {
			ALLOWED_TAGS: ['b', 'i', 'em', 'strong', 'a', 'br', 'p', 'ul', 'ol', 'li', 'code'],
			ALLOWED_ATTR: ['href', 'target', 'rel'],
		});
	};

	const widget = document.getElementById('chatbot-widget');
	if (!widget) {
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
			window.Echo.private('inbox')
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

		if (products.length > 1) {
			let lastWheelAt = 0;
			root.addEventListener('wheel', (event) => {
				if (Math.abs(event.deltaY) <= Math.abs(event.deltaX)) {
					return;
				}

				event.preventDefault();

				const now = Date.now();
				if (now - lastWheelAt < 250) {
					return;
				}

				lastWheelAt = now;
				splide.go(event.deltaY > 0 ? '>' : '<');
			}, { passive: false });
		}

		scrollMessagesToBottom();
		root.scrollIntoView({ block: 'nearest' });
	};

	const setOpenState = (open) => {
		if (open) {
			panel.classList.add('is-open');
			panel.setAttribute('aria-hidden', 'false');
			toggleButton.setAttribute('aria-expanded', 'true');
			input.focus();
			if (historyEndpoint && !historyLoaded) loadHistory();
		} else {
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

	// ── Load conversation history on open ──
	const loadHistory = async () => {
		if (historyLoaded || !historyEndpoint) return;
		historyLoaded = true;

		try {
			const res = await fetch(historyEndpoint, {
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
				data.messages.forEach((msg) => {
					const role = msg.sender_type === 'customer' ? 'user' : 'bot';
					addMessage(msg.content, role);
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
				},
				body: JSON.stringify({ message }),
			});

			const data = await response.json();
			typingBubble.remove();

			if (!response.ok) {
				addMessage(data?.message || 'ამ ეტაპზე პასუხის გაცემა ვერ შევძელი. სცადეთ ცოტა მოგვიანებით.', 'bot');
				return;
			}

			if (data.conversation_id) conversationId = data.conversation_id;

			addMessage(data.message || 'ამ ეტაპზე პასუხის გაცემა ვერ შევძელი. სცადეთ ცოტა მოგვიანებით.', 'bot');

			// Show product carousel if backend provides products
			if (Array.isArray(data.products) && data.products.length > 0) {
				addCarousel(data.products);
			}

			// Show quick replies if backend provides them
			if (Array.isArray(data.quick_replies) && data.quick_replies.length > 0) {
				addQuickReplies(data.quick_replies);
			}
		} catch (error) {
			typingBubble.remove();
			addMessage('ამ ეტაპზე პასუხის გაცემა ვერ შევძელი. სცადეთ ცოტა მოგვიანებით.', 'bot');
		} finally {
			isSending = false;
		}
	});
});
