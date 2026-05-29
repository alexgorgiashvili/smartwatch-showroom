{{-- Social Comments — reusable content partial (used standalone and embedded in Social Dashboard) --}}
<div id="sc-root">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0">სოციალური კომენტარები</h5>
            <p class="text-muted small mb-0">Facebook &amp; Instagram კომენტარების მართვა</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                    <i data-feather="download" style="width:14px;height:14px;"></i> ექსპორტი
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item small" href="#" id="sc-export-csv">CSV ექსპორტი</a></li>
                    <li><a class="dropdown-item small" href="#" id="sc-export-xlsx">Excel ექსპორტი</a></li>
                </ul>
            </div>
            <button class="btn btn-outline-secondary btn-sm" id="sc-quick-replies-btn">
                <i data-feather="message-square" style="width:14px;height:14px;"></i> სწრაფი პასუხები
            </button>
            <button class="btn btn-outline-primary btn-sm" id="sc-fetch-btn">
                <i data-feather="download-cloud" style="width:14px;height:14px;"></i> Meta-დან ჩამოტვირთვა
            </button>
            <div class="dropdown" id="sc-bulk-dropdown" style="display:none;">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                    მასობრივი მოქმედება (<span id="sc-selected-count">0</span>)
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item small sc-bulk-action" href="#" data-status="read">წაკითხულად მონიშვნა</a></li>
                    <li><a class="dropdown-item small sc-bulk-action" href="#" data-status="spam">სპამად მონიშვნა</a></li>
                    <li><a class="dropdown-item small sc-bulk-action" href="#" data-status="hidden">დამალვა</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item small" href="#" id="sc-bulk-delete">წაშლა</a></li>
                    <li><a class="dropdown-item small" href="#" id="sc-bulk-block">მომხმარებლის დაბლოკვა</a></li>
                </ul>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-3 border-0 shadow-sm">
        <div class="card-body py-2">
            <div class="row g-2 align-items-center">
                <div class="col-md-3">
                    <input type="text" class="form-control form-control-sm" id="sc-search"
                           placeholder="ავტორი ან ტექსტი..." autocomplete="off">
                </div>
                <div class="col-auto">
                    <input type="date" class="form-control form-control-sm" id="sc-date-from">
                </div>
                <div class="col-auto">
                    <input type="date" class="form-control form-control-sm" id="sc-date-to">
                </div>
                <div class="col-auto">
                    <select class="form-select form-select-sm" id="sc-filter-status">
                        <option value="all">ყველა სტატუსი</option>
                        <option value="unread" selected>წაუკითხავი</option>
                        <option value="read">წაკითხული</option>
                        <option value="replied">გაპასუხებული</option>
                        <option value="spam">სპამი</option>
                        <option value="hidden">დამალული</option>
                    </select>
                </div>
                <div class="col-auto">
                    <select class="form-select form-select-sm" id="sc-filter-platform">
                        <option value="all">ყველა პლატფ.</option>
                        <option value="facebook">Facebook</option>
                        <option value="instagram">Instagram</option>
                    </select>
                </div>
                <div class="col-auto">
                    <select class="form-select form-select-sm" id="sc-filter-sentiment">
                        <option value="all">ყველა ტონი</option>
                        <option value="positive">პოზიტიური</option>
                        <option value="negative">ნეგატიური</option>
                        <option value="neutral">ნეიტრალური</option>
                        <option value="question">კითხვა</option>
                    </select>
                </div>
                <div class="col-auto ms-auto">
                    <span class="badge bg-warning" id="sc-unread-badge">0 წაუკითხავი</span>
                    <span class="badge bg-secondary" id="sc-total-badge">0 სულ</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0" id="sc-table">
                <thead class="table-light">
                    <tr>
                        <th style="width:30px;"><input type="checkbox" id="sc-select-all"></th>
                        <th>ავტორი</th>
                        <th>კომენტარი</th>
                        <th style="width:90px;">ტონი</th>
                        <th style="width:80px;">სტატუსი</th>
                        <th style="width:100px;">თარიღი</th>
                        <th style="width:320px; min-width:320px;">მოქმედება</th>
                    </tr>
                </thead>
                <tbody id="sc-tbody">
                    <tr><td colspan="7" class="text-center py-4 text-muted">იტვირთება...</td></tr>
                </tbody>
            </table>
        </div>
        {{-- Pagination --}}
        <div class="card-footer d-flex justify-content-between align-items-center py-2 d-none" id="sc-pagination">
            <span class="text-muted small" id="sc-page-info"></span>
            <div class="d-flex gap-1">
                <button class="btn btn-outline-secondary btn-sm" id="sc-prev" disabled>&laquo;</button>
                <button class="btn btn-outline-secondary btn-sm" id="sc-next" disabled>&raquo;</button>
            </div>
        </div>
    </div>
</div>

{{-- AI Reply Modal --}}
<div class="modal fade" id="sc-reply-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">AI პასუხი</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold small">ორიგინალი კომენტარი</label>
                    <div class="border rounded p-2 bg-light small" id="sc-modal-comment">—</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">შემოთავაზებული პასუხი</label>
                    <textarea class="form-control" id="sc-modal-reply" rows="4"></textarea>
                </div>
                <div class="row g-2 align-items-center mb-2">
                    <div class="col-md-8">
                        <label class="form-label fw-bold small">სწრაფი პასუხი</label>
                        <select class="form-select form-select-sm" id="sc-quick-reply-select">
                            <option value="">— შაბლონი —</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end gap-2">
                        <button class="btn btn-outline-secondary btn-sm w-100" id="sc-insert-quick-reply">ჩასმა</button>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary btn-sm" id="sc-modal-regenerate">
                        <i data-feather="refresh-cw" style="width:12px;height:12px;"></i> ხელახლა გენერაცია
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">გაუქმება</button>
                <button class="btn btn-primary btn-sm" id="sc-modal-send">პასუხის გაგზავნა</button>
            </div>
        </div>
    </div>
</div>

{{-- Quick Replies Modal --}}
<div class="modal fade" id="sc-quick-replies-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">სწრაფი პასუხები</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <select class="form-select form-select-sm" id="sc-qr-platform">
                            <option value="">ყველა პლატფ.</option>
                            <option value="facebook">Facebook</option>
                            <option value="instagram">Instagram</option>
                        </select>
                    </div>
                    <div class="col-md-8 text-end">
                        <button class="btn btn-primary btn-sm" id="sc-qr-new">ახალი შაბლონი</button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>სათაური</th>
                                <th>პლატფ.</th>
                                <th style="width:120px;">მოქმედება</th>
                            </tr>
                        </thead>
                        <tbody id="sc-qr-tbody">
                            <tr><td colspan="3" class="text-center text-muted py-3">იტვირთება...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="border rounded p-3 mt-3 d-none" id="sc-qr-editor">
                    <input type="hidden" id="sc-qr-id" value="">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">პლატფ.</label>
                            <select class="form-select form-select-sm" id="sc-qr-edit-platform">
                                <option value="">ყველა</option>
                                <option value="facebook">Facebook</option>
                                <option value="instagram">Instagram</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">სათაური</label>
                            <input type="text" class="form-control form-control-sm" id="sc-qr-title">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">ტექსტი</label>
                            <textarea class="form-control" id="sc-qr-body" rows="4"></textarea>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-2">
                        <button class="btn btn-outline-secondary btn-sm" id="sc-qr-cancel">გაუქმება</button>
                        <button class="btn btn-primary btn-sm" id="sc-qr-save">შენახვა</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Auto-Reply Rules Modal --}}
<div class="modal fade" id="sc-auto-reply-modal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">ავტო-პასუხის წესები</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="sc-ar-post-id" value="">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="text-muted small" id="sc-ar-post-preview">—</div>
                    <button class="btn btn-primary btn-sm" id="sc-ar-new">ახალი წესი</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>შესაბამისობა</th>
                                <th>AI</th>
                                <th>აქტიური</th>
                                <th>ლიმ./დღე</th>
                                <th style="width:140px;">მოქმედება</th>
                            </tr>
                        </thead>
                        <tbody id="sc-ar-tbody">
                            <tr><td colspan="5" class="text-center text-muted py-3">იტვირთება...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="border rounded p-3 mt-3 d-none" id="sc-ar-editor">
                    <input type="hidden" id="sc-ar-id" value="">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">ტიპი</label>
                            <select class="form-select form-select-sm" id="sc-ar-match-type">
                                <option value="contains">შეიცავს</option>
                                <option value="keywords">საკვ. სიტყვები</option>
                                <option value="regex">Regex</option>
                            </select>
                        </div>
                        <div class="col-md-9">
                            <label class="form-label small fw-bold">მნიშვნელობა</label>
                            <input type="text" class="form-control form-control-sm" id="sc-ar-match-value">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">AI</label>
                            <select class="form-select form-select-sm" id="sc-ar-use-ai">
                                <option value="0">არა</option>
                                <option value="1">კი</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">აქტიური</label>
                            <select class="form-select form-select-sm" id="sc-ar-enabled">
                                <option value="1">კი</option>
                                <option value="0">არა</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">მაქს./დღე</label>
                            <input type="number" class="form-control form-control-sm" id="sc-ar-max" min="1" max="50" value="3">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">პასუხის შაბლონი / ინსტრუქცია</label>
                            <textarea class="form-control" id="sc-ar-template" rows="4"></textarea>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-2">
                        <button class="btn btn-outline-secondary btn-sm" id="sc-ar-cancel">გაუქმება</button>
                        <button class="btn btn-primary btn-sm" id="sc-ar-save">შენახვა</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@php
    $socialCommentsConfig = $socialCommentsConfig ?? [
        'listUrl'                  => route('admin.social-comments.list'),
        'statusUrl'                => url('/admin/social-comments/{id}/status'),
        'bulkStatusUrl'            => route('admin.social-comments.bulk-status'),
        'bulkDeleteUrl'            => route('admin.social-comments.bulk-delete'),
        'generateUrl'              => url('/admin/social-comments/{id}/generate-reply'),
        'replyUrl'                 => url('/admin/social-comments/{id}/reply'),
        'hideUrl'                  => url('/admin/social-comments/{id}/hide'),
        'fetchUrl'                 => route('admin.social-comments.fetch'),
        'exportUrl'                => route('admin.social-comments.export'),
        'blockUserUrl'             => url('/admin/social-comments/{id}/block-user'),
        'bulkBlockUrl'             => route('admin.social-comments.bulk-block-users'),
        'repliesUrl'               => url('/admin/social-comments/{id}/replies'),
        'quickRepliesListUrl'      => route('admin.social-comments.quick-replies.list'),
        'quickRepliesStoreUrl'     => route('admin.social-comments.quick-replies.store'),
        'quickRepliesUpdateUrl'    => url('/admin/social-comments/quick-replies/{id}'),
        'quickRepliesDeleteUrl'    => url('/admin/social-comments/quick-replies/{id}'),
        'autoReplyRulesListUrl'    => url('/admin/social-comments/auto-reply-rules/{facebookPostId}'),
        'autoReplyRulesStoreUrl'   => route('admin.social-comments.auto-reply-rules.store'),
        'autoReplyRulesUpdateUrl'  => url('/admin/social-comments/auto-reply-rules/{id}'),
        'autoReplyRulesDeleteUrl'  => url('/admin/social-comments/auto-reply-rules/{id}'),
    ];
@endphp
<script id="sc-config" type="application/json">{!! json_encode($socialCommentsConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
