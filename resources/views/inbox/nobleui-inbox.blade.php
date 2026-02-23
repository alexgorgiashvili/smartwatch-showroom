@extends('admin.layout')

@section('title', 'Inbox - NobleUI')

@push('styles')
    <style>
        .chat-body {
            max-height: 60vh;
            overflow-y: auto;
        }

        .messages .bubble {
            max-width: 70%;
            word-break: break-word;
            overflow-wrap: anywhere;
            white-space: pre-wrap;
        }

        .chat-list .text-truncate {
            max-width: 220px;
        }

        /* Bot message styling */
        .message-item.bot {
            display: flex;
            margin-bottom: 1rem;
            justify-content: flex-start;
        }

        .message-item.bot .bubble {
            background-color: #10b981 !important;
            color: white !important;
            border-radius: 18px 18px 18px 4px;
        }

        .message-item.bot .content {
            margin-left: 0.5rem;
        }

        .message-item.bot .content .message span {
            margin-left: 0.5rem;
        }

        /* Customer message styling */
        .message-item.friend .bubble {
            background-color: #f3f4f6;
            color: #1f2937;
            border-radius: 18px 18px 4px 18px;
        }

        /* Admin message (me) styling */
        .message-item.me .bubble {
            background-color: #3b82f6;
            color: white;
            border-radius: 18px 18px 4px 18px;
        }
    </style>
@endpush

@section('content')
<div class="row chat-wrapper">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <div class="row position-relative">
                    <div class="col-lg-4 chat-aside border-end-lg">
                        <div class="aside-content">
                            <div class="aside-header">
                                <div class="d-flex justify-content-between align-items-center pb-2 mb-2">
                                    <div class="d-flex align-items-center">
                                        <figure class="me-2 mb-0">
                                            <img src="{{ asset('assets/images/others/placeholder.jpg') }}"
                                                 class="img-sm rounded-circle"
                                                 alt="profile">
                                            <div class="status online"></div>
                                        </figure>
                                        <div>
                                            <h6>Inbox</h6>
                                            <p class="text-muted tx-13">Omnichannel messages</p>
                                        </div>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn p-0" type="button" id="dropdownMenuButton"
                                                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <i class="icon-lg text-muted pb-3px" data-feather="settings"
                                               data-bs-toggle="tooltip" title="Settings"></i>
                                        </button>
                                        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                            <a class="dropdown-item d-flex align-items-center" href="javascript:;">
                                                <i data-feather="eye" class="icon-sm me-2"></i>
                                                <span>View Profile</span>
                                            </a>
                                            <a class="dropdown-item d-flex align-items-center" href="javascript:;">
                                                <i data-feather="edit-2" class="icon-sm me-2"></i>
                                                <span>Edit Profile</span>
                                            </a>
                                            <a class="dropdown-item d-flex align-items-center" href="javascript:;">
                                                <i data-feather="settings" class="icon-sm me-2"></i>
                                                <span>Settings</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <form class="search-form">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i data-feather="search" class="cursor-pointer"></i>
                                        </span>
                                        <input type="text" class="form-control" id="conversation-search"
                                               placeholder="Search here..." autocomplete="off">
                                    </div>
                                </form>
                            </div>

                            <div class="aside-body">
                                <!-- Platform Tabs -->
                                <ul class="nav nav-tabs nav-fill mt-3" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link platform-tab active" data-platform="all"
                                           href="javascript:;" role="tab" title="All Platforms">
                                            <div class="d-flex flex-row flex-lg-column flex-xl-row align-items-center justify-content-center">
                                                <i data-feather="message-square" class="icon-sm me-sm-2 me-lg-0 me-xl-2 mb-md-1 mb-xl-0"></i>
                                                <p class="d-none d-sm-block tx-11">All</p>
                                            </div>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link platform-tab" data-platform="home"
                                           href="javascript:;" role="tab" title="Home Widget">
                                            <div class="d-flex flex-row flex-lg-column flex-xl-row align-items-center justify-content-center">
                                                <i data-feather="home" class="icon-sm me-sm-2 me-lg-0 me-xl-2 mb-md-1 mb-xl-0"></i>
                                                <p class="d-none d-sm-block tx-11">Home</p>
                                            </div>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link platform-tab" data-platform="messenger"
                                           href="javascript:;" role="tab" title="Messenger">
                                            <div class="d-flex flex-row flex-lg-column flex-xl-row align-items-center justify-content-center">
                                                <i data-feather="send" class="icon-sm me-sm-2 me-lg-0 me-xl-2 mb-md-1 mb-xl-0"></i>
                                                <p class="d-none d-sm-block tx-11">Messenger</p>
                                            </div>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link platform-tab" data-platform="whatsapp"
                                           href="javascript:;" role="tab" title="WhatsApp">
                                            <div class="d-flex flex-row flex-lg-column flex-xl-row align-items-center justify-content-center">
                                                <i data-feather="phone" class="icon-sm me-sm-2 me-lg-0 me-xl-2 mb-md-1 mb-xl-0"></i>
                                                <p class="d-none d-sm-block tx-11">WhatsApp</p>
                                            </div>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link platform-tab" data-platform="instagram"
                                           href="javascript:;" role="tab" title="Instagram">
                                            <div class="d-flex flex-row flex-lg-column flex-xl-row align-items-center justify-content-center">
                                                <i data-feather="camera" class="icon-sm me-sm-2 me-lg-0 me-xl-2 mb-md-1 mb-xl-0"></i>
                                                <p class="d-none d-sm-block tx-11">Instagram</p>
                                            </div>
                                        </a>
                                    </li>
                                </ul>

                                <div class="tab-content mt-3">
                                    <div class="tab-pane fade show active" id="chats" role="tabpanel" aria-labelledby="chats-tab">
                                        <div>
                                            <p class="text-muted mb-1">Recent chats</p>
                                            <ul class="list-unstyled chat-list px-1" id="conversation-list">
                                                @forelse($conversations as $conversation)
                                                    <li class="chat-item pe-1 conversation-item"
                                                        data-conversation-id="{{ $conversation->id }}"
                                                        onclick="selectConversation({{ $conversation->id }})">
                                                        <a href="javascript:;" class="d-flex align-items-center">
                                                            <figure class="mb-0 me-2">
                                                                <img src="{{ asset('assets/images/others/placeholder.jpg') }}"
                                                                     class="img-xs rounded-circle" alt="user">
                                                                <div class="status online"></div>
                                                            </figure>
                                                            <div class="d-flex justify-content-between flex-grow-1 border-bottom">
                                                                <div>
                                                                    <p class="text-body fw-bolder">{{ $conversation->customer->name }}</p>
                                                                    <p class="text-muted tx-13 text-truncate">
                                                                        {{ $conversation->messages->last()?->content ?? 'No messages yet' }}
                                                                    </p>
                                                                </div>
                                                                <div class="d-flex flex-column align-items-end conversation-meta">
                                                                    <p class="text-muted tx-13 mb-1">
                                                                        {{ optional($conversation->last_message_at)->format('h:i A') ?? '' }}
                                                                    </p>
                                                                    @if($conversation->unread_count > 0)
                                                                        <div class="badge rounded-pill bg-primary ms-auto">
                                                                            {{ $conversation->unread_count }}
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </a>
                                                    </li>
                                                @empty
                                                    <li class="p-3 text-center text-muted">
                                                        No conversations found
                                                    </li>
                                                @endforelse
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8 chat-content" id="chat-content" style="display: none;">
                        <div class="chat-header border-bottom pb-2">
                            <div class="d-flex justify-content-between">
                                <div class="d-flex align-items-center">
                                    <button type="button" id="backToChat" class="btn btn-link p-0 me-2 ms-n2 text-muted d-lg-none" style="border: none; background: none;">
                                        <i data-feather="corner-up-left" class="icon-lg"></i>
                                    </button>
                                    <figure class="mb-0 me-2">
                                        <img src="{{ asset('assets/images/others/placeholder.jpg') }}"
                                             class="img-sm rounded-circle" alt="image" id="chat-header-avatar">
                                        <div class="status online"></div>
                                    </figure>
                                    <div>
                                        <p id="chat-header-name">Select a conversation</p>
                                        <p class="text-muted tx-13" id="chat-header-platform">-</p>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center me-n1">
                                    <div class="form-check form-switch me-3">
                                        <input class="form-check-input" type="checkbox" id="ai-toggle" style="cursor: pointer;">
                                        <label class="form-check-label ms-2" for="ai-toggle" style="cursor: pointer;">
                                            <i data-feather="cpu" class="icon-sm me-1"></i>
                                            <span id="ai-toggle-label">AI Auto-Reply</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="chat-body" id="messages-container">
                            <ul class="messages" id="messages-list">
                                <!-- Messages will be rendered here -->
                            </ul>
                        </div>

                        <div class="chat-footer d-flex">
                            <div>
                                <button type="button" class="btn border btn-icon rounded-circle me-2" data-bs-toggle="tooltip" title="Emoji">
                                    <i data-feather="smile" class="text-muted"></i>
                                </button>
                            </div>
                            <div class="d-none d-md-block">
                                <button type="button" class="btn border btn-icon rounded-circle me-2" data-bs-toggle="tooltip" title="Attach files">
                                    <i data-feather="paperclip" class="text-muted"></i>
                                </button>
                            </div>
                            <div class="d-none d-md-block">
                                <button type="button" class="btn border btn-icon rounded-circle me-2" data-bs-toggle="tooltip" title="Record voice">
                                    <i data-feather="mic" class="text-muted"></i>
                                </button>
                            </div>
                            <form id="message-form" class="search-form flex-grow-1 me-2">
                                @csrf
                                <input type="hidden" id="conversation-id" value="">
                                <div class="input-group">
                                    <input type="text" class="form-control rounded-pill" id="message-input"
                                           placeholder="Type a message" autocomplete="off">
                                </div>
                            </form>
                            <div>
                                <button type="button" id="ai-suggest-btn" class="btn btn-success btn-icon rounded-circle me-2" data-bs-toggle="tooltip" title="Get AI Suggestion">
                                    <i data-feather="zap" id="ai-suggest-icon"></i>
                                    <span class="spinner-border spinner-border-sm d-none" id="ai-suggest-spinner"></span>
                                </button>
                            </div>
                            <div>
                                <button type="submit" form="message-form" id="send-message-btn" class="btn btn-primary btn-icon rounded-circle">
                                    <i data-feather="send" id="send-icon"></i>
                                    <span class="spinner-border spinner-border-sm d-none" id="send-spinner"></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-center h-100" id="empty-state">
                        <div class="text-center text-muted">
                            <i class="feather feather-message-circle" style="font-size: 64px; opacity: 0.3;"></i>
                            <p class="mt-3">Select a conversation to start messaging</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    @vite(['resources/js/nobleui-inbox.js'])
    <script>
        // Global variables
        const avatarFallbackUrl = "{{ asset('assets/images/others/placeholder.jpg') }}";
        let currentConversationId = null;
        let selectedConversation = null;

        function selectConversation(conversationId) {
            currentConversationId = conversationId;

            // Update UI
            document.querySelectorAll('.conversation-item').forEach(item => {
                item.classList.remove('active');
            });
            const activeItem = document.querySelector(`[data-conversation-id="${conversationId}"]`);
            activeItem.classList.add('active');

            const unreadBadge = activeItem.querySelector('.badge');
            if (unreadBadge) {
                unreadBadge.remove();
                if (typeof window.updateSidebarUnreadBadge === 'function') {
                    window.updateSidebarUnreadBadge();
                }
            }

            // Show chat content and hide empty state
            const chatContent = document.getElementById('chat-content');
            const emptyState = document.getElementById('empty-state');
            const chatAside = document.querySelector('.chat-aside');

            chatContent.style.display = 'block';
            emptyState.style.display = 'none';
            document.getElementById('conversation-id').value = conversationId;

            // Hide sidebar on mobile when opening chat
            if (window.innerWidth < 992 && chatAside) {
                chatAside.style.display = 'none';
            }

            // Fetch conversation data
            fetch(`/api/conversations/${conversationId}`, {
                headers: {
                    'Accept': 'application/json'
                }
            })
                .then(async (r) => {
                    if (!r.ok) {
                        const errorBody = await r.text();
                        throw new Error(`Request failed (${r.status}): ${errorBody}`);
                    }
                    return r.json();
                })
                .then(data => {
                    selectedConversation = data.conversation;

                    // Update header
                    document.getElementById('chat-header-name').textContent = data.conversation.customer.name;
                    document.getElementById('chat-header-platform').textContent =
                        `${data.conversation.platform.charAt(0).toUpperCase() + data.conversation.platform.slice(1)}`;

                    // Update AI toggle state
                    const aiToggle = document.getElementById('ai-toggle');
                    if (aiToggle) {
                        aiToggle.checked = data.conversation.ai_enabled || false;
                        feather.replace(); // Re-render icons
                    }

                    // Render messages
                    renderMessages(data.messages);

                    // Scroll to bottom
                    scrollToBottom();

                    if (typeof window.markConversationAsRead === 'function') {
                        window.markConversationAsRead(conversationId);
                    }
                })
                .catch(err => {
                    console.error('Error loading conversation:', err);
                    showNotification('Unable to load conversation', 'Please refresh and try again.');
                });
        }

        function renderMessages(messages) {
            const messagesList = document.getElementById('messages-list');
            messagesList.innerHTML = '';

            messages.forEach(msg => {
                const isAdmin = msg.sender_type === 'admin';
                const isBot = msg.sender_type === 'bot';
                const isMe = isAdmin;
                const className = isAdmin ? 'me' : (isBot ? 'bot' : 'friend');
                const bubbleClass = isAdmin ? 'bg-primary text-white' : (isBot ? 'bg-success text-white' : '');
                const senderLabel = isBot ? `<span class="text-muted tx-11 ms-2">Bot</span>` : '';

                const messageHTML = `
                    <li class="message-item ${className}">
                        <img src="${avatarFallbackUrl}" class="img-xs rounded-circle" alt="${msg.sender_name}">
                        <div class="content">
                            <div class="message">
                                <div class="bubble ${bubbleClass}">
                                    <p>${escapeHtml(msg.content)}</p>
                                </div>
                                <span>${new Date(msg.created_at).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'})}${senderLabel}</span>
                            </div>
                        </div>
                    </li>
                `;
                messagesList.insertAdjacentHTML('beforeend', messageHTML);
            });
        }

        function appendMessage(message) {
            const isAdmin = message.sender_type === 'admin';
            const isBot = message.sender_type === 'bot';
            const isMe = isAdmin;
            const className = isAdmin ? 'me' : (isBot ? 'bot' : 'friend');
            const bubbleClass = isAdmin ? 'bg-primary text-white' : (isBot ? 'bg-success text-white' : '');
            const senderLabel = isBot ? `<span class="text-muted tx-11 ms-2">Bot</span>` : '';

            const messageHTML = `
                <li class="message-item ${className}">
                    <img src="${avatarFallbackUrl}" class="img-xs rounded-circle" alt="${message.sender_name}">
                    <div class="content">
                        <div class="message">
                            <div class="bubble ${bubbleClass}">
                                <p>${escapeHtml(message.content)}</p>
                            </div>
                            <span>${new Date(message.created_at).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'})}${senderLabel}</span>
                        </div>
                    </div>
                </li>
            `;
            document.getElementById('messages-list').insertAdjacentHTML('beforeend', messageHTML);
            scrollToBottom();
        }

        function scrollToBottom() {
            const container = document.getElementById('messages-container');
            container.scrollTop = container.scrollHeight;
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

        // Message form submission
        let isSending = false; // Prevent duplicate submissions
        document.getElementById('message-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const content = document.getElementById('message-input').value;
            if (!content.trim() || isSending) return;

            const sendBtn = document.getElementById('send-message-btn');
            const sendIcon = document.getElementById('send-icon');
            const sendSpinner = document.getElementById('send-spinner');
            const messageInput = document.getElementById('message-input');

            // Disable form during submission
            isSending = true;
            sendBtn.disabled = true;
            sendIcon.classList.add('d-none');
            sendSpinner.classList.remove('d-none');
            messageInput.disabled = true;

            try {
                const response = await fetch(`/api/conversations/${currentConversationId}/messages`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ content })
                });
                if (!response.ok) {
                    const errorBody = await response.text();
                    throw new Error(`Request failed (${response.status}): ${errorBody}`);
                }

                await response.json();
                messageInput.value = '';
                // Message will appear via Pusher broadcast
            } catch (err) {
                console.error('Error sending message:', err);
                showNotification('Send failed', 'Message could not be sent.');
            } finally {
                // Re-enable form
                isSending = false;
                sendBtn.disabled = false;
                sendIcon.classList.remove('d-none');
                sendSpinner.classList.add('d-none');
                messageInput.disabled = false;
                messageInput.focus();
            }
        });

        // Enter key to send message (without Shift)
        document.getElementById('message-input').addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.getElementById('message-form').dispatchEvent(new Event('submit'));
            }
        });

        // Back button (mobile)
        const backBtn = document.getElementById('backToChat');
        if (backBtn) {
            backBtn.style.cursor = 'pointer';
            backBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();

                console.log('Back button clicked!');

                const chatContent = document.getElementById('chat-content');
                const emptyState = document.getElementById('empty-state');
                const chatAside = document.querySelector('.chat-aside');

                // Hide chat content
                if (chatContent) chatContent.style.display = 'none';
                if (emptyState) emptyState.style.display = 'flex';

                // Show sidebar on mobile
                if (chatAside) {
                    chatAside.style.display = 'block';
                }

                currentConversationId = null;

                console.log('Returned to conversations list');
            });
        }

        // AI Toggle handler
        document.getElementById('ai-toggle').addEventListener('change', async (e) => {
            if (!currentConversationId) return;

            const isEnabled = e.target.checked;
            console.log('AI toggle changed:', isEnabled);

            try {
                const response = await fetch(`/api/conversations/${currentConversationId}/toggle-ai`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                if (data.success) {
                    showNotification(
                        data.message,
                        isEnabled ? 'AI will automatically respond to customer messages' : 'AI auto-reply disabled'
                    );
                } else {
                    // Revert toggle if failed
                    e.target.checked = !isEnabled;
                    showNotification('Failed to toggle AI', 'Please try again');
                }
            } catch (error) {
                console.error('Error toggling AI:', error);
                // Revert toggle if failed
                e.target.checked = !isEnabled;
                showNotification('Error', 'Failed to update AI settings');
            }
        });

        // AI Suggest button handler
        document.getElementById('ai-suggest-btn').addEventListener('click', async (e) => {
            e.preventDefault();

            if (!currentConversationId) {
                showNotification('Error', 'Please select a conversation first');
                return;
            }

            const btn = e.currentTarget;
            const icon = document.getElementById('ai-suggest-icon');
            const spinner = document.getElementById('ai-suggest-spinner');
            const messageInput = document.getElementById('message-input');

            // Show loading state
            btn.disabled = true;
            icon.classList.add('d-none');
            spinner.classList.remove('d-none');

            try {
                const response = await fetch(`/api/conversations/${currentConversationId}/ai-suggest`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                if (data.success && data.suggestion) {
                    // Put AI suggestion in the message input
                    messageInput.value = data.suggestion;
                    messageInput.focus();
                    showNotification('AI Suggestion', 'Review and edit before sending');
                } else {
                    showNotification('No suggestion', 'AI could not generate a response');
                }
            } catch (error) {
                console.error('Error getting AI suggestion:', error);
                showNotification('Error', error.message || 'Failed to get AI suggestion');
            } finally {
                // Hide loading state
                btn.disabled = false;
                icon.classList.remove('d-none');
                spinner.classList.add('d-none');
            }
        });

        // Search conversations
        document.getElementById('conversation-search').addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            document.querySelectorAll('.conversation-item').forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(query) ? '' : 'none';
            });
        });
    </script>
@endpush
@endsection
