/**
 * NobleUI Inbox - Real-time Messaging with Pusher
 * Handles real-time message updates via Pusher broadcast
 */

const avatarFallbackUrl = '/assets/images/others/placeholder.jpg';
let currentPlatformFilter = 'all';

document.addEventListener('DOMContentLoaded', function() {
    initializePusherListeners();
    initializePlatformTabs();
});

/**
 * Initialize platform tab click handlers
 */
function initializePlatformTabs() {
    const platformTabs = document.querySelectorAll('.platform-tab');

    platformTabs.forEach(tab => {
        tab.addEventListener('click', (e) => {
            e.preventDefault();

            // Update active tab styling
            platformTabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            // Load conversations for selected platform
            currentPlatformFilter = tab.dataset.platform;
            loadConversationsByPlatform(currentPlatformFilter);
        });
    });
}

/**
 * Initialize Pusher event listeners for real-time messaging
 */
function initializePusherListeners() {
    // Check if Echo/Pusher is available
    if (typeof window.Echo === 'undefined') {
        console.warn('Pusher/Echo not available, real-time features disabled');
        return;
    }

    console.log('Initializing Pusher listeners...');

    const pusher = window.Echo.connector?.pusher;
    if (pusher) {
        pusher.connection.bind('state_change', (states) => {
            console.log('Pusher state change:', states.current);
        });
        pusher.connection.bind('connected', () => {
            console.log('Pusher connected');
        });
        pusher.connection.bind('error', (err) => {
            console.error('Pusher connection error:', err);
        });
    }

    // Subscribe to private inbox channel for broadcasts
    const channel = window.Echo.private('inbox');
    console.log('Subscribing to private channel: inbox');

    channel
        .listen('.MessageReceived', (event) => {
            console.log('MessageReceived event:', event);
            handleIncomingMessage(event);
        })
        .listen('.ConversationStatusChanged', (event) => {
            console.log('ConversationStatusChanged event:', event);
            handleConversationStatusChange(event);
        });

    channel.subscribed(() => {
        console.log('Subscribed to private-inbox channel');
    });

    channel.error((error) => {
        console.error('Pusher subscription error:', error);
    });

    console.log('Pusher listeners initialized');
}

/**
 * Handle incoming message from Pusher broadcast
 * @param {Object} event - The broadcast event data
 */
function handleIncomingMessage(event) {
    const message = event.message;
    const conversation = event.conversation;
    const customer = event.customer;

    console.log('Incoming payload:', {
        messageId: message?.id,
        conversationId: conversation?.id,
        customerName: customer?.name,
    });

    // Update conversation in list
    updateConversationInList(conversation, message);

    // If this conversation is currently open, append the message
    if (currentConversationId === conversation.id) {
        appendMessage(message);

        // Mark as read if it's from customer
        if (message.sender_type !== 'admin') {
            markConversationAsRead(conversation.id);
        }
    }

    if (message.sender_type !== 'admin') {
        showNotification(
            `New message from ${customer?.name || 'Customer'}`,
            message.content.substring(0, 50)
        );
    }
}

/**
 * Update conversation in the sidebar list
 * @param {Object} conversation - The conversation object
 */
function updateConversationInList(conversation, message) {
    const conversationItem = document.querySelector(
        `.conversation-item[data-conversation-id="${conversation.id}"]`
    );

    console.log('Update conversation list:', {
        conversationId: conversation?.id,
        hasItem: Boolean(conversationItem),
        unread: conversation?.unread_count,
    });

    if (!conversationItem) {
        // If conversation doesn't exist in list, add it at the top
        addConversationToList(conversation);
        return;
    }

    // Move to top and update preview
    const chatList = document.getElementById('conversation-list');
    chatList.insertBefore(conversationItem, chatList.firstChild);

    // Update last message preview
    const lastMessage = message || conversation.messages?.[conversation.messages.length - 1];
    if (lastMessage) {
        const previewElement = conversationItem.querySelector('.text-truncate');
        if (previewElement) {
            previewElement.textContent = lastMessage.content;
        }
    }

    // Update unread count
    const badgeElement = conversationItem.querySelector('.badge');
    const metaContainer = conversationItem.querySelector('.conversation-meta');
    if (conversation.unread_count > 0) {
        if (badgeElement) {
            badgeElement.textContent = conversation.unread_count;
        } else {
            const badge = document.createElement('div');
            badge.className = 'badge rounded-pill bg-primary ms-auto';
            badge.textContent = conversation.unread_count;
            if (metaContainer) {
                metaContainer.appendChild(badge);
            }
        }
    } else if (badgeElement) {
        badgeElement.remove();
    }

    updateSidebarUnreadBadge();
}

/**
 * Add a new conversation to the list
 * @param {Object} conversation - The conversation object
 */
function addConversationToList(conversation) {
    const chatList = document.getElementById('conversation-list');
    if (!chatList) return;

    const lastMessage = conversation.messages?.[conversation.messages.length - 1];
    const customerName = conversation.customer?.name || 'Unknown Customer';

    const conversationItem = document.createElement('li');
    conversationItem.className = 'chat-item pe-1 conversation-item';
    conversationItem.setAttribute('data-conversation-id', conversation.id);
    conversationItem.onclick = () => selectConversation(conversation.id);

    conversationItem.innerHTML = `
        <a href="javascript:;" class="d-flex align-items-center">
            <figure class="mb-0 me-2">
                <img src="${avatarFallbackUrl}" class="img-xs rounded-circle" alt="user">
                <div class="status online"></div>
            </figure>
            <div class="d-flex justify-content-between flex-grow-1 border-bottom">
                <div>
                    <p class="text-body fw-bolder">${customerName}</p>
                    <p class="text-muted tx-13 text-truncate">${lastMessage?.content || 'No messages yet'}</p>
                </div>
                <div class="d-flex flex-column align-items-end conversation-meta">
                    <p class="text-muted tx-13 mb-1"></p>
                    ${conversation.unread_count > 0 ? `<div class="badge rounded-pill bg-primary ms-auto">${conversation.unread_count}</div>` : ''}
                </div>
            </div>
        </a>
    `;

    chatList.insertBefore(conversationItem, chatList.firstChild);

    updateSidebarUnreadBadge();
}

/**
 * Load conversations filtered by platform
 * @param {string} platform - The platform filter ('all', 'messenger', 'whatsapp', 'instagram')
 */
function loadConversationsByPlatform(platform) {
    const chatList = document.getElementById('conversation-list');
    if (!chatList) {
        console.warn('Conversation list element not found');
        return;
    }

    // Clear the list
    chatList.innerHTML = '';

    // Build query parameter
    const url = platform === 'all'
        ? '/api/conversations'
        : `/api/conversations?platform=${encodeURIComponent(platform)}`;

    fetch(url, {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        }
    })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            // Handle paginated response structure
            const conversations = data.data || data;

            if (Array.isArray(conversations) && conversations.length === 0) {
                chatList.innerHTML = '<li class="p-3 text-center text-muted">No conversations found</li>';
                return;
            }

            // Render each conversation
            conversations.forEach(conversation => {
                addConversationToList(conversation);
            });

            console.log(`Loaded ${conversations.length} conversations for platform: ${platform}`);
        })
        .catch(err => {
            console.error(`Error loading conversations for platform ${platform}:`, err);
            chatList.innerHTML = '<li class="p-3 text-center text-muted">Error loading conversations</li>';
        });
}

function updateSidebarUnreadBadge() {
    const sidebarBadge = document.getElementById('sidebar-inbox-badge');
    if (!sidebarBadge) {
        return;
    }

    const badges = document.querySelectorAll('#conversation-list .badge');
    const total = Array.from(badges).reduce((sum, badge) => {
        const value = parseInt(badge.textContent, 10);
        return sum + (Number.isNaN(value) ? 0 : value);
    }, 0);

    sidebarBadge.textContent = total;
    sidebarBadge.dataset.unreadCount = total;
    sidebarBadge.classList.toggle('d-none', total === 0);
}

/**
 * Handle conversation status change
 * @param {Object} event - The broadcast event data
 */
function handleConversationStatusChange(event) {
    const conversation = event.conversation;
    console.log('Conversation status changed:', conversation.status);

    // Update UI if needed
    if (currentConversationId === conversation.id) {
        // Could update status badge or other UI elements here
    }
}

/**
 * Mark conversation as read
 * @param {number} conversationId - The conversation ID
 */
function markConversationAsRead(conversationId) {
    fetch(`/api/conversations/${conversationId}/read`, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
        .then(() => updateSidebarUnreadBadge())
        .catch(err => console.error('Error marking as read:', err));
}

/**
 * Show notification toast
 * @param {string} title - Notification title
 * @param {string} message - Notification message
 */
function showNotification(title, message) {
    const toastContainer = document.querySelector('.toast-container') ||
                          document.querySelector('.position-fixed');

    if (!toastContainer) return;

    if (typeof bootstrap === 'undefined' || !bootstrap.Toast) {
        console.warn('Bootstrap Toast not available. Skipping notification.');
        return;
    }

    const toast = document.createElement('div');
    toast.className = 'toast align-items-center text-white bg-primary border-0 shadow-lg';
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');

    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <strong>${title}</strong>
                <p class="mb-0 text-truncate">${message}</p>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto"
                    data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;

    toastContainer.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();

    // Remove after disappears
    toast.addEventListener('hidden.bs.toast', () => toast.remove());
}

window.showNotification = showNotification;
window.updateSidebarUnreadBadge = updateSidebarUnreadBadge;
window.markConversationAsRead = markConversationAsRead;
