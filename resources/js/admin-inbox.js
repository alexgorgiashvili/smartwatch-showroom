/**
 * Admin Inbox — SPA-like conversation manager
 */
let cfg = {};
let state = {
    conversationId: null,
    page: 1,
    pollTimer: null,
    seenMessageIds: new Set(),
};

const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

function isLocalhostRuntime() {
    return ['127.0.0.1', 'localhost'].includes(window.location.hostname);
}

function subscribeToInboxChannel(name) {
    if (!window.Echo) return null;

    return isLocalhostRuntime()
        ? window.Echo.channel(name)
        : window.Echo.private(name);
}

function updateGlobalSidebarBadge(unread) {
    const badge = document.getElementById('sidebar-inbox-badge')
        || document.querySelector('a[href*="/admin/inbox"] [data-inbox-badge], a[href*="/inbox"] [data-inbox-badge]');

    if (!badge) return;

    const safeUnread = Math.max(0, parseInt(unread || 0, 10) || 0);
    badge.textContent = `${safeUnread}`;
    badge.dataset.unreadCount = `${safeUnread}`;
    badge.classList.toggle('d-none', safeUnread <= 0);
}

function updateConversationBadge(conversationId, unread) {
    const item = $(`[data-cid="${conversationId}"]`);
    if (!item) return;

    const container = item.closest('.chat-item')?.querySelector('.d-flex.flex-column.align-items-end.justify-content-center.ps-2');
    if (!container) return;

    const safeUnread = Math.max(0, parseInt(unread || 0, 10) || 0);
    let badge = container.querySelector('.badge.bg-danger');

    if (safeUnread <= 0) {
        badge?.remove();
        return;
    }

    if (!badge) {
        badge = document.createElement('span');
        badge.className = 'badge rounded-pill bg-danger';
        container.prepend(badge);
    }

    badge.textContent = `${safeUnread}`;
}

async function markConversationRead(conversationId) {
    if (!conversationId || !cfg.markReadUrl) return;

    try {
        await axios.post(cfg.markReadUrl.replace('{id}', conversationId));
        updateConversationBadge(conversationId, 0);
        refreshCounts();
    } catch (_) {}
}

// ── Platform colors / icons ─────────────────────────────────────
const platformBadge = (p) => {
    const map = {
        facebook:  { bg: 'primary',   label: 'FB' },
        instagram: { bg: 'danger',    label: 'IG' },
        whatsapp:  { bg: 'success',   label: 'WA' },
        messenger: { bg: 'info',      label: 'MSG' },
        home:      { bg: 'secondary', label: 'Web' },
    };
    const m = map[p] || { bg: 'secondary', label: p };
    return `<span class="badge bg-${m.bg}" style="font-size:10px;">${m.label}</span>`;
};

// ── Load conversation list ──────────────────────────────────────
async function loadConversations() {
    const list = $('#inbox-conversation-list');
    const loading = $('#inbox-list-loading');
    if (!list) return;

    const params = new URLSearchParams({
        platform: $('#inbox-platform-filter')?.value || 'all',
        status:   $('#inbox-status-filter')?.value || 'all',
        search:   $('#inbox-search')?.value || '',
        page:     state.page,
    });

    try {
        if (loading) loading.classList.remove('d-none');
        const res = await axios.get(cfg.conversationsUrl + '?' + params.toString());
        const payload = res.data || {};
        const data = Array.isArray(payload.data)
            ? payload.data
            : (Array.isArray(payload.data?.data) ? payload.data.data : []);
        const meta = payload.meta || payload.data?.meta || null;
        const metaSafe = meta || (payload.data && typeof payload.data === 'object' ? {
            current_page: payload.data.current_page,
            last_page: payload.data.last_page,
            total: payload.data.total,
        } : null);
        if (loading) loading.classList.add('d-none');

        // Render items
        if (data.length === 0) {
            list.innerHTML = '<div class="text-center py-4 text-muted small">No conversations</div>';
        } else {
            list.innerHTML = `<ul class="chat-list">${data.map(c => conversationItem(c)).join('')}</ul>`;
        }

        // Pagination
        const pag = $('#inbox-list-pagination');
        if (pag && metaSafe) {
            pag.classList.toggle('d-none', (metaSafe.last_page || 1) <= 1);
            const prev = $('#inbox-prev-page');
            const next = $('#inbox-next-page');
            const info = $('#inbox-page-info');
            if (prev) prev.disabled = (metaSafe.current_page || 1) <= 1;
            if (next) next.disabled = (metaSafe.current_page || 1) >= (metaSafe.last_page || 1);
            if (info) info.textContent = `${metaSafe.current_page || 1} / ${metaSafe.last_page || 1}`;
        }

        // Re-highlight selected
        if (state.conversationId) {
            const active = list.querySelector(`[data-cid="${state.conversationId}"]`);
            if (active) (active.closest('.chat-item') || active).classList.add('active');
        }
    } catch (e) {
        if (loading) loading.innerHTML = '<div class="text-muted small py-4">Failed to load</div>';
    }
}

function conversationItem(c) {
    const unread = c.unread_count > 0 ? `<span class="badge rounded-pill bg-danger">${c.unread_count}</span>` : '';
    const initial = (c.customer_name || '?').charAt(0).toUpperCase();
    const preview = c.last_message ? c.last_message.content : '';
    const time = c.last_message_at || '';

    return `<li class="chat-item">
        <button type="button" class="inbox-conv-item w-100 text-start border-0 bg-transparent p-0" data-cid="${c.id}" aria-label="Open conversation">
            <div class="d-flex align-items-center">
                <div class="position-relative me-2">
                    <div class="avatar-placeholder">${esc(initial)}</div>
                </div>
                <div class="d-flex justify-content-between flex-grow-1 border-bottom" style="min-width:0;">
                    <div class="me-2 overflow-hidden" style="min-width:0;">
                        <p class="mb-0 fw-bolder text-truncate">${esc(c.customer_name)}</p>
                        <p class="mb-0 tx-13 text-muted inbox-conv-preview">${esc(preview)}</p>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            ${platformBadge(c.platform)}
                            <span class="text-muted tx-12">${esc(time)}</span>
                        </div>
                    </div>
                    <div class="d-flex flex-column align-items-end justify-content-center ps-2">
                        ${unread}
                    </div>
                </div>
            </div>
        </button>
    </li>`;
}

async function refreshCounts() {
    if (!cfg.countsUrl) return;
    try {
        const res = await axios.get(cfg.countsUrl);
        const unread = parseInt(res.data?.unread || 0, 10);
        const pill = $('#inbox-unread-pill');
        if (pill) {
            pill.textContent = `${unread}`;
            pill.classList.toggle('d-none', unread <= 0);
        }
        updateGlobalSidebarBadge(unread);
    } catch (_) {}
}

// ── Load messages for a conversation ────────────────────────────
async function openConversation(id) {
    const prevConversationId = state.conversationId;
    state.conversationId = id;

    // Highlight in sidebar
    $$('.chat-item').forEach(el => el.classList.remove('active'));
    const item = $(`[data-cid="${id}"]`);
    if (item) {
        (item.closest('.chat-item') || item).classList.add('active');
        // Clear unread badge
        const badge = item.querySelector('.badge.bg-danger');
        if (badge) badge.remove();
    }

    const messagesEl = $('#inbox-messages');
    const header = $('#inbox-chat-header');
    const inputArea = $('#inbox-input-area');
    const emptyState = $('#inbox-empty-state');

    if (emptyState) emptyState.classList.add('d-none');
    if (header) header.classList.remove('d-none');
    if (inputArea) inputArea.classList.remove('d-none');
    if (messagesEl) messagesEl.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm" role="status" aria-label="Loading"></div></div>';

    const chatContent = document.querySelector('.chat-wrapper .chat-content');
    if (chatContent) chatContent.classList.add('show');

    if (window.Echo) {
        if (prevConversationId) window.Echo.leave(`inbox.conversation.${prevConversationId}`);
        subscribeToInboxChannel(`inbox.conversation.${id}`)
            ?.stopListening('.MessageReceived')
            .listen('.MessageReceived', onMessageReceived);
    }

    try {
        const url = cfg.messagesUrl.replace('{id}', id);
        const res = await axios.get(url);
        const { conversation: conv, messages } = res.data;

        // Header
        const initial = (conv.customer_name || '?').charAt(0).toUpperCase();
        const avatarEl = $('#inbox-chat-avatar');
        if (avatarEl) avatarEl.textContent = initial;
        const nameEl = $('#inbox-chat-name');
        if (nameEl) nameEl.textContent = conv.customer_name;
        const platEl = $('#inbox-chat-platform');
        if (platEl) platEl.innerHTML = `${platformBadge(conv.platform)} &middot; ${esc(conv.status)}`;

        // Details panel
        setText('#inbox-detail-name', conv.customer_name);
        setText('#inbox-detail-email', conv.customer_email || '—');
        setText('#inbox-detail-phone', conv.customer_phone || '—');
        setHtml('#inbox-detail-platform', platformBadge(conv.platform));
        setHtml('#inbox-detail-status', `<span class="badge bg-${conv.status === 'active' ? 'success' : 'secondary'}">${esc(conv.status)}</span>`);
        setHtml('#inbox-detail-priority', `<span class="badge bg-info">${esc(conv.priority)}</span>`);
        setHtml('#inbox-detail-ai', `<span class="badge bg-dark">${esc(conv.ai_mode)}</span>`);

        // Messages
        renderMessages(messages);

        // Refresh list to clear unread count
        loadConversations();
        refreshCounts();
    } catch (e) {
        if (messagesEl) messagesEl.innerHTML = '<div class="text-center text-danger py-4">Failed to load messages</div>';
    }
}

function renderMessages(messages) {
    const body = $('#inbox-messages');
    if (!body) return;

    const emptyState = $('#inbox-empty-state');
    if (emptyState) emptyState.classList.add('d-none');

    if (!messages || messages.length === 0) {
        body.innerHTML = '<div class="text-center text-muted py-4">No messages yet</div>';
        return;
    }

    const wasNearBottom = isNearBottom(body);
    body.innerHTML = '<ul class="messages"></ul>';
    const list = body.querySelector('ul.messages');
    if (!list) return;

    let lastDate = '';
    let html = '';

    messages.forEach(m => {
        if (m.created_date && m.created_date !== lastDate) {
            lastDate = m.created_date;
            html += `<li class="text-center my-2"><span class="badge bg-light text-dark">${esc(lastDate)}</span></li>`;
        }
        html += messageItem(m);
    });

    list.innerHTML = html;
    if (wasNearBottom) body.scrollTop = body.scrollHeight;
}

function isNearBottom(el) {
    if (!el) return true;
    return (el.scrollHeight - el.scrollTop - el.clientHeight) < 80;
}

function messageItem(m) {
    const isMe = String(m.sender_type || '') === 'admin';
    const meClass = isMe ? ' me' : '';
    const bubbleExtraClass = m.is_bot ? ' inbox-bubble-bot' : '';
    const nameInitial = (m.sender_name || (m.is_customer ? 'Customer' : 'Admin')).charAt(0).toUpperCase();
    const avatar = `<div class="avatar-placeholder">${esc(nameInitial)}</div>`;
    const attachment = m.media_url
        ? `<div class="mt-2"><a href="${esc(m.media_url)}" target="_blank" rel="noopener" class="tx-13">Attachment</a></div>`
        : '';
    const botLabel = m.is_bot ? '<div class="fw-bolder tx-12 mb-1">Bot</div>' : '';
    const failed = m.delivery_status === 'failed' ? ' <span class="text-danger">✗</span>' : '';

    if (m.id) state.seenMessageIds.add(m.id);

    return `<li class="message-item${meClass}" data-mid="${esc(String(m.id || ''))}">
        ${avatar}
        <div class="content">
            <div class="bubble${bubbleExtraClass}">
                ${botLabel}
                <div style="white-space:pre-wrap;">${esc(m.content || '')}</div>
                ${attachment}
            </div>
            <span>${esc(m.created_at || '')}${failed}</span>
        </div>
    </li>`;
}

// ── Send message ────────────────────────────────────────────────
async function sendMessage() {
    const input = $('#inbox-message-input');
    const content = input?.value?.trim();
    if (!content || !state.conversationId) return;

    const btn = $('#inbox-send-btn');
    if (btn) btn.disabled = true;
    input.value = '';

    try {
        const url = cfg.sendUrl.replace('{id}', state.conversationId);
        const res = await axios.post(url, { content });

        if (res.data.message) {
            appendMessage(res.data.message);
        }
    } catch (e) {
        const msg = e.response?.data?.error || 'Send failed';
        if (window.AdminHelpers) window.AdminHelpers.showToast(msg, 'error');
    } finally {
        if (btn) btn.disabled = false;
        input?.focus();
    }
}

function appendMessage(m) {
    const body = $('#inbox-messages');
    if (!body) return;

    const emptyState = $('#inbox-empty-state');
    if (emptyState) emptyState.classList.add('d-none');

    let list = body.querySelector('ul.messages');
    if (!list) {
        body.innerHTML = '<ul class="messages"></ul>';
        list = body.querySelector('ul.messages');
    }
    if (!list) return;

    const wasNearBottom = isNearBottom(body);
    list.insertAdjacentHTML('beforeend', messageItem(m));
    if (wasNearBottom) body.scrollTop = body.scrollHeight;
}

// ── Actions: status, priority, AI toggle ────────────────────────
async function setStatus(status) {
    if (!state.conversationId) return;
    try {
        await axios.patch(cfg.statusUrl.replace('{id}', state.conversationId), { status });
        if (window.AdminHelpers) window.AdminHelpers.showToast(`Status → ${status}`, 'success');
        openConversation(state.conversationId);
    } catch (_) {
        if (window.AdminHelpers) window.AdminHelpers.showToast('Failed to update status', 'error');
    }
}

async function setPriority(priority) {
    if (!state.conversationId) return;
    try {
        await axios.patch(cfg.priorityUrl.replace('{id}', state.conversationId), { priority });
        if (window.AdminHelpers) window.AdminHelpers.showToast(`Priority → ${priority}`, 'success');
        openConversation(state.conversationId);
    } catch (_) {
        if (window.AdminHelpers) window.AdminHelpers.showToast('Failed to update priority', 'error');
    }
}

async function toggleAi() {
    if (!state.conversationId) return;
    try {
        await axios.post(cfg.toggleAiUrl.replace('{id}', state.conversationId));
        if (window.AdminHelpers) window.AdminHelpers.showToast('AI mode toggled', 'success');
        openConversation(state.conversationId);
    } catch (_) {
        if (window.AdminHelpers) window.AdminHelpers.showToast('Failed to toggle AI', 'error');
    }
}

// ── Helpers ─────────────────────────────────────────────────────
function esc(s) {
    if (!s) return '';
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

function setText(sel, val) { const el = $(sel); if (el) el.textContent = val || '—'; }
function setHtml(sel, val) { const el = $(sel); if (el) el.innerHTML = val || '—'; }

function startFallbackPolling() {
    if (state.pollTimer) clearInterval(state.pollTimer);
    state.pollTimer = setInterval(() => {
        loadConversations();
        refreshCounts();
    }, 20000);
}

function stopFallbackPolling() {
    if (state.pollTimer) clearInterval(state.pollTimer);
    state.pollTimer = null;
}

async function onMessageReceived(e) {
    const mid = parseInt(e?.message?.id || 0, 10);
    if (!mid || state.seenMessageIds.has(mid)) return;

    const cid = parseInt(e?.conversation?.id || 0, 10);
    if (!cid) return;

    const senderType = String(e?.message?.sender_type || '');
    const isCustomerMessage = senderType === 'customer';
    const isActiveConversation = state.conversationId && cid === parseInt(state.conversationId, 10);

    if (isActiveConversation) {
        const normalized = {
            id: mid,
            sender_type: e.message.sender_type,
            sender_name: e.message.sender_name,
            content: e.message.content || '',
            media_url: e.message.media_url,
            media_type: e.message.media_type,
            delivery_status: e.message.delivery_status,
            created_at: formatTime(e.message.created_at),
            created_date: formatDate(e.message.created_at),
            is_customer: e.message.sender_type === 'customer',
            is_bot: e.message.sender_type === 'bot',
        };
        appendMessage(normalized);

        if (isCustomerMessage) {
            await markConversationRead(cid);
            updateConversationBadge(cid, 0);
        }
    } else {
        if (isCustomerMessage) {
            updateConversationBadge(cid, parseInt(e?.conversation?.unread_count || 1, 10));
            const name = e?.customer?.name ? String(e.customer.name) : 'New message';
            const platform = String(e?.platform || e?.conversation?.platform || '').toLowerCase();
            const preview = String(e?.message?.content || '').slice(0, 90);
            const toastText = platform ? `${name} • ${platform}` : name;
            if (window.AdminHelpers) window.AdminHelpers.showToast(toastText, 'info');

            window.dispatchEvent(new CustomEvent('inbox-browser-notification', {
                detail: {
                    title: 'New message',
                    body: preview ? `${name}: ${preview}` : name,
                    conversationId: cid,
                },
            }));
        }
    }

    refreshCounts();
    loadConversations();
}

function formatTime(value) {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function formatDate(value) {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleDateString([], { month: 'short', day: '2-digit', year: 'numeric' });
}

// ── Init / Teardown ─────────────────────────────────────────────
export function initInbox() {
    const configEl = document.getElementById('inbox-config');
    if (!configEl) return;

    cfg = JSON.parse(configEl.textContent);
    state = { conversationId: null, page: 1, pollTimer: null, seenMessageIds: new Set() };

    // Load initial list
    loadConversations();
    refreshCounts();

    const initialConversation = new URLSearchParams(window.location.search).get('conversation');
    if (initialConversation) {
        const cid = parseInt(initialConversation, 10);
        if (!Number.isNaN(cid) && cid > 0) openConversation(cid);
    }

    // Conversation click
    $('#inbox-conversation-list')?.addEventListener('click', (e) => {
        const item = e.target.closest('.inbox-conv-item');
        if (item) openConversation(parseInt(item.dataset.cid, 10));
    });

    $('#backToChatList')?.addEventListener('click', () => {
        const chatContent = document.querySelector('.chat-wrapper .chat-content');
        if (chatContent) chatContent.classList.remove('show');
    });

    $('#inbox-refresh')?.addEventListener('click', () => {
        loadConversations();
        refreshCounts();
    });

    // Filters
    let filterTimeout;
    const debouncedLoad = () => { clearTimeout(filterTimeout); filterTimeout = setTimeout(() => { state.page = 1; loadConversations(); }, 300); };
    $('#inbox-search')?.addEventListener('input', debouncedLoad);
    $('#inbox-platform-filter')?.addEventListener('change', () => { state.page = 1; loadConversations(); });
    $('#inbox-status-filter')?.addEventListener('change', () => { state.page = 1; loadConversations(); });

    // Pagination
    $('#inbox-prev-page')?.addEventListener('click', () => { if (state.page > 1) { state.page--; loadConversations(); } });
    $('#inbox-next-page')?.addEventListener('click', () => { state.page++; loadConversations(); });

    // Send form
    $('#inbox-send-form')?.addEventListener('submit', (e) => { e.preventDefault(); sendMessage(); });

    // Textarea: Enter to send (Shift+Enter for newline)
    $('#inbox-message-input')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    });

    // Status/priority/AI actions
    $$('.inbox-status-action').forEach(el => el.addEventListener('click', (e) => { e.preventDefault(); setStatus(el.dataset.status); }));
    $$('.inbox-priority-action').forEach(el => el.addEventListener('click', (e) => { e.preventDefault(); setPriority(el.dataset.priority); }));
    $('#inbox-btn-toggle-ai')?.addEventListener('click', () => toggleAi());

    // Start Echo listeners instead of polling
    if (window.Echo) {
        subscribeToInboxChannel('inbox')
            ?.stopListening('.MessageReceived')
            .listen('.MessageReceived', onMessageReceived);
        stopFallbackPolling();
    } else {
        startFallbackPolling();
    }

    if (window.PerfectScrollbar) {
        const chatsEl = document.querySelector('.chat-aside .tab-content #chats');
        if (chatsEl) new window.PerfectScrollbar(chatsEl);
        const bodyEl = document.querySelector('.chat-content .chat-body');
        if (bodyEl) new window.PerfectScrollbar(bodyEl);
    }

    // feather icons
    if (window.feather) window.feather.replace();
}

export function destroyInbox() {
    if (window.Echo) {
        window.Echo.leave('inbox');
        if (state.conversationId) window.Echo.leave(`inbox.conversation.${state.conversationId}`);
    }
    stopFallbackPolling();
}
