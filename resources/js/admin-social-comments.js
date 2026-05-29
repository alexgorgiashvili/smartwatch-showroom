/**
 * Admin Social Comments — DataTable + AI Reply + Bulk Actions
 */
let cfg = {};
let state = { page: 1, selected: new Set(), replyCommentId: null };
const realtimeChannel = 'social.comments';

const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

function isLocalhostRuntime() {
    return ['127.0.0.1', 'localhost'].includes(window.location.hostname);
}

function esc(s) {
    if (!s) return '';
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

function escAttr(s) {
    return esc(s).replace(/"/g, '&quot;');
}

const statusLabels = {
    unread: 'წაუკითხავი', read: 'წაკითხული', replied: 'გაპასუხ.',
    spam: 'სპამი', hidden: 'დამალული',
};
const statusColors = {
    unread: 'warning', read: 'secondary', replied: 'success',
    spam: 'danger', hidden: 'dark',
};

const statusBadge = (s) => {
    const label = statusLabels[s] || s;
    const color = statusColors[s] || 'secondary';
    return `<span class="badge bg-${color}">${label}</span>`;
};

const sentimentLabels = {
    positive: 'პოზ.', negative: 'ნეგ.', question: 'კითხვა', neutral: 'ნეიტ.',
};
const sentimentBadge = (s) => {
    if (!s) return '<span class="text-muted small">—</span>';
    const map = {
        positive: 'success', negative: 'danger',
        question: 'warning', neutral: 'secondary',
    };
    return `<span class="badge bg-${map[s] || 'secondary'}">${sentimentLabels[s] || s}</span>`;
};

const platformBadge = (p) => {
    if (p === 'instagram') {
        return '<span class="badge" style="font-size:10px;background:linear-gradient(45deg,#405de6,#e1306c);">IG</span>';
    }
    return '<span class="badge bg-primary" style="font-size:10px;">FB</span>';
};

function refreshFeather(root = document) {
    if (!window.feather || typeof window.feather.replace !== 'function') return;

    try {
        window.feather.replace({ width: 14, height: 14, 'stroke-width': 2 });
    } catch (_) {}
}

// ── Load list ───────────────────────────────────────────────────
async function loadComments() {
    const tbody = $('#sc-tbody');
    if (!tbody) return;

    const params = new URLSearchParams({
        status:    $('#sc-filter-status')?.value || 'all',
        platform:  $('#sc-filter-platform')?.value || 'all',
        sentiment: $('#sc-filter-sentiment')?.value || 'all',
        search:    $('#sc-search')?.value || '',
        date_from: $('#sc-date-from')?.value || '',
        date_to:   $('#sc-date-to')?.value || '',
        page:      state.page,
    });

    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></td></tr>';

    try {
        const res = await axios.get(cfg.listUrl + '?' + params.toString());
        const { data, meta, counts } = res.data;

        const unreadBadge = $('#sc-unread-badge');
        const totalBadge = $('#sc-total-badge');
        if (unreadBadge) unreadBadge.textContent = `${counts.unread} წაუკითხავი`;
        if (totalBadge) totalBadge.textContent = `${counts.total} სულ`;

        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">კომენტარები ვერ მოიძებნა</td></tr>';
        } else {
            tbody.innerHTML = data.map(c => commentRow(c)).join('');
        }

        refreshFeather(tbody);

        const pag = $('#sc-pagination');
        if (pag && meta) {
            pag.classList.toggle('d-none', meta.last_page <= 1);
            const prev = $('#sc-prev');
            const next = $('#sc-next');
            const info = $('#sc-page-info');
            if (prev) prev.disabled = meta.current_page <= 1;
            if (next) next.disabled = meta.current_page >= meta.last_page;
            if (info) info.textContent = `გვერდი ${meta.current_page} / ${meta.last_page} • სულ ${meta.total} კომენტარი`;
        }

        state.selected.clear();
        updateBulkUI();
        const selectAll = $('#sc-select-all');
        if (selectAll) selectAll.checked = false;
    } catch (e) {
        console.error('Social comments load error', e);
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">Failed to load comments</td></tr>';
    }
}

function actionButton({ className, id, title, icon, label }) {
    return `<button class="btn btn-sm sc-action-btn ${className}" data-id="${id}" title="${title}" aria-label="${title}">
        <i data-feather="${icon}"></i>
        <span>${label}</span>
    </button>`;
}

function commentRow(c) {
    return `<tr data-id="${c.id}" data-post-id="${c.facebook_post_id || ''}" data-post-preview="${escAttr(c.post_preview || '')}" data-platform="${c.platform}" data-comment-id="${escAttr(c.platform_comment_id || '')}">
        <td><input type="checkbox" class="sc-row-check" data-id="${c.id}"></td>
        <td>
            <div class="fw-bold small d-flex align-items-center gap-1">
                ${platformBadge(c.platform)}
                ${esc(c.author_name)}
            </div>
        </td>
        <td>
            <div class="small" style="max-width:350px;word-break:break-word;">${esc(c.message_short)}</div>
            ${c.post_preview ? `<div class="text-muted" style="font-size:10px;">პოსტი: ${esc(c.post_preview)}</div>` : ''}
            ${c.has_replies ? `<button class="btn btn-link btn-sm p-0 sc-show-replies-btn" data-id="${c.id}" style="font-size:10px;">↳ პასუხები</button>` : ''}
        </td>
        <td>${sentimentBadge(c.sentiment)}</td>
        <td>${statusBadge(c.status)}</td>
        <td><span class="small text-muted">${c.commented_at || '—'}</span></td>
        <td>
            <div class="sc-action-group">
                ${actionButton({ className: 'btn-outline-primary sc-ai-reply-btn', id: c.id, title: 'AI პასუხის შექმნა', icon: 'zap', label: 'AI პასუხი' })}
                ${c.status === 'unread' ? actionButton({ className: 'btn-outline-secondary sc-mark-read-btn', id: c.id, title: 'წაკითხულად მონიშვნა', icon: 'eye', label: 'წაკითხულად' }) : ''}
                ${!['spam', 'hidden'].includes(c.status) ? actionButton({ className: 'btn-outline-warning sc-hide-btn', id: c.id, title: 'კომენტარის დამალვა', icon: 'eye-off', label: 'დამალვა' }) : ''}
                ${!['spam', 'hidden'].includes(c.status) ? actionButton({ className: 'btn-outline-danger sc-mark-spam-btn', id: c.id, title: 'სპამად მონიშვნა', icon: 'slash', label: 'სპამი' }) : ''}
                ${c.facebook_post_id ? actionButton({ className: 'btn-outline-secondary sc-auto-reply-rules-btn', id: c.id, title: 'ავტო-პასუხის წესები', icon: 'settings', label: 'ავტო-პასუხი' }) : ''}
                ${c.author_id ? actionButton({ className: 'btn-outline-dark sc-block-user-btn', id: c.id, title: 'მომხმარებლის დაბლოკვა', icon: 'user-x', label: 'დაბლოკვა' }) : ''}
            </div>
        </td>
    </tr>`;
}

function replyRows(replies) {
    if (!replies || replies.length === 0) {
        return `<tr class="sc-reply-row"><td colspan="7" class="ps-5 text-muted small py-1">პასუხები არ არის</td></tr>`;
    }
    return replies.map(r => `
        <tr class="sc-reply-row table-light">
            <td></td>
            <td class="ps-4">
                <div class="fw-bold small d-flex align-items-center gap-1">
                    <i data-feather="corner-down-right" style="width:10px;height:10px;" class="text-muted"></i>
                    ${platformBadge(r.platform)}
                    ${esc(r.author_name)}
                </div>
            </td>
            <td><div class="small" style="max-width:320px;word-break:break-word;">${esc(r.message)}</div></td>
            <td>${sentimentBadge(r.sentiment)}</td>
            <td>${statusBadge(r.status)}</td>
            <td><span class="small text-muted">${r.commented_at || '—'}</span></td>
            <td></td>
        </tr>
    `).join('');
}

// ── Bulk selection ──────────────────────────────────────────────
function updateBulkUI() {
    const dropdown = $('#sc-bulk-dropdown');
    const countEl = $('#sc-selected-count');
    if (dropdown) dropdown.style.display = state.selected.size > 0 ? '' : 'none';
    if (countEl) countEl.textContent = state.selected.size;
}

async function bulkAction(status) {
    if (state.selected.size === 0) return;

    try {
        await axios.post(cfg.bulkStatusUrl, {
            ids: Array.from(state.selected),
            status: status,
        });
        if (window.AdminHelpers) window.AdminHelpers.showToast(`${state.selected.size} comment(s) updated`, 'success');
        loadComments();
    } catch (e) {
        if (window.AdminHelpers) window.AdminHelpers.showToast('Bulk action failed', 'error');
    }
}

function currentFilterParams() {
    return new URLSearchParams({
        status:    $('#sc-filter-status')?.value || 'all',
        platform:  $('#sc-filter-platform')?.value || 'all',
        sentiment: $('#sc-filter-sentiment')?.value || 'all',
        search:    $('#sc-search')?.value || '',
        date_from: $('#sc-date-from')?.value || '',
        date_to:   $('#sc-date-to')?.value || '',
    });
}

async function bulkDelete() {
    if (state.selected.size === 0) return;
    if (!confirm(`Delete ${state.selected.size} comment(s)? This only deletes from admin DB.`)) return;

    try {
        await axios.post(cfg.bulkDeleteUrl, { ids: Array.from(state.selected) });
        if (window.AdminHelpers) window.AdminHelpers.showToast('Deleted', 'success');
        loadComments();
    } catch (e) {
        if (window.AdminHelpers) window.AdminHelpers.showToast('Bulk delete failed', 'error');
    }
}

async function bulkBlockUsers() {
    if (state.selected.size === 0) return;
    const reason = prompt('Block reason (optional):', '') || '';
    if (!confirm(`Block users for ${state.selected.size} selected comment(s)?`)) return;

    try {
        const res = await axios.post(cfg.bulkBlockUrl, { ids: Array.from(state.selected), reason });
        if (window.AdminHelpers) window.AdminHelpers.showToast(`${res.data.count || 0} user(s) blocked`, 'success');
        loadComments();
    } catch (e) {
        if (window.AdminHelpers) window.AdminHelpers.showToast('Bulk block failed', 'error');
    }
}

async function blockUserFromComment(id) {
    const reason = prompt('Block reason (optional):', '') || '';
    if (!confirm('Block this user?')) return;

    try {
        const res = await axios.post(cfg.blockUserUrl.replace('{id}', id), { reason });
        if (res.data?.success) {
            const msg = res.data.platform_blocked === false && res.data.platform_error
                ? `Blocked locally. Platform block failed: ${res.data.platform_error}`
                : 'User blocked';
            if (window.AdminHelpers) window.AdminHelpers.showToast(msg, 'success');
        } else {
            if (window.AdminHelpers) window.AdminHelpers.showToast(res.data?.error || 'Block failed', 'error');
        }
        loadComments();
    } catch (e) {
        if (window.AdminHelpers) window.AdminHelpers.showToast('Block failed', 'error');
    }
}

function exportFile(format) {
    const params = currentFilterParams();
    params.set('format', format);
    const url = cfg.exportUrl + '?' + params.toString();
    window.location.href = url;
}

// ── Single actions ──────────────────────────────────────────────
async function markStatus(id, status) {
    try {
        await axios.patch(cfg.statusUrl.replace('{id}', id), { status });
        loadComments();
    } catch (_) {}
}

async function hideComment(id) {
    try {
        await axios.post(cfg.hideUrl.replace('{id}', id));
        if (window.AdminHelpers) window.AdminHelpers.showToast('კომენტარი დაიმალა', 'success');
        loadComments();
    } catch (e) {
        if (window.AdminHelpers) window.AdminHelpers.showToast('დამალვა ვერ მოხერხდა', 'error');
    }
}

async function showReplies(commentId, afterRow) {
    const existingReplies = afterRow.parentNode.querySelectorAll(`.sc-reply-row[data-parent="${commentId}"]`);
    if (existingReplies.length > 0) {
        existingReplies.forEach(r => r.remove());
        return;
    }

    const placeholder = document.createElement('tr');
    placeholder.className = 'sc-reply-row';
    placeholder.dataset.parent = commentId;
    placeholder.innerHTML = '<td colspan="7" class="ps-5 py-1"><div class="spinner-border spinner-border-sm"></div></td>';
    afterRow.insertAdjacentElement('afterend', placeholder);

    try {
        const url = cfg.repliesUrl ? cfg.repliesUrl.replace('{id}', commentId) : null;
        if (!url) { placeholder.remove(); return; }
        const res = await axios.get(url);
        placeholder.remove();

        const rows = replyRows(res.data.data || []);
        const tempDiv = document.createElement('tbody');
        tempDiv.innerHTML = rows;
        let ref = afterRow;
        Array.from(tempDiv.children).forEach(tr => {
            tr.dataset.parent = commentId;
            ref.insertAdjacentElement('afterend', tr);
            ref = tr;
        });
        refreshFeather();
    } catch (_) {
        placeholder.remove();
    }
}

// ── AI Reply modal ──────────────────────────────────────────────
async function openReplyModal(id) {
    state.replyCommentId = id;
    const modalEl = $('#sc-reply-modal');
    if (!modalEl) return;

    const commentEl = $('#sc-modal-comment');
    const replyEl = $('#sc-modal-reply');
    const sendBtn = $('#sc-modal-send');

    if (commentEl) commentEl.textContent = 'Loading...';
    if (replyEl) replyEl.value = '';
    if (sendBtn) sendBtn.disabled = true;

    // Show modal
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    // Find comment text from table
    const row = document.querySelector(`tr[data-id="${id}"]`);
    if (row && commentEl) {
        const msgCell = row.querySelectorAll('td')[2];
        commentEl.textContent = msgCell?.textContent?.trim() || '—';
    }

    // Generate AI reply
    try {
        const res = await axios.post(cfg.generateUrl.replace('{id}', id));
        if (replyEl) replyEl.value = res.data.reply || '';
        if (sendBtn) sendBtn.disabled = false;
    } catch (e) {
        if (replyEl) replyEl.value = '';
        if (commentEl) commentEl.textContent += '\n\n⚠ AI generation failed.';
        if (sendBtn) sendBtn.disabled = false;
    }

    refreshFeather();
}

let quickRepliesCache = [];

async function loadQuickReplies(platform = 'all') {
    try {
        const url = cfg.quickRepliesListUrl + '?platform=' + encodeURIComponent(platform);
        const res = await axios.get(url);
        quickRepliesCache = res.data.data || [];
    } catch (_) {
        quickRepliesCache = [];
    }

    const select = $('#sc-quick-reply-select');
    if (!select) return;

    select.innerHTML = '<option value="">— Select template —</option>' + quickRepliesCache.map(q => {
        return `<option value="${q.id}">${esc(q.title)}</option>`;
    }).join('');
}

function insertQuickReply() {
    const select = $('#sc-quick-reply-select');
    const replyEl = $('#sc-modal-reply');
    const id = parseInt(select?.value || '0', 10);
    if (!id || !replyEl) return;
    const tpl = quickRepliesCache.find(x => x.id === id);
    if (!tpl) return;
    const current = replyEl.value || '';
    replyEl.value = current ? (current + '\n' + tpl.body) : tpl.body;
}

async function openQuickRepliesModal() {
    const modalEl = $('#sc-quick-replies-modal');
    if (!modalEl) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
    await refreshQuickRepliesTable();
}

async function refreshQuickRepliesTable() {
    const platform = $('#sc-qr-platform')?.value || '';
    const tbody = $('#sc-qr-tbody');
    if (tbody) tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">Loading...</td></tr>';

    try {
        const url = cfg.quickRepliesListUrl + '?platform=' + encodeURIComponent(platform || 'all');
        const res = await axios.get(url);
        const rows = res.data.data || [];
        if (!tbody) return;
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">No templates</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(r => {
            return `<tr data-id="${r.id}" data-title="${escAttr(r.title)}" data-body="${escAttr(r.body)}" data-platform="${r.platform || ''}">
                <td class="small fw-bold">${esc(r.title)}</td>
                <td class="small text-muted">${esc(r.platform || 'all')}</td>
                <td>
                    <button class="btn btn-outline-secondary btn-sm p-1 sc-qr-edit" data-id="${r.id}">Edit</button>
                    <button class="btn btn-outline-danger btn-sm p-1 sc-qr-del" data-id="${r.id}">Delete</button>
                </td>
            </tr>`;
        }).join('');
    } catch (e) {
        if (tbody) tbody.innerHTML = '<tr><td colspan="3" class="text-center text-danger py-3">Failed to load</td></tr>';
    }
}

function openQuickReplyEditor(data = null) {
    const editor = $('#sc-qr-editor');
    if (!editor) return;
    editor.classList.remove('d-none');
    $('#sc-qr-id').value = data?.id || '';
    $('#sc-qr-edit-platform').value = data?.platform || '';
    $('#sc-qr-title').value = data?.title || '';
    $('#sc-qr-body').value = data?.body || '';
}

function closeQuickReplyEditor() {
    $('#sc-qr-editor')?.classList.add('d-none');
    $('#sc-qr-id').value = '';
    $('#sc-qr-title').value = '';
    $('#sc-qr-body').value = '';
    $('#sc-qr-edit-platform').value = '';
}

async function saveQuickReply() {
    const id = ($('#sc-qr-id')?.value || '').trim();
    const payload = {
        platform: ($('#sc-qr-edit-platform')?.value || '') || null,
        title: ($('#sc-qr-title')?.value || '').trim(),
        body: ($('#sc-qr-body')?.value || '').trim(),
    };
    if (!payload.title || !payload.body) return;

    try {
        if (id) {
            await axios.put(cfg.quickRepliesUpdateUrl.replace('{id}', id), payload);
        } else {
            await axios.post(cfg.quickRepliesStoreUrl, payload);
        }
        closeQuickReplyEditor();
        await refreshQuickRepliesTable();
        await loadQuickReplies($('#sc-filter-platform')?.value || 'all');
        if (window.AdminHelpers) window.AdminHelpers.showToast('Saved', 'success');
    } catch (e) {
        if (window.AdminHelpers) window.AdminHelpers.showToast('Save failed', 'error');
    }
}

async function deleteQuickReply(id) {
    if (!confirm('Delete this template?')) return;
    try {
        await axios.delete(cfg.quickRepliesDeleteUrl.replace('{id}', id));
        await refreshQuickRepliesTable();
        await loadQuickReplies($('#sc-filter-platform')?.value || 'all');
        if (window.AdminHelpers) window.AdminHelpers.showToast('Deleted', 'success');
    } catch (e) {
        if (window.AdminHelpers) window.AdminHelpers.showToast('Delete failed', 'error');
    }
}

async function openAutoReplyModalFromRow(commentId) {
    const row = document.querySelector(`tr[data-id="${commentId}"]`);
    const postId = row?.getAttribute('data-post-id');
    if (!postId) return;

    $('#sc-ar-post-id').value = postId;
    $('#sc-ar-post-preview').textContent = row?.getAttribute('data-post-preview') || '—';
    $('#sc-ar-editor')?.classList.add('d-none');

    const modalEl = $('#sc-auto-reply-modal');
    if (!modalEl) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
    await refreshAutoReplyRules();
}

async function refreshAutoReplyRules() {
    const postId = ($('#sc-ar-post-id')?.value || '').trim();
    const tbody = $('#sc-ar-tbody');
    if (tbody) tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Loading...</td></tr>';

    try {
        const res = await axios.get(cfg.autoReplyRulesListUrl.replace('{facebookPostId}', postId));
        const rows = res.data.data || [];
        if (!tbody) return;
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No rules</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(r => {
            return `<tr data-id="${r.id}">
                <td class="small"><span class="badge bg-light text-dark">${esc(r.match_type)}</span> ${esc(r.match_value).slice(0, 60)}</td>
                <td class="small">${r.use_ai ? 'Yes' : 'No'}</td>
                <td class="small">${r.enabled ? 'Yes' : 'No'}</td>
                <td class="small">${r.max_replies_per_author_per_day}</td>
                <td>
                    <button class="btn btn-outline-secondary btn-sm p-1 sc-ar-edit" data-id="${r.id}">Edit</button>
                    <button class="btn btn-outline-danger btn-sm p-1 sc-ar-del" data-id="${r.id}">Delete</button>
                </td>
            </tr>`;
        }).join('');
    } catch (e) {
        if (tbody) tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-3">Failed to load</td></tr>';
    }
}

function openAutoReplyEditor(data = null) {
    const editor = $('#sc-ar-editor');
    if (!editor) return;
    editor.classList.remove('d-none');
    $('#sc-ar-id').value = data?.id || '';
    $('#sc-ar-match-type').value = data?.match_type || 'contains';
    $('#sc-ar-match-value').value = data?.match_value || '';
    $('#sc-ar-use-ai').value = data?.use_ai ? '1' : '0';
    $('#sc-ar-enabled').value = data?.enabled ? '1' : '0';
    $('#sc-ar-max').value = data?.max_replies_per_author_per_day || 3;
    $('#sc-ar-template').value = data?.reply_template || '';
}

function closeAutoReplyEditor() {
    $('#sc-ar-editor')?.classList.add('d-none');
    $('#sc-ar-id').value = '';
    $('#sc-ar-match-value').value = '';
    $('#sc-ar-template').value = '';
}

async function saveAutoReplyRule() {
    const postId = ($('#sc-ar-post-id')?.value || '').trim();
    const id = ($('#sc-ar-id')?.value || '').trim();
    const payload = {
        facebook_post_id: parseInt(postId, 10),
        match_type: ($('#sc-ar-match-type')?.value || 'contains'),
        match_value: ($('#sc-ar-match-value')?.value || '').trim(),
        reply_template: ($('#sc-ar-template')?.value || '').trim(),
        use_ai: ($('#sc-ar-use-ai')?.value || '0') === '1',
        enabled: ($('#sc-ar-enabled')?.value || '1') === '1',
        max_replies_per_author_per_day: parseInt($('#sc-ar-max')?.value || '3', 10),
    };
    if (!payload.match_value || !payload.reply_template) return;

    try {
        if (id) {
            await axios.put(cfg.autoReplyRulesUpdateUrl.replace('{id}', id), payload);
        } else {
            await axios.post(cfg.autoReplyRulesStoreUrl, payload);
        }
        closeAutoReplyEditor();
        await refreshAutoReplyRules();
        if (window.AdminHelpers) window.AdminHelpers.showToast('Rule saved', 'success');
    } catch (e) {
        if (window.AdminHelpers) window.AdminHelpers.showToast('Save failed', 'error');
    }
}

async function deleteAutoReplyRule(id) {
    if (!confirm('Delete this rule?')) return;
    try {
        await axios.delete(cfg.autoReplyRulesDeleteUrl.replace('{id}', id));
        await refreshAutoReplyRules();
        if (window.AdminHelpers) window.AdminHelpers.showToast('Deleted', 'success');
    } catch (e) {
        if (window.AdminHelpers) window.AdminHelpers.showToast('Delete failed', 'error');
    }
}


async function sendReply() {
    if (!state.replyCommentId) return;
    const replyEl = $('#sc-modal-reply');
    const reply = replyEl?.value?.trim();
    if (!reply) return;

    const sendBtn = $('#sc-modal-send');
    if (sendBtn) sendBtn.disabled = true;

    try {
        await axios.post(cfg.replyUrl.replace('{id}', state.replyCommentId), { reply });
        const modal = bootstrap.Modal.getInstance($('#sc-reply-modal'));
        if (modal) modal.hide();
        if (window.AdminHelpers) window.AdminHelpers.showToast('Reply sent!', 'success');
        loadComments();
    } catch (e) {
        const msg = e.response?.data?.error || 'Reply failed';
        if (window.AdminHelpers) window.AdminHelpers.showToast(msg, 'error');
        if (sendBtn) sendBtn.disabled = false;
    }
}

async function regenerateReply() {
    if (!state.replyCommentId) return;
    const replyEl = $('#sc-modal-reply');
    if (replyEl) replyEl.value = 'Regenerating...';

    try {
        const res = await axios.post(cfg.generateUrl.replace('{id}', state.replyCommentId));
        if (replyEl) replyEl.value = res.data.reply || '';
    } catch (_) {
        if (replyEl) replyEl.value = '⚠ Generation failed.';
    }
}

// ── Fetch from Meta ─────────────────────────────────────────────
async function fetchFromMeta() {
    const btn = $('#sc-fetch-btn');
    if (btn) { btn.disabled = true; btn.textContent = 'Fetching...'; }

    try {
        const res = await axios.post(cfg.fetchUrl);
        if (window.AdminHelpers) window.AdminHelpers.showToast(`${res.data.imported} comment(s) imported`, 'success');
        loadComments();
    } catch (e) {
        const msg = e.response?.data?.error || 'Fetch failed';
        if (window.AdminHelpers) window.AdminHelpers.showToast(msg, 'error');
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i data-feather="download" style="width:14px;height:14px;"></i> Fetch from Meta'; }
        if (window.feather) window.feather.replace();
    }
}

// ── Init / Destroy ──────────────────────────────────────────────
export function initSocialComments() {
    const configEl = document.getElementById('sc-config');
    if (!configEl) return;

    cfg = JSON.parse(configEl.textContent);
    state = { page: 1, selected: new Set(), replyCommentId: null };

    loadComments();
    loadQuickReplies($('#sc-filter-platform')?.value || 'all');

    // Filters
    let searchTimeout;
    $('#sc-search')?.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => { state.page = 1; loadComments(); }, 300);
    });
    $('#sc-filter-status')?.addEventListener('change', () => { state.page = 1; loadComments(); });
    $('#sc-filter-platform')?.addEventListener('change', () => { state.page = 1; loadComments(); loadQuickReplies($('#sc-filter-platform')?.value || 'all'); });
    $('#sc-filter-sentiment')?.addEventListener('change', () => { state.page = 1; loadComments(); });
    $('#sc-date-from')?.addEventListener('change', () => { state.page = 1; loadComments(); });
    $('#sc-date-to')?.addEventListener('change', () => { state.page = 1; loadComments(); });

    // Pagination
    $('#sc-prev')?.addEventListener('click', () => { if (state.page > 1) { state.page--; loadComments(); } });
    $('#sc-next')?.addEventListener('click', () => { state.page++; loadComments(); });

    // Select all
    $('#sc-select-all')?.addEventListener('change', (e) => {
        const checked = e.target.checked;
        $$('.sc-row-check').forEach(cb => {
            cb.checked = checked;
            const id = parseInt(cb.dataset.id, 10);
            if (checked) state.selected.add(id); else state.selected.delete(id);
        });
        updateBulkUI();
    });

    // Row checkbox delegation
    $('#sc-tbody')?.addEventListener('change', (e) => {
        if (e.target.classList.contains('sc-row-check')) {
            const id = parseInt(e.target.dataset.id, 10);
            e.target.checked ? state.selected.add(id) : state.selected.delete(id);
            updateBulkUI();
        }
    });

    // Row action delegation
    $('#sc-tbody')?.addEventListener('click', (e) => {
        const aiBtn = e.target.closest('.sc-ai-reply-btn');
        if (aiBtn) { openReplyModal(parseInt(aiBtn.dataset.id, 10)); return; }

        const readBtn = e.target.closest('.sc-mark-read-btn');
        if (readBtn) { markStatus(parseInt(readBtn.dataset.id, 10), 'read'); return; }

        const hideBtn = e.target.closest('.sc-hide-btn');
        if (hideBtn) { hideComment(parseInt(hideBtn.dataset.id, 10)); return; }

        const spamBtn = e.target.closest('.sc-mark-spam-btn');
        if (spamBtn) { markStatus(parseInt(spamBtn.dataset.id, 10), 'spam'); return; }

        const repliesBtn = e.target.closest('.sc-show-replies-btn');
        if (repliesBtn) {
            const row = repliesBtn.closest('tr');
            if (row) showReplies(parseInt(repliesBtn.dataset.id, 10), row);
            return;
        }

        const blockBtn = e.target.closest('.sc-block-user-btn');
        if (blockBtn) { blockUserFromComment(parseInt(blockBtn.dataset.id, 10)); return; }

        const rulesBtn = e.target.closest('.sc-auto-reply-rules-btn');
        if (rulesBtn) { openAutoReplyModalFromRow(parseInt(rulesBtn.dataset.id, 10)); return; }
    });

    // Bulk actions
    $$('.sc-bulk-action').forEach(el => el.addEventListener('click', (e) => {
        e.preventDefault();
        bulkAction(el.dataset.status);
    }));

    // Fetch from Meta
    $('#sc-fetch-btn')?.addEventListener('click', fetchFromMeta);

    // Modal: Send & Regenerate
    $('#sc-modal-send')?.addEventListener('click', sendReply);
    $('#sc-modal-regenerate')?.addEventListener('click', regenerateReply);
    $('#sc-insert-quick-reply')?.addEventListener('click', (e) => { e.preventDefault(); insertQuickReply(); });

    $('#sc-export-csv')?.addEventListener('click', (e) => { e.preventDefault(); exportFile('csv'); });
    $('#sc-export-xlsx')?.addEventListener('click', (e) => { e.preventDefault(); exportFile('xlsx'); });
    $('#sc-bulk-delete')?.addEventListener('click', (e) => { e.preventDefault(); bulkDelete(); });
    $('#sc-bulk-block')?.addEventListener('click', (e) => { e.preventDefault(); bulkBlockUsers(); });

    $('#sc-quick-replies-btn')?.addEventListener('click', (e) => { e.preventDefault(); openQuickRepliesModal(); });
    $('#sc-qr-platform')?.addEventListener('change', refreshQuickRepliesTable);
    $('#sc-qr-new')?.addEventListener('click', (e) => { e.preventDefault(); openQuickReplyEditor(); });
    $('#sc-qr-cancel')?.addEventListener('click', (e) => { e.preventDefault(); closeQuickReplyEditor(); });
    $('#sc-qr-save')?.addEventListener('click', (e) => { e.preventDefault(); saveQuickReply(); });
    $('#sc-qr-tbody')?.addEventListener('click', (e) => {
        const editBtn = e.target.closest('.sc-qr-edit');
        if (editBtn) {
            const tr = e.target.closest('tr');
            openQuickReplyEditor({
                id: tr?.getAttribute('data-id') || '',
                title: tr?.getAttribute('data-title') || '',
                body: tr?.getAttribute('data-body') || '',
                platform: tr?.getAttribute('data-platform') || '',
            });
            return;
        }
        const delBtn = e.target.closest('.sc-qr-del');
        if (delBtn) {
            deleteQuickReply(parseInt(delBtn.dataset.id, 10));
        }
    });

    $('#sc-ar-new')?.addEventListener('click', (e) => { e.preventDefault(); openAutoReplyEditor(); });
    $('#sc-ar-cancel')?.addEventListener('click', (e) => { e.preventDefault(); closeAutoReplyEditor(); });
    $('#sc-ar-save')?.addEventListener('click', (e) => { e.preventDefault(); saveAutoReplyRule(); });
    $('#sc-ar-tbody')?.addEventListener('click', async (e) => {
        const editBtn = e.target.closest('.sc-ar-edit');
        if (editBtn) {
            const id = parseInt(editBtn.dataset.id, 10);
            const postId = ($('#sc-ar-post-id')?.value || '').trim();
            const res = await axios.get(cfg.autoReplyRulesListUrl.replace('{facebookPostId}', postId));
            const rule = (res.data.data || []).find(x => x.id === id);
            if (rule) openAutoReplyEditor(rule);
            return;
        }
        const delBtn = e.target.closest('.sc-ar-del');
        if (delBtn) {
            deleteAutoReplyRule(parseInt(delBtn.dataset.id, 10));
        }
    });

    if (!isLocalhostRuntime() && window.Echo && typeof window.Echo.private === 'function') {
        try {
            window.Echo.private(realtimeChannel)
                .stopListening('.social.comment.created')
                .listen('.social.comment.created', (e) => {
                    const c = e?.comment;
                    const author = c?.author_name || 'Unknown';
                    const platform = c?.platform || '';
                    if (window.AdminHelpers) window.AdminHelpers.showToast(`New ${platform} comment from ${author}`, 'info');

                    if (state.selected.size === 0 && !document.getElementById('sc-reply-modal')?.classList.contains('show')) {
                        clearTimeout(state._rtReloadTimer);
                        state._rtReloadTimer = setTimeout(() => loadComments(), 600);
                    }
                });
        } catch (_) {}
    }

    if (window.feather) window.feather.replace();
}

export function destroySocialComments() {
    try {
        if (state?._rtReloadTimer) clearTimeout(state._rtReloadTimer);
    } catch (_) {}

    if (window.Echo && typeof window.Echo.leave === 'function') {
        try { window.Echo.leave(realtimeChannel); } catch (_) {}
    }
}
