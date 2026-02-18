import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
	const widget = document.getElementById('chatbot-widget');
	if (!widget) {
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

	addMessage('გამარჯობა! რით დაგეხმარო KidSIM Watch-ის არჩევაში?', 'bot');

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
		typingBubble.textContent = 'ვფიქრობ...';
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
				addMessage(data?.message || 'დროებით ვერ გიპასუხებ. სცადე მოგვიანებით.', 'bot');
				return;
			}

			addMessage(data.message || 'დროებით ვერ გიპასუხებ. სცადე მოგვიანებით.', 'bot');
		} catch (error) {
			typingBubble.remove();
			addMessage('დროებით ვერ გიპასუხებ. სცადე მოგვიანებით.', 'bot');
		}
	});
});
