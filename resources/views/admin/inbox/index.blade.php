@extends('admin.layout')

@section('title', 'Inbox — Admin')

@section('content')
@fragment('content')
<div data-page-title="Inbox" id="inbox-app">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-0">
                    <div class="chat-wrapper d-flex position-relative">
                        <div class="chat-aside border-end" style="width:360px;min-width:280px;">
                            <div class="aside-header">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <h6 class="mb-0">Inbox</h6>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge rounded-pill bg-danger d-none" id="inbox-unread-pill">0</span>
                                        <button type="button" class="btn btn-icon btn-light" id="inbox-refresh" aria-label="Refresh">
                                            <i data-feather="refresh-cw" class="icon-sm"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="search-form mb-2">
                                    <div class="input-group">
                                        <span class="input-group-text"><i data-feather="search" class="icon-sm"></i></span>
                                        <input type="text" class="form-control" id="inbox-search" placeholder="Search conversations..." autocomplete="off" aria-label="Search conversations">
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <select class="form-select form-select-sm" id="inbox-platform-filter" aria-label="Platform filter">
                                        <option value="all">All</option>
                                        <option value="facebook">Facebook</option>
                                        <option value="instagram">Instagram</option>
                                        <option value="whatsapp">WhatsApp</option>
                                        <option value="messenger">Messenger</option>
                                        <option value="home">Web</option>
                                    </select>
                                    <select class="form-select form-select-sm" id="inbox-status-filter" aria-label="Status filter">
                                        <option value="all">All Status</option>
                                        <option value="active" selected>Active</option>
                                        <option value="archived">Archived</option>
                                        <option value="closed">Closed</option>
                                    </select>
                                </div>
                            </div>

                            <div class="aside-body">
                                <div class="tab-content">
                                    <div class="tab-pane fade show active" id="chats" role="tabpanel">
                                        <div id="inbox-conversation-list">
                                            <div class="text-center py-4 text-muted" id="inbox-list-loading">
                                                <div class="spinner-border spinner-border-sm" role="status" aria-label="Loading"></div>
                                                <div class="small mt-1">Loading...</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="p-2 border-top d-none" id="inbox-list-pagination">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <button class="btn btn-outline-secondary btn-sm" id="inbox-prev-page" disabled aria-label="Previous page">&laquo;</button>
                                        <span class="small text-muted" id="inbox-page-info"></span>
                                        <button class="btn btn-outline-secondary btn-sm" id="inbox-next-page" disabled aria-label="Next page">&raquo;</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="chat-content flex-grow-1">
                            <div class="chat-header border-bottom d-none" id="inbox-chat-header">
                                <div class="d-flex align-items-center justify-content-between py-2">
                                    <div class="d-flex align-items-center">
                                        <button type="button" class="btn btn-icon btn-light d-lg-none me-2" id="backToChatList" aria-label="Back to conversations">
                                            <i data-feather="chevron-left" class="icon-sm"></i>
                                        </button>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-placeholder me-2" id="inbox-chat-avatar">?</div>
                                            <div>
                                                <div class="fw-bold tx-13" id="inbox-chat-name">—</div>
                                                <div class="tx-12 text-muted" id="inbox-chat-platform">—</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-1">
                                        <button type="button" class="btn btn-icon btn-light" id="inbox-btn-toggle-ai" aria-label="Toggle AI">
                                            <i data-feather="cpu" class="icon-sm"></i>
                                        </button>
                                        <div class="dropdown">
                                            <button type="button" class="btn btn-icon btn-light dropdown-toggle" data-bs-toggle="dropdown" aria-label="Conversation actions">
                                                <i data-feather="more-vertical" class="icon-sm"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><a class="dropdown-item inbox-status-action" href="#" data-status="active">Mark Active</a></li>
                                                <li><a class="dropdown-item inbox-status-action" href="#" data-status="archived">Archive</a></li>
                                                <li><a class="dropdown-item inbox-status-action" href="#" data-status="closed">Close</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item inbox-priority-action" href="#" data-priority="low">Priority: Low</a></li>
                                                <li><a class="dropdown-item inbox-priority-action" href="#" data-priority="normal">Priority: Normal</a></li>
                                                <li><a class="dropdown-item inbox-priority-action" href="#" data-priority="high">Priority: High</a></li>
                                                <li><a class="dropdown-item inbox-priority-action" href="#" data-priority="urgent">Priority: Urgent</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="chat-body" id="inbox-messages">
                                <div class="d-flex align-items-center justify-content-center h-100 text-muted" id="inbox-empty-state">
                                    <div class="text-center">
                                        <i data-feather="message-circle" class="icon-xxl"></i>
                                        <p class="mt-2 mb-0">Select a conversation</p>
                                    </div>
                                </div>
                            </div>

                            <div class="chat-footer d-none" id="inbox-input-area">
                                <form id="inbox-send-form" class="d-flex gap-2">
                                    <textarea class="form-control" id="inbox-message-input" rows="1" placeholder="Type a message..." aria-label="Message input" style="resize:none;max-height:120px;"></textarea>
                                    <button type="submit" class="btn btn-primary px-3 align-self-end" id="inbox-send-btn" aria-label="Send">
                                        <i data-feather="send" class="icon-sm"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="d-none d-xl-flex flex-column border-start" style="width:320px;" id="inbox-details-panel">
                            <div class="p-3">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h6 class="mb-0">Details</h6>
                                </div>
                                <div class="mb-3">
                                    <div class="text-muted tx-12">Name</div>
                                    <div class="fw-bold" id="inbox-detail-name">—</div>
                                </div>
                                <div class="mb-3">
                                    <div class="text-muted tx-12">Email</div>
                                    <div id="inbox-detail-email">—</div>
                                </div>
                                <div class="mb-3">
                                    <div class="text-muted tx-12">Phone</div>
                                    <div id="inbox-detail-phone">—</div>
                                </div>
                                <hr>
                                <div class="mb-2 d-flex align-items-center justify-content-between">
                                    <span class="text-muted tx-12">Platform</span>
                                    <span id="inbox-detail-platform">—</span>
                                </div>
                                <div class="mb-2 d-flex align-items-center justify-content-between">
                                    <span class="text-muted tx-12">Status</span>
                                    <span id="inbox-detail-status">—</span>
                                </div>
                                <div class="mb-2 d-flex align-items-center justify-content-between">
                                    <span class="text-muted tx-12">Priority</span>
                                    <span id="inbox-detail-priority">—</span>
                                </div>
                                <div class="mb-2 d-flex align-items-center justify-content-between">
                                    <span class="text-muted tx-12">AI Mode</span>
                                    <span id="inbox-detail-ai">—</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@php
    $inboxConfig = [
        'conversationsUrl' => route('admin.inbox.conversations'),
        'messagesUrl' => url('/admin/inbox/{id}/messages'),
        'sendUrl' => url('/admin/inbox/{id}/send'),
        'markReadUrl' => url('/admin/inbox/{id}/read'),
        'statusUrl' => url('/admin/inbox/{id}/status'),
        'priorityUrl' => url('/admin/inbox/{id}/priority'),
        'toggleAiUrl' => url('/admin/inbox/{id}/toggle-ai'),
        'countsUrl' => route('admin.inbox.counts'),
    ];
@endphp
<script id="inbox-config" type="application/json">{!! json_encode($inboxConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

@push('scripts')
<script>
    window.vapidPublicKey = document.querySelector('meta[name="webpush-public-key"]')?.getAttribute('content') || '';
</script>
<script src="{{ asset('js/inbox-pwa.js') }}"></script>
@endpush
@endfragment
@endsection
