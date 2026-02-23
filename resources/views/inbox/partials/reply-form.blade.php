<form id="reply-form" class="d-flex flex-column gap-2">
    @csrf

    <!-- Error Messages -->
    <div id="form-errors" class="alert alert-danger d-none"></div>

    <!-- AI Suggestions Container -->
    <div id="suggestions-container" class="d-none">
        <div class="alert alert-info d-flex justify-content-between align-items-start">
            <div>
                <strong class="small d-block mb-2">AI Suggestions:</strong>
                <div id="suggestions-list" class="d-flex flex-wrap gap-2"></div>
            </div>
            <button
                type="button"
                class="btn-close btn-sm"
                id="dismiss-suggestions"
                title="Dismiss suggestions"
            ></button>
        </div>
    </div>

    <!-- Textarea -->
    <div class="position-relative">
        <textarea
            id="reply-message"
            class="form-control"
            name="content"
            rows="3"
            placeholder="Type your reply..."
            required
            minlength="1"
            maxlength="5000"
        ></textarea>
        <small class="form-text text-muted d-block text-end mt-1">
            <span id="char-count">0</span>/5000
        </small>
    </div>

    <!-- Action Buttons -->
    <div class="d-flex gap-2 align-items-center">
        <button
            type="reset"
            class="btn btn-sm btn-outline-secondary"
            id="reset-btn"
        >
            Clear
        </button>

        <button
            type="button"
            class="btn btn-sm btn-outline-info"
            id="ai-suggest-btn"
            data-conversation-id="{{ $conversation->id }}"
            title="Get AI-powered response suggestions"
        >
            <i data-feather="zap" class="wd-14 ht-14 me-1"></i>
            AI Suggest
        </button>

        <button
            type="button"
            class="btn btn-sm btn-outline-secondary"
            id="attach-btn"
            title="Attach media (coming soon)"
            disabled
        >
            <i data-feather="paperclip" class="wd-14 ht-14"></i>
        </button>

        <button
            type="submit"
            class="btn btn-sm btn-primary ms-auto"
            id="send-btn"
        >
            <span id="send-text">Send</span>
            <i id="send-spinner" class="spinner-border spinner-border-sm ms-1 d-none" role="status"></i>
        </button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('reply-form');
    const messageInput = document.getElementById('reply-message');
    const charCount = document.getElementById('char-count');
    const resetBtn = document.getElementById('reset-btn');
    const sendBtn = document.getElementById('send-btn');
    const aiBtn = document.getElementById('ai-suggest-btn');
    const errorsContainer = document.getElementById('form-errors');
    const suggestionsContainer = document.getElementById('suggestions-container');
    const dismissBtn = document.getElementById('dismiss-suggestions');

    feather.replace();

    // Character counter
    messageInput.addEventListener('input', function() {
        charCount.textContent = this.value.length;
    });

    // Reset handler
    resetBtn.addEventListener('click', function(e) {
        e.preventDefault();
        form.reset();
        charCount.textContent = '0';
        errorsContainer.classList.add('d-none');
        suggestionsContainer.classList.add('d-none');
    });

    // Form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        submitReply();
    });

    // AI Suggest handler
    aiBtn.addEventListener('click', function(e) {
        e.preventDefault();
        suggestAIResponse();
    });

    // Dismiss suggestions handler
    dismissBtn.addEventListener('click', function(e) {
        e.preventDefault();
        suggestionsContainer.classList.add('d-none');
    });

    function submitReply() {
        const content = messageInput.value.trim();
        if (!content) {
            showError('Please enter a message');
            return;
        }

        const conversationId = {{ $conversation->id }};
        const sendBtn = document.getElementById('send-btn');
        const sendText = document.getElementById('send-text');
        const sendSpinner = document.getElementById('send-spinner');

        // Show loading state
        sendBtn.disabled = true;
        sendText.textContent = 'Sending...';
        sendSpinner.classList.remove('d-none');

        fetch(`/admin/inbox/${conversationId}/messages`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({
                content: content,
            }),
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(data => {
                    throw new Error(data.message || `HTTP error! status: ${response.status}`);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showToast('Message sent successfully', 'success');
                form.reset();
                charCount.textContent = '0';
                errorsContainer.classList.add('d-none');
                suggestionsContainer.classList.add('d-none');

                // Append message to thread using the full message object
                appendMessageToThread(data.message || data);
            } else {
                showError(data.message || 'Failed to send message');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError(error.message || 'Error sending message. Please try again.');
        })
        .finally(() => {
            sendBtn.disabled = false;
            sendText.textContent = 'Send';
            sendSpinner.classList.add('d-none');
        });
    }

    function suggestAIResponse() {
        const conversationId = {{ $conversation->id }};
        const aiBtn = document.getElementById('ai-suggest-btn');
        const sendText = document.getElementById('send-text');

        aiBtn.disabled = true;
        const originalHTML = aiBtn.innerHTML;
        aiBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Loading...';

        fetch(`/admin/inbox/${conversationId}/suggest-ai`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
        })
        .then(response => {
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return response.json();
        })
        .then(data => {
            if (data.success && data.suggestions && data.suggestions.length > 0) {
                displaySuggestions(data.suggestions);
                showToast('AI suggestions generated', 'info');
            } else {
                showError(data.message || 'Could not generate AI suggestions');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Error generating AI suggestions. Please try again later.');
        })
        .finally(() => {
            aiBtn.disabled = false;
            aiBtn.innerHTML = originalHTML;
        });
    }

    function displaySuggestions(suggestions) {
        const suggestionsList = document.getElementById('suggestions-list');
        suggestionsList.innerHTML = '';

        suggestions.forEach((suggestion, index) => {
            const pill = document.createElement('button');
            pill.type = 'button';
            pill.className = 'btn btn-sm btn-outline-primary';
            pill.innerHTML = `
                <small>${escapeHtml(suggestion.substring(0, 60))}${suggestion.length > 60 ? '...' : ''}</small>
            `;
            pill.title = suggestion;
            pill.addEventListener('click', function(e) {
                e.preventDefault();
                insertSuggestion(suggestion);
            });

            suggestionsList.appendChild(pill);
        });

        suggestionsContainer.classList.remove('d-none');
    }

    function insertSuggestion(text) {
        messageInput.value = text;
        charCount.textContent = text.length;
        messageInput.focus();
        showToast('Suggestion inserted', 'success');
    }

    function appendMessageToThread(message) {
        const messagesContainer = document.getElementById('messages-container');
        if (!messagesContainer) return;

        // Create message HTML
        const messageHTML = `
            <div class="mb-3 message-item admin-message">
                <div class="d-flex gap-2 flex-row-reverse">
                    <div class="flex-shrink-0">
                        <div class="avatar avatar-xs rounded-circle bg-light d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                            <i data-feather="user" class="wd-16 ht-16"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 text-end">
                        <div class="d-flex align-items-center gap-2 mb-1 justify-content-end">
                            <strong class="small">${message.sender_name || 'Admin'}</strong>
                            <small class="text-muted">Just now</small>
                            <span class="text-primary" title="Read"><i data-feather="check-circle" class="wd-14 ht-14"></i></span>
                        </div>
                        <div class="bg-primary text-white rounded p-2">
                            <p class="mb-0 text-break">${escapeHtml(message.content)}</p>
                        </div>
                    </div>
                </div>
            </div>
        `;

        messagesContainer.innerHTML += messageHTML;
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
        feather.replace();
    }

    function showError(message) {
        errorsContainer.innerHTML = message;
        errorsContainer.classList.remove('d-none');
    }

    function showToast(message, type = 'info') {
        const toast = document.getElementById('inbox-toast');
        const toastBody = toast.querySelector('.toast-body');
        toastBody.textContent = message;

        toast.classList.remove('bg-info', 'bg-success', 'bg-danger', 'bg-warning');
        if (type === 'success') {
            toast.classList.add('bg-success');
        } else if (type === 'error' || type === 'danger') {
            toast.classList.add('bg-danger');
        } else if (type === 'warning') {
            toast.classList.add('bg-warning');
        } else {
            toast.classList.add('bg-info');
        }

        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
    }

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
});
</script>
