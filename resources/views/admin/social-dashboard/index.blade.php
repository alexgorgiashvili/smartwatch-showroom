@extends('admin.layout')

@section('title', 'სოციალური დეშბორდი — Admin')

@section('content')
@fragment('content')
@php
$sdConfig = [
    'statsUrl'           => route('admin.social-dashboard.stats', [], false),
    'postsUrl'           => route('admin.social-dashboard.posts', [], false),
    'scheduledUrl'       => route('admin.social-dashboard.scheduled', [], false),
    'postStoreUrl'       => route('admin.facebook-posts.store', [], false),
    'productImagesUrl'   => '/admin/products/{id}/images-json',
    'allImagesUrl'       => route('admin.images.all.json', [], false),
    'uploadImageUrl'     => route('admin.images.upload-standalone', [], false),
    'postUpdateUrl'      => '/admin/facebook-posts/{id}',
    'postDestroyUrl'     => '/admin/facebook-posts/{id}',
    'postPublishUrl'     => '/admin/facebook-posts/{id}/publish',
    'postEditUrl'        => '/admin/facebook-posts/{id}/edit',
    'generateUrl'        => route('admin.facebook-posts.generate', [], false),
    'enhancePromptUrl'   => route('admin.facebook-posts.enhance-prompt', [], false),
    'suggestHashtagsUrl' => route('admin.facebook-posts.suggest-hashtags', [], false),
    'fbConfigured'       => $fbConfigured,
    'igConfigured'       => $igConfigured,
    'products'           => $products->map(fn($p) => [
        'id'    => $p->id,
        'slug'  => $p->slug,
        'name'  => $p->name_ka ?: $p->name_en,
        'price' => number_format($p->sale_price ?? $p->price, 2),
    ])->values(),
];
$sdConfig['socialCommentsConfig'] = [
    'listUrl'                  => route('admin.social-comments.list', [], false),
    'statusUrl'                => '/admin/social-comments/{id}/status',
    'bulkStatusUrl'            => route('admin.social-comments.bulk-status', [], false),
    'bulkDeleteUrl'            => route('admin.social-comments.bulk-delete', [], false),
    'generateUrl'              => '/admin/social-comments/{id}/generate-reply',
    'replyUrl'                 => '/admin/social-comments/{id}/reply',
    'hideUrl'                  => '/admin/social-comments/{id}/hide',
    'fetchUrl'                 => route('admin.social-comments.fetch', [], false),
    'exportUrl'                => route('admin.social-comments.export', [], false),
    'blockUserUrl'             => '/admin/social-comments/{id}/block-user',
    'bulkBlockUrl'             => route('admin.social-comments.bulk-block-users', [], false),
    'repliesUrl'               => '/admin/social-comments/{id}/replies',
    'quickRepliesListUrl'      => route('admin.social-comments.quick-replies.list', [], false),
    'quickRepliesStoreUrl'     => route('admin.social-comments.quick-replies.store', [], false),
    'quickRepliesUpdateUrl'    => '/admin/social-comments/quick-replies/{id}',
    'quickRepliesDeleteUrl'    => '/admin/social-comments/quick-replies/{id}',
    'autoReplyRulesListUrl'    => '/admin/social-comments/auto-reply-rules/{facebookPostId}',
    'autoReplyRulesStoreUrl'   => route('admin.social-comments.auto-reply-rules.store', [], false),
    'autoReplyRulesUpdateUrl'  => '/admin/social-comments/auto-reply-rules/{id}',
    'autoReplyRulesDeleteUrl'  => '/admin/social-comments/auto-reply-rules/{id}',
];
@endphp
<div data-page-title="Social Dashboard" class="sd-page">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0">სოციალური მედია</h4>
            <p class="text-muted small mb-0">Facebook &amp; Instagram — ერთიანი მართვის პანელი</p>
        </div>
        <div class="d-flex gap-2">
            @if(!$fbConfigured && !$igConfigured)
            <span class="badge bg-warning text-dark">⚠ API არ არის კონფიგურირებული</span>
            @else
                @if($fbConfigured)<span class="badge bg-primary">Facebook ✓</span>@endif
                @if($igConfigured)<span class="badge" style="background:linear-gradient(45deg,#405de6,#e1306c);">Instagram ✓</span>@endif
            @endif
            <button class="btn btn-primary btn-sm" id="sd-new-post-btn">
                <i data-feather="plus" style="width:14px;height:14px;"></i> ახალი პოსტი
            </button>
        </div>
    </div>

    {{-- Tabs --}}
    <ul class="nav nav-tabs mb-3" id="sdTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="sd-tab-overview" data-bs-toggle="tab" data-bs-target="#sd-overview" type="button" role="tab">
                <i data-feather="bar-chart-2" style="width:14px;height:14px;"></i> მიმოხილვა
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="sd-tab-posts" data-bs-toggle="tab" data-bs-target="#sd-posts" type="button" role="tab">
                <i data-feather="edit-3" style="width:14px;height:14px;"></i> პოსტები
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="sd-tab-comments" data-bs-toggle="tab" data-bs-target="#sd-comments" type="button" role="tab">
                <i data-feather="message-square" style="width:14px;height:14px;"></i> კომენტარები
                <span class="badge bg-warning ms-1 sd-unread-badge d-none" id="sd-comments-badge">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="sd-tab-schedule" data-bs-toggle="tab" data-bs-target="#sd-schedule" type="button" role="tab">
                <i data-feather="clock" style="width:14px;height:14px;"></i> განრიგი
            </button>
        </li>
    </ul>

    <div class="tab-content" id="sdTabContent">

        {{-- ═══════════════════════════════════════
             TAB 1: OVERVIEW
        ═══════════════════════════════════════ --}}
        <div class="tab-pane fade show active" id="sd-overview" role="tabpanel">

            {{-- Stat Cards --}}
            <div class="row g-3 mb-4" id="sd-stat-cards">
                <div class="col-md-2 col-sm-4 col-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center py-3">
                            <div class="text-muted small">სულ პოსტი</div>
                            <div class="fw-bold fs-3" id="sd-stat-total-posts">—</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4 col-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center py-3">
                            <div class="text-muted small">გამოქვეყნებული</div>
                            <div class="fw-bold fs-3 text-success" id="sd-stat-published">—</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4 col-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center py-3">
                            <div class="text-muted small">დაგეგმილი</div>
                            <div class="fw-bold fs-3 text-primary" id="sd-stat-scheduled">—</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4 col-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center py-3">
                            <div class="text-muted small">კომენტარები</div>
                            <div class="fw-bold fs-3" id="sd-stat-comments">—</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4 col-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center py-3">
                            <div class="text-muted small">წაუკითხავი</div>
                            <div class="fw-bold fs-3 text-warning" id="sd-stat-unread">—</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4 col-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center py-3">
                            <div class="text-muted small">რეაქციები</div>
                            <div class="fw-bold fs-3 text-info" id="sd-stat-reactions">—</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                {{-- Recent Posts --}}
                <div class="col-lg-7">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-transparent d-flex justify-content-between align-items-center border-bottom">
                            <h6 class="mb-0">ბოლო პოსტები</h6>
                            <button class="btn btn-link btn-sm p-0 text-decoration-none" id="sd-overview-all-posts-btn">ყველა →</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>პოსტი</th>
                                        <th style="width:70px;">პლ.</th>
                                        <th style="width:80px;">სტატუსი</th>
                                        <th style="width:100px;">თარიღი</th>
                                    </tr>
                                </thead>
                                <tbody id="sd-recent-posts-tbody">
                                    <tr><td colspan="4" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Recent Comments --}}
                <div class="col-lg-5">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-transparent d-flex justify-content-between align-items-center border-bottom">
                            <h6 class="mb-0">ბოლო კომენტარები</h6>
                            <button class="btn btn-link btn-sm p-0 text-decoration-none" id="sd-overview-all-comments-btn">ყველა →</button>
                        </div>
                        <div id="sd-recent-comments-list" class="list-group list-group-flush">
                            <div class="list-group-item text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════
             TAB 2: POSTS
        ═══════════════════════════════════════ --}}
        <div class="tab-pane fade" id="sd-posts" role="tabpanel">

            {{-- Posts filters --}}
            <div class="card mb-3 border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-4">
                            <input type="text" class="form-control form-control-sm" id="sd-posts-search"
                                   placeholder="პოსტის ძიება..." autocomplete="off">
                        </div>
                        <div class="col-auto">
                            <select class="form-select form-select-sm" id="sd-posts-filter-status">
                                <option value="all">ყველა სტატუსი</option>
                                <option value="draft">დრაფტი</option>
                                <option value="scheduled">დაგეგმილი</option>
                                <option value="published">გამოქვეყნებული</option>
                                <option value="failed">შეცდომა</option>
                            </select>
                        </div>
                        <div class="col-auto ms-auto">
                            <button class="btn btn-primary btn-sm" id="sd-posts-new-btn">
                                <i data-feather="plus" style="width:14px;height:14px;"></i> ახალი პოსტი
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Posts table --}}
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0" id="sd-posts-table">
                        <thead class="table-light">
                            <tr>
                                <th>პოსტი</th>
                                <th style="width:80px;">პლ.</th>
                                <th style="width:90px;">სტატუსი</th>
                                <th style="width:120px;">თარიღი</th>
                                <th style="width:90px;">ავტორი</th>
                                <th style="width:100px;">მოქმედება</th>
                            </tr>
                        </thead>
                        <tbody id="sd-posts-tbody">
                            <tr><td colspan="6" class="text-center py-4 text-muted">იტვირთება...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center py-2 d-none" id="sd-posts-pagination">
                    <span class="text-muted small" id="sd-posts-page-info"></span>
                    <div class="d-flex gap-1">
                        <button class="btn btn-outline-secondary btn-sm" id="sd-posts-prev" disabled>&laquo;</button>
                        <button class="btn btn-outline-secondary btn-sm" id="sd-posts-next" disabled>&raquo;</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════
             TAB 3: COMMENTS (embedded)
        ═══════════════════════════════════════ --}}
        <div class="tab-pane fade" id="sd-comments" role="tabpanel">
            <div id="sd-comments-container">
                @include('admin.social-comments._content', ['socialCommentsConfig' => $sdConfig['socialCommentsConfig']])
            </div>
        </div>

        {{-- ═══════════════════════════════════════
             TAB 4: SCHEDULE
        ═══════════════════════════════════════ --}}
        <div class="tab-pane fade" id="sd-schedule" role="tabpanel">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center border-bottom">
                    <h6 class="mb-0">დაგეგმილი პოსტები</h6>
                    <button class="btn btn-primary btn-sm" id="sd-schedule-new-btn">
                        <i data-feather="plus" style="width:14px;height:14px;"></i> პოსტის დაგეგმვა
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>პოსტი</th>
                                <th style="width:80px;">პლ.</th>
                                <th style="width:150px;">დაგეგმილი დრო</th>
                                <th style="width:100px;">მოქმედება</th>
                            </tr>
                        </thead>
                        <tbody id="sd-schedule-tbody">
                            <tr><td colspan="4" class="text-center py-4 text-muted">იტვირთება...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>{{-- /.tab-content --}}

{{-- ═══════════════════════════════════════
     POST CREATE/EDIT OFFCANVAS
═══════════════════════════════════════ --}}
<div class="card border-0 shadow-sm d-none mt-4 sd-post-panel" id="sd-post-offcanvas">
    <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center">
        <h5 class="mb-0" id="sd-post-offcanvas-title">ახალი პოსტი</h5>
        <button type="button" class="btn-close" id="sd-post-panel-close"></button>
    </div>
    <div class="card-body p-0 sd-post-panel-body">
        <div id="sd-post-form-container" class="p-3">
            <div class="text-center py-5"><div class="spinner-border spinner-border-sm"></div> <span class="ms-2 text-muted">იტვირთება...</span></div>
        </div>
    </div>
</div>

</div>

{{-- Config JSON --}}
<script id="sd-config" type="application/json">{!! json_encode($sdConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

@include('admin.partials._image_manager_modal')

@endfragment
@endsection
