@extends('admin.layout')

@section('title', 'FB კონკურენტები — Admin')

@section('content')
@fragment('content')
<div data-page-title="FB კონკურენტები">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 gap-2">
        <div>
            <h4 class="mb-0">Facebook კონკურენტების მონიტორინგი</h4>
            <p class="text-muted small mb-0 d-none d-md-block">კონკურენტების გვერდების ანალიზი და insights</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.fb-competitors.charts') }}" class="btn btn-outline-info btn-sm" data-pjax>
                <i data-feather="bar-chart-2" style="width:14px;height:14px;"></i> <span class="d-none d-sm-inline">ანალიტიკა</span>
            </a>
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                    <i data-feather="download" style="width:14px;height:14px;"></i> <span class="d-none d-sm-inline">Export</span>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="{{ route('admin.fb-competitors.export', ['type' => 'posts']) }}">პოსტები (CSV)</a></li>
                    <li><a class="dropdown-item" href="{{ route('admin.fb-competitors.export', ['type' => 'competitors']) }}">კონკურენტები (CSV)</a></li>
                </ul>
            </div>
            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCompetitorModal">
                <i data-feather="plus" style="width:14px;height:14px;"></i> <span class="d-none d-sm-inline">დამატება</span>
            </button>
            <button class="btn btn-primary btn-sm" id="btn-scrape-all">
                <i data-feather="refresh-cw" style="width:14px;height:14px;"></i> <span class="d-none d-md-inline">ყველას გაპარსვა</span>
            </button>
            <button class="btn btn-success btn-sm" id="btn-analyze-all">
                <i data-feather="zap" style="width:14px;height:14px;"></i> <span class="d-none d-md-inline">AI ანალიზი</span>
            </button>
            <button class="btn btn-info btn-sm" id="btn-weekly-analysis">
                <i data-feather="trending-up" style="width:14px;height:14px;"></i> <span class="d-none d-lg-inline">კვირეული ანალიზი</span>
            </button>
        </div>
    </div>

    {{-- Stats Overview --}}
    <div class="row mb-4">
        <div class="col-6 col-md-2 mb-3">
            <div class="card h-100">
                <div class="card-body text-center py-2 py-md-3">
                    <i data-feather="users" class="text-primary mb-1 mb-md-2" style="width:20px;height:20px;"></i>
                    <div class="text-muted" style="font-size:0.7rem;">კონკურენტები</div>
                    <div class="fw-bold fs-5 fs-md-4">{{ $stats['total_pages'] }}</div>
                    <small class="text-success" style="font-size:0.65rem;">{{ $stats['active_pages'] }} აქტიური</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2 mb-3">
            <div class="card h-100">
                <div class="card-body text-center py-2 py-md-3">
                    <i data-feather="file-text" class="text-info mb-1 mb-md-2" style="width:20px;height:20px;"></i>
                    <div class="text-muted" style="font-size:0.7rem;">სულ პოსტები</div>
                    <div class="fw-bold fs-5 fs-md-4">{{ number_format($stats['total_posts']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2 mb-3">
            <div class="card h-100">
                <div class="card-body text-center py-2 py-md-3">
                    <i data-feather="check-circle" class="text-success mb-1 mb-md-2" style="width:20px;height:20px;"></i>
                    <div class="text-muted" style="font-size:0.7rem;">რელევანტური</div>
                    <div class="fw-bold fs-5 fs-md-4 text-success">{{ number_format($stats['relevant_posts']) }}</div>
                    <small class="text-muted" style="font-size:0.65rem;">
                        {{ $stats['total_posts'] > 0 ? round(($stats['relevant_posts'] / $stats['total_posts']) * 100, 1) : 0 }}%
                    </small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="card h-100">
                <div class="card-body text-center py-2 py-md-3">
                    <i data-feather="alert-circle" class="text-warning mb-1 mb-md-2" style="width:20px;height:20px;"></i>
                    <div class="text-muted" style="font-size:0.7rem;">ახალი Insights</div>
                    <div class="fw-bold fs-5 fs-md-4 text-warning">{{ $stats['pending_insights'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3 mb-3">
            <div class="card h-100">
                <div class="card-body text-center py-2 py-md-3">
                    <i data-feather="clock" class="text-muted mb-1 mb-md-2" style="width:20px;height:20px;"></i>
                    <div class="text-muted" style="font-size:0.7rem;">ბოლო გაპარსვა</div>
                    <div class="fw-bold small">{{ $stats['last_scrape'] ? $stats['last_scrape']->diffForHumans() : 'არასდროს' }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="competitors-tab" data-bs-toggle="tab" data-bs-target="#competitors" type="button">
                <i data-feather="users" style="width:14px;height:14px;"></i> კონკურენტები
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="posts-tab" data-bs-toggle="tab" data-bs-target="#posts" type="button">
                <i data-feather="file-text" style="width:14px;height:14px;"></i> რელევანტური პოსტები
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link position-relative" id="insights-tab" data-bs-toggle="tab" data-bs-target="#insights" type="button">
                <i data-feather="zap" style="width:14px;height:14px;"></i> Insights
                @if($stats['pending_insights'] > 0)
                    <span class="badge bg-warning position-absolute top-0 start-100 translate-middle">{{ $stats['pending_insights'] }}</span>
                @endif
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="analysis-tab" data-bs-toggle="tab" data-bs-target="#analysis" type="button">
                <i data-feather="trending-up" style="width:14px;height:14px;"></i> ანალიზები
            </button>
        </li>
    </ul>

    <div class="tab-content">
        {{-- Competitors Tab --}}
        <div class="tab-pane fade show active" id="competitors" role="tabpanel">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>კონკურენტი</th>
                                <th style="width:120px;">სტატუსი</th>
                                <th style="width:100px;">პოსტები</th>
                                <th style="width:100px;">რელევანტური</th>
                                <th style="width:150px;">ბოლო გაპარსვა</th>
                                <th style="width:100px;">სიხშირე</th>
                                <th style="width:180px;">მოქმედებები</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pages as $page)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $page->name }}</div>
                                    <a href="{{ $page->facebook_url }}" target="_blank" class="small text-muted text-decoration-none">
                                        <i data-feather="external-link" style="width:10px;height:10px;"></i>
                                        {{ Str::limit($page->facebook_url, 40) }}
                                    </a>
                                    @if($page->category)
                                        <div><span class="badge bg-light text-dark">{{ $page->category }}</span></div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $page->is_active ? 'success' : 'secondary' }}">
                                        {{ $page->is_active ? 'აქტიური' : 'გამორთული' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold">{{ number_format($page->posts_count) }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="text-success fw-bold">{{ number_format($page->relevant_posts_count) }}</span>
                                    @if($page->posts_count > 0)
                                        <br><small class="text-muted">{{ round(($page->relevant_posts_count / $page->posts_count) * 100, 1) }}%</small>
                                    @endif
                                </td>
                                <td class="small">
                                    {{ $page->last_scraped_at ? $page->last_scraped_at->diffForHumans() : '—' }}
                                </td>
                                <td>
                                    <span class="badge bg-{{ $page->scraping_frequency === 'daily' ? 'primary' : 'info' }}">
                                        {{ $page->scraping_frequency }}
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('admin.fb-competitors.show', $page) }}" class="btn btn-outline-primary" data-pjax>
                                            <i data-feather="eye" style="width:12px;height:12px;"></i>
                                        </a>
                                        <button class="btn btn-outline-success btn-scrape" data-page-id="{{ $page->id }}">
                                            <i data-feather="refresh-cw" style="width:12px;height:12px;"></i>
                                        </button>
                                        <button class="btn btn-outline-warning btn-edit" data-page-id="{{ $page->id }}" data-page="{{ json_encode($page) }}">
                                            <i data-feather="edit" style="width:12px;height:12px;"></i>
                                        </button>
                                        <button class="btn btn-outline-danger btn-delete" data-page-id="{{ $page->id }}" data-page-name="{{ $page->name }}">
                                            <i data-feather="trash-2" style="width:12px;height:12px;"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    არ არის დამატებული კონკურენტები
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Recent Posts Tab --}}
        <div class="tab-pane fade" id="posts" role="tabpanel">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>კონკურენტი</th>
                                <th>პოსტი</th>
                                <th style="width:100px;">ჩართულობა</th>
                                <th style="width:80px;">ქულა</th>
                                <th style="width:120px;">თარიღი</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentPosts as $post)
                            <tr>
                                <td>
                                    <div class="fw-bold small">{{ $post->competitorPage->name ?? 'N/A' }}</div>
                                </td>
                                <td>
                                    <div class="small">{{ Str::limit($post->text, 120) }}</div>
                                    @if($post->images_json)
                                        <span class="badge bg-info">{{ count($post->images_json) }} სურათი</span>
                                    @endif
                                    @if($post->video_url)
                                        <span class="badge bg-danger">ვიდეო</span>
                                    @endif
                                    @if($post->post_url)
                                        <a href="{{ $post->post_url }}" target="_blank" class="small">
                                            <i data-feather="external-link" style="width:10px;height:10px;"></i>
                                        </a>
                                    @endif
                                </td>
                                <td class="small text-center">
                                    <div><i data-feather="thumbs-up" style="width:12px;height:12px;"></i> {{ number_format($post->likes_count) }}</div>
                                    <div><i data-feather="message-circle" style="width:12px;height:12px;"></i> {{ number_format($post->comments_count) }}</div>
                                    <div><i data-feather="share-2" style="width:12px;height:12px;"></i> {{ number_format($post->shares_count) }}</div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $post->relevance_score >= 80 ? 'success' : ($post->relevance_score >= 50 ? 'warning' : 'secondary') }}">
                                        {{ $post->relevance_score }}
                                    </span>
                                </td>
                                <td class="small text-muted">
                                    {{ $post->posted_at?->format('M d, H:i') }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    ჯერ არ არის რელევანტური პოსტები
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Insights Tab --}}
        <div class="tab-pane fade" id="insights" role="tabpanel">
            <div class="row">
                @forelse($insights as $insight)
                <div class="col-md-6 mb-3">
                    <div class="card h-100 border-start border-{{ $insight->priority === 'high' ? 'danger' : ($insight->priority === 'medium' ? 'warning' : 'info') }} border-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <span class="badge bg-{{ $insight->priority === 'high' ? 'danger' : ($insight->priority === 'medium' ? 'warning' : 'info') }}">
                                        {{ strtoupper($insight->priority) }}
                                    </span>
                                    <span class="badge bg-light text-dark">{{ $insight->insight_type }}</span>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                        სტატუსი: {{ $insight->status }}
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item btn-insight-status" data-insight-id="{{ $insight->id }}" data-status="acknowledged" href="#">acknowledged</a></li>
                                        <li><a class="dropdown-item btn-insight-status" data-insight-id="{{ $insight->id }}" data-status="actioned" href="#">actioned</a></li>
                                        <li><a class="dropdown-item btn-insight-status" data-insight-id="{{ $insight->id }}" data-status="dismissed" href="#">dismissed</a></li>
                                    </ul>
                                </div>
                            </div>
                            <h6 class="card-title">{{ $insight->title }}</h6>
                            <p class="card-text small text-muted">{{ $insight->description }}</p>
                            @if($insight->competitorPage)
                                <div class="small"><strong>კონკურენტი:</strong> {{ $insight->competitorPage->name }}</div>
                            @endif
                            <div class="small text-muted mt-2">{{ $insight->created_at->diffForHumans() }}</div>
                        </div>
                    </div>
                </div>
                @empty
                <div class="col-12">
                    <div class="alert alert-info">
                        <i data-feather="info" style="width:16px;height:16px;"></i>
                        ჯერ არ არის insights. გაუშვით კვირეული ანალიზი.
                    </div>
                </div>
                @endforelse
            </div>
        </div>

        {{-- Analysis Tab --}}
        <div class="tab-pane fade" id="analysis" role="tabpanel">
            @if($recentAnalysis)
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">უახლესი ანალიზი — {{ $recentAnalysis->analysis_date }}</h6>
                    <a href="{{ route('admin.fb-competitors.analysis', $recentAnalysis) }}" class="btn btn-sm btn-outline-primary" data-pjax>
                        სრული ანალიზი <i data-feather="arrow-right" style="width:12px;height:12px;"></i>
                    </a>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="text-muted small">გაანალიზებული პოსტები</div>
                            <div class="fw-bold fs-5">{{ $recentAnalysis->posts_analyzed_count }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted small">AI მოდელი</div>
                            <div class="small">{{ $recentAnalysis->ai_model_used }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted small">რეკომენდაციები</div>
                            <div class="fw-bold">{{ count($recentAnalysis->recommendations_json ?? []) }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted small">თარიღი</div>
                            <div class="small">{{ $recentAnalysis->created_at->diffForHumans() }}</div>
                        </div>
                    </div>

                    @if($recentAnalysis->recommendations_json)
                    <h6 class="mb-2">რეკომენდაციები:</h6>
                    <ul class="list-group">
                        @foreach(array_slice($recentAnalysis->recommendations_json, 0, 5) as $rec)
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <strong>{{ $rec['title'] ?? $rec['action'] ?? 'N/A' }}</strong>
                                <span class="badge bg-{{ ($rec['priority'] ?? 'medium') === 'high' ? 'danger' : 'warning' }}">
                                    {{ strtoupper($rec['priority'] ?? 'medium') }}
                                </span>
                            </div>
                            <div class="small text-muted">{{ $rec['reasoning'] ?? $rec['description'] ?? '' }}</div>
                        </li>
                        @endforeach
                    </ul>
                    @endif
                </div>
            </div>
            @else
            <div class="alert alert-info">
                <i data-feather="info" style="width:16px;height:16px;"></i>
                ჯერ არ ჩატარებულა კვირეული ანალიზი. დააჭირეთ "კვირეული ანალიზი" ღილაკს.
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Add Competitor Modal --}}
<div class="modal fade" id="addCompetitorModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.fb-competitors.store') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">ახალი კონკურენტის დამატება</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">სახელი *</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Facebook URL *</label>
                    <input type="url" name="facebook_url" class="form-control" placeholder="https://facebook.com/..." required>
                </div>
                <div class="mb-3">
                    <label class="form-label">კატეგორია</label>
                    <input type="text" name="category" class="form-control" placeholder="მაგ: ელექტრონიკა, საათები">
                </div>
                <div class="mb-3">
                    <label class="form-label">გაპარსვის სიხშირე</label>
                    <select name="scraping_frequency" class="form-select">
                        <option value="daily" selected>ყოველდღიური</option>
                        <option value="weekly">კვირაში</option>
                        <option value="manual">ხელით</option>
                    </select>
                </div>
                <div class="form-check">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" class="form-check-input" id="is_active" value="1" checked>
                    <label class="form-check-label" for="is_active">აქტიური</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">გაუქმება</button>
                <button type="submit" class="btn btn-primary">დამატება</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Competitor Modal --}}
<div class="modal fade" id="editCompetitorModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="edit-form" class="modal-content">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title">კონკურენტის რედაქტირება</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">სახელი *</label>
                    <input type="text" name="name" id="edit-name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Facebook URL *</label>
                    <input type="url" name="facebook_url" id="edit-url" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">კატეგორია</label>
                    <input type="text" name="category" id="edit-category" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">გაპარსვის სიხშირე</label>
                    <select name="scraping_frequency" id="edit-frequency" class="form-select">
                        <option value="daily">ყოველდღიური</option>
                        <option value="weekly">კვირაში</option>
                        <option value="manual">ხელით</option>
                    </select>
                </div>
                <div class="form-check">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" class="form-check-input" id="edit-is-active" value="1">
                    <label class="form-check-label" for="edit-is-active">აქტიური</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">გაუქმება</button>
                <button type="submit" class="btn btn-primary">შენახვა</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
// Page-specific JS registration for PJAX
if (window.AdminRouter) {
    window.AdminRouter.registerPageScript('fb-competitors-index', () => {
        initFbCompetitorsIndex();
    });
}

function initFbCompetitorsIndex() {
    // Manual icon replacement with validation
    setTimeout(() => {
        if (typeof feather !== 'undefined' && feather.icons) {
            const icons = document.querySelectorAll('i[data-feather]');
            icons.forEach(icon => {
                const iconName = icon.getAttribute('data-feather');
                // Only replace if icon exists in library
                if (iconName && feather.icons[iconName]) {
                    const svg = feather.icons[iconName].toSvg({
                        class: icon.className,
                        'stroke-width': icon.getAttribute('stroke-width') || 2
                    });
                    icon.outerHTML = svg;
                } else if (iconName) {
                    console.warn('Feather icon not found:', iconName);
                }
            });
        }
    }, 150);
    // Scrape single page
    document.querySelectorAll('.btn-scrape').forEach(btn => {
        btn.addEventListener('click', async function() {
            const pageId = this.dataset.pageId;
            const icon = this.querySelector('svg');
            if (icon) icon.classList.add('icon-spin');

            try {
                const res = await fetch(`{{ url('admin/fb-competitors') }}/${pageId}/scrape`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ max_posts: 50 })
                });
                const data = await res.json();

                if (data.success) {
                    showToast('success', data.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('error', data.message);
                }
            } catch (e) {
                showToast('error', 'შეცდომა: ' + e.message);
            } finally {
                if (icon) icon.classList.remove('icon-spin');
            }
        });
    });

    // Edit button
    document.querySelectorAll('.btn-edit').forEach(btn => {
        btn.addEventListener('click', function() {
            const page = JSON.parse(this.dataset.page);
            document.getElementById('edit-form').action = `{{ url('admin/fb-competitors') }}/${page.id}`;
            document.getElementById('edit-name').value = page.name;
            document.getElementById('edit-url').value = page.facebook_url;
            document.getElementById('edit-category').value = page.category || '';
            document.getElementById('edit-frequency').value = page.scraping_frequency;
            document.getElementById('edit-is-active').checked = page.is_active;
            new bootstrap.Modal(document.getElementById('editCompetitorModal')).show();
        });
    });

    // Delete button
    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', async function() {
            const pageId = this.dataset.pageId;
            const pageName = this.dataset.pageName;

            if (!confirm(`დარწმუნებული ხართ, რომ გსურთ "${pageName}" წაშლა?`)) return;

            try {
                const res = await fetch(`{{ url('admin/fb-competitors') }}/${pageId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                });

                const data = await res.json();

                if (data.success) {
                    showToast('success', data.message);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('error', data.message || 'წაშლა ვერ მოხერხდა');
                }
            } catch (e) {
                showToast('error', 'შეცდომა: ' + e.message);
            }
        });
    });

    // Scrape all
    document.getElementById('btn-scrape-all')?.addEventListener('click', async function() {
        if (!confirm('გსურთ ყველა აქტიური კონკურენტის გაპარსვა? დასჭირდება რამდენიმე წუთი.')) return;

        showToast('info', 'დაიწყო გაპარსვა...');
        // Implement batch scraping or call artisan command via API
    });

    // Analyze all
    document.getElementById('btn-analyze-all')?.addEventListener('click', async function() {
        const btn = this;
        btn.disabled = true;

        try {
            const res = await fetch('{{ route('admin.fb-competitors.analyze') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                }
            });
            const data = await res.json();

            if (data.success) {
                showToast('success', data.message);
                setTimeout(() => location.reload(), 2000);
            } else {
                showToast('warning', data.message);
            }
        } catch (e) {
            showToast('error', 'ანალიზი ვერ მოხერხდა');
        } finally {
            btn.disabled = false;
        }
    });

    // Weekly analysis
    document.getElementById('btn-weekly-analysis')?.addEventListener('click', async function() {
        if (!confirm('გსურთ კვირეული AI ანალიზის გაშვება? (OpenAI API გამოყენება)')) return;

        const btn = this;
        btn.disabled = true;
        showToast('info', 'მიმდინარეობს ანალიზი...');

        try {
            const res = await fetch('{{ route('admin.fb-competitors.weekly-analysis') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
            const data = await res.json();

            if (data.success) {
                showToast('success', data.message);
                setTimeout(() => location.reload(), 2000);
            } else {
                showToast('warning', data.message);
            }
        } catch (e) {
            showToast('error', 'ანალიზი ვერ მოხერხდა');
        } finally {
            btn.disabled = false;
        }
    });

    // Insight status update
    document.querySelectorAll('.btn-insight-status').forEach(btn => {
        btn.addEventListener('click', async function(e) {
            e.preventDefault();
            const insightId = this.dataset.insightId;
            const status = this.dataset.status;

            try {
                const res = await fetch(`{{ url('admin/fb-competitors/insights') }}/${insightId}`, {
                    method: 'PUT',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ status })
                });
                const data = await res.json();

                if (data.success) {
                    showToast('success', data.message);
                    setTimeout(() => location.reload(), 1000);
                }
            } catch (e) {
                showToast('error', 'განახლება ვერ მოხერხდა');
            }
        });
    });

}

// Run on initial page load
document.addEventListener('DOMContentLoaded', () => {
    initFbCompetitorsIndex();
});
</script>

<style>
.icon-spin {
    animation: spin 1s linear infinite;
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
@endpush

@endfragment
@endsection
