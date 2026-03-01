import './bootstrap';
import Splide from '@splidejs/splide';
import '@splidejs/splide/css';

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
			pagination: false,
			perPage: 4,
			perMove: 1,
			breakpoints: {
				1024: { perPage: 3 },
				768: { perPage: 2 },
				520: { perPage: 1.2, gap: '0.75rem' },
			},
		}).mount();
	}

	const widget = document.getElementById('chatbot-widget');
	if (!widget) {
		const sidebarBadge = document.getElementById('sidebar-inbox-badge');
		const conversationList = document.getElementById('conversation-list');
		if (sidebarBadge && !conversationList && window.Echo) {
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
	const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

	const addMessage = (text, role) => {
		const bubble = document.createElement('div');
		bubble.className = `chatbot-message ${role}`;
		bubble.textContent = text;
		messages.appendChild(bubble);
		messages.scrollTop = messages.scrollHeight;
	};

	const setOpenState = (open) => {
		if (open) {
			panel.classList.add('is-open');
			panel.setAttribute('aria-hidden', 'false');
			toggleButton.setAttribute('aria-expanded', 'true');
			input.focus();
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

	addMessage('გამარჯობა! MyTechnic ასისტენტიႠ სიამოვნებით დაგეხმარებთ. რა გაინტერესებთ?', 'bot');

	form?.addEventListener('submit', async (event) => {
		event.preventDefault();

		const message = input.value.trim();
		if (!message) {
			return;
		}

		addMessage(message, 'user');
		input.value = '';

		const typingBubble = document.createElement('div');
		typingBubble.className = 'chatbot-message bot';
		typingBubble.textContent = 'ერთი წამით...';
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

			addMessage(data.message || 'ამ ეტაპზე პასუხის გაცემა ვერ შევძელი. სცადეთ ცოტა მოგვიანებით.', 'bot');
		} catch (error) {
			typingBubble.remove();
			addMessage('ამ ეტაპზე პასუხის გაცემა ვერ შევძელი. სცადეთ ცოტა მოგვიანებით.', 'bot');
		}
	});
});
