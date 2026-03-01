/**
 * NobleUI Inbox - Real-time Messaging with Pusher
 * Handles real-time message updates via Pusher broadcast
 */

const avatarFallbackUrl = '/assets/images/others/placeholder.jpg';
const browserNotificationIcon = '/assets/images/others/placeholder.jpg';
let currentPlatformFilter = 'all';
let browserNotificationPermissionRequested = false;
let notificationUnavailableNoticeShown = false;
let notificationPermissionGestureBound = false;

document.addEventListener('DOMContentLoaded', function() {
    initializeBrowserNotificationPermission();
    initializeWebPushSubscription();
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
async function handleIncomingMessage(event) {
    const message = event.message;
    const conversation = event.conversation;
    const customer = event.customer;
    const customerName = customer?.name || 'Customer';
    const messagePreview = (message?.content || '').substring(0, 50);

    console.log('Incoming payload:', {
        messageId: message?.id,
        conversationId: conversation?.id,
        customerName: customer?.name,
    });

    // Update conversation in list
    updateConversationInList(conversation, message);

    // If this conversation is currently open, append the message
    const activeConversationId = Number(window.currentConversationId || 0) || null;
    if (activeConversationId && Number(conversation?.id) === activeConversationId) {
        if (typeof window.appendMessage === 'function') {
            window.appendMessage(message);
        }

        // Mark as read if it's from customer
        if (message.sender_type !== 'admin') {
            markConversationAsRead(conversation.id);
        }
    }

    if (message.sender_type !== 'admin') {
        const title = `New message from ${customerName}`;
        showNotification(title, messagePreview);
        await showSystemNotificationWhenHidden(title, messagePreview, conversation?.id);
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
    const activeConversationId = Number(window.currentConversationId || 0) || null;
    if (activeConversationId && Number(conversation?.id) === activeConversationId) {
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

function initializeBrowserNotificationPermission() {
    reportNotificationState('init');

    if (!canUseSystemNotifications()) {
        const reason = hasWebPushSecureContext()
            ? 'System notifications are unavailable in this app context. Open from Safari Home Screen app.'
            : 'System notifications require HTTPS or localhost.';
        notifyNotificationUnavailable(reason);
        return;
    }

    if (Notification.permission === 'default') {
        bindNotificationPermissionRequestOnUserGesture();
    }
}

async function showSystemNotificationWhenHidden(title, message, conversationId = null) {
    reportNotificationState('incoming-event');

    if (!canUseSystemNotifications()) {
        notifyNotificationUnavailable('System notifications are unavailable in this browser context.');
        return;
    }

    if (Notification.permission === 'default') {
        bindNotificationPermissionRequestOnUserGesture();
        notifyNotificationUnavailable('Tap once in the app to allow browser notifications.');
        return;
    }

    if (Notification.permission !== 'granted') {
        notifyNotificationUnavailable('Browser notifications are blocked. Allow them in site settings.');
        return;
    }

    try {
        const notification = new Notification(title, {
            body: message,
            icon: browserNotificationIcon,
            tag: conversationId ? `conversation-${conversationId}` : undefined,
        });

        notification.onclick = () => {
            window.focus();
            notification.close();
        };

        console.log('System notification sent', {
            title,
            conversationId,
        });
    } catch (error) {
        console.error('Error showing system notification:', error);
        notifyNotificationUnavailable('Failed to show system notification. Check OS notification settings.');
    }
}

function canUseSystemNotifications() {
    return 'Notification' in window && hasWebPushSecureContext();
}

function hasWebPushSecureContext() {
    return window.isSecureContext || location.protocol === 'https:' || location.hostname === 'localhost' || isStandaloneDisplayMode();
}

function isStandaloneDisplayMode() {
    return window.matchMedia?.('(display-mode: standalone)')?.matches || window.navigator?.standalone === true;
}

function reportNotificationState(context) {
    const permission = 'Notification' in window ? Notification.permission : 'unsupported';
    console.log('Notification state', {
        context,
        permission,
        isSecureContext: window.isSecureContext,
        protocol: window.location?.protocol,
        host: window.location?.host,
        standalone: isStandaloneDisplayMode(),
        hasFocus: document.hasFocus(),
        hidden: document.hidden,
    });
}

function notifyNotificationUnavailable(reason) {
    if (notificationUnavailableNoticeShown) {
        return;
    }

    notificationUnavailableNoticeShown = true;
    console.warn('System notification unavailable:', reason);

    showNotification('Browser popup unavailable', reason);
}

function bindNotificationPermissionRequestOnUserGesture() {
    if (notificationPermissionGestureBound || browserNotificationPermissionRequested || Notification.permission !== 'default') {
        return;
    }

    notificationPermissionGestureBound = true;

    const requestFromGesture = async () => {
        document.removeEventListener('click', requestFromGesture, true);
        document.removeEventListener('touchend', requestFromGesture, true);

        notificationPermissionGestureBound = false;
        await requestNotificationPermissionFromGesture();
    };

    document.addEventListener('click', requestFromGesture, true);
    document.addEventListener('touchend', requestFromGesture, true);
}

async function requestNotificationPermissionFromGesture() {
    if (!('Notification' in window) || Notification.permission !== 'default' || browserNotificationPermissionRequested) {
        return;
    }

    browserNotificationPermissionRequested = true;

    try {
        const permission = await Notification.requestPermission();

        if (permission === 'granted') {
            notificationUnavailableNoticeShown = false;
            await initializeWebPushSubscription();
            return;
        }

        notifyNotificationUnavailable('Browser notifications are blocked. Allow them in site settings.');
    } catch (error) {
        console.warn('Notification permission request failed:', error);
        browserNotificationPermissionRequested = false;
    }
}

async function initializeWebPushSubscription() {
    const vapidPublicKey = document.querySelector('meta[name="webpush-public-key"]')?.content || '';

    if (!vapidPublicKey) {
        return;
    }

    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window) || !hasWebPushSecureContext()) {
        console.warn('Web push not supported in this environment.');
        return;
    }

    let permission = Notification.permission;

    if (permission === 'default') {
        bindNotificationPermissionRequestOnUserGesture();
        console.log('Web push subscription deferred: waiting for user gesture permission prompt.');
        return;
    }

    if (permission !== 'granted') {
        console.warn('Web push subscription skipped: notification permission is not granted.');
        return;
    }

    try {
        const registration = await navigator.serviceWorker.register('/admin-sw.js', {
            scope: '/admin/',
        });

        let subscription = await registration.pushManager.getSubscription();

        if (!subscription) {
            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
            });
        }

        await fetch('/admin/push-subscriptions', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify(subscription.toJSON()),
        });

        console.log('Web push subscription active');
    } catch (error) {
        console.error('Failed to initialize web push subscription:', error);
    }
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }

    return outputArray;
}

/**
 * Show notification toast
 * @param {string} title - Notification title
 * @param {string} message - Notification message
 */
function showNotification(title, message) {
    const toastContainer = getOrCreateNotificationContainer();

    if (typeof bootstrap === 'undefined' || !bootstrap.Toast) {
        const fallback = document.createElement('div');
        fallback.className = 'alert alert-primary shadow-sm mb-2';
        fallback.innerHTML = `<strong>${title}</strong><div>${message}</div>`;
        toastContainer.appendChild(fallback);
        setTimeout(() => fallback.remove(), 3500);
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

function getOrCreateNotificationContainer() {
    let container = document.getElementById('inbox-runtime-toast-container');

    if (!container) {
        container = document.createElement('div');
        container.id = 'inbox-runtime-toast-container';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '2000';
        document.body.appendChild(container);
    }

    return container;
}

window.showNotification = showNotification;
window.updateSidebarUnreadBadge = updateSidebarUnreadBadge;
window.markConversationAsRead = markConversationAsRead;
