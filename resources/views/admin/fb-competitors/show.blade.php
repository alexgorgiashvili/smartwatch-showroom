@extends('admin.layout')

@section('title', $page->name . ' — FB კონკურენტები')

@section('content')
@fragment('content')
<div data-page-title="FB კონკურენტი დეტალები">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item"><a href="{{ route('admin.fb-competitors') }}" data-pjax>კონკურენტები</a></li>
                    <li class="breadcrumb-item active">{{ $page->name }}</li>
                </ol>
            </nav>
            <h4 class="mb-0">{{ $page->name }}</h4>
            <a href="{{ $page->facebook_url }}" target="_blank" class="small text-muted">
                <i data-feather="external-link" style="width:12px;height:12px;"></i>
                {{ $page->facebook_url }}
            </a>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary btn-sm" id="btn-scrape">
                <i data-feather="refresh-cw" style="width:14px;height:14px;"></i> გაპარსვა
            </button>
            <button class="btn btn-success btn-sm" id="btn-analyze">
                <i data-feather="zap" style="width:14px;height:14px;"></i> AI ფილტრი
            </button>
        </div>
    </div>

    {{-- Stats --}}
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card">
                <div class="card-body text-center">
                    <i data-feather="file-text" class="text-primary mb-2" style="width:32px;height:32px;"></i>
                    <div class="text-muted small">სულ პოსტები</div>
                    <div class="fw-bold fs-3">{{ number_format($stats['total_posts']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card">
                <div class="card-body text-center">
                    <i data-feather="check-circle" class="text-success mb-2" style="width:32px;height:32px;"></i>
                    <div class="text-muted small">რელევანტური</div>
                    <div class="fw-bold fs-3 text-success">{{ number_format($stats['relevant_posts']) }}</div>
                    @if($stats['total_posts'] > 0)
                        <small class="text-muted">{{ round(($stats['relevant_posts'] / $stats['total_posts']) * 100, 1) }}%</small>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card">
                <div class="card-body text-center">
                    <i data-feather="heart" class="text-danger mb-2" style="width:32px;height:32px;"></i>
                    <div class="text-muted small">საშ. ჩართულობა</div>
                    <div class="fw-bold fs-3">{{ number_format($stats['avg_engagement'] ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card">
                <div class="card-body text-center">
                    <i data-feather="clock" class="text-info mb-2" style="width:32px;height:32px;"></i>
                    <div class="text-muted small">ბოლო გაპარსვა</div>
                    <div class="fw-bold">{{ $stats['last_scraped'] ?? 'არასდროს' }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Page Info --}}
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">გვერდის ინფორმაცია</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="text-muted small">სტატუსი</div>
                            <span class="badge bg-{{ $page->is_active ? 'success' : 'secondary' }}">
                                {{ $page->is_active ? 'აქტიური' : 'გამორთული' }}
                            </span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="text-muted small">გაპარსვის სიხშირე</div>
                            <div>{{ $page->scraping_frequency }}</div>
                        </div>
                        @if($page->category)
                        <div class="col-md-6 mb-3">
                            <div class="text-muted small">კატეგორია</div>
                            <div>{{ $page->category }}</div>
                        </div>
                        @endif
                        @if($page->follower_count > 0)
                        <div class="col-md-6 mb-3">
                            <div class="text-muted small">ფოლოვერები</div>
                            <div class="fw-bold">{{ number_format($page->follower_count) }}</div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Insights</h6>
                </div>
                <div class="card-body">
                    @forelse($insights as $insight)
                    <div class="mb-2">
                        <span class="badge bg-{{ $insight->priority === 'high' ? 'danger' : 'warning' }}">
                            {{ strtoupper($insight->priority) }}
                        </span>
                        <div class="small">{{ $insight->title }}</div>
                        <div class="small text-muted">{{ $insight->created_at->diffForHumans() }}</div>
                    </div>
                    @empty
                    <div class="text-muted small">არ არის insights</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Posts Table --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">პოსტები</h6>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-secondary filter-all active" data-filter="all">ყველა ({{ $posts->total() }})</button>
                <button class="btn btn-outline-success filter-relevant" data-filter="relevant">რელევანტური</button>
                <button class="btn btn-outline-secondary filter-unfiltered" data-filter="unfiltered">გაუფილტრავი</button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>პოსტი</th>
                        <th style="width:100px;">ჩართულობა</th>
                        <th style="width:80px;">რელევანტურობა</th>
                        <th style="width:120px;">თარიღი</th>
                        <th style="width:80px;">მოქმედება</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($posts as $post)
                    <tr data-relevance="{{ $post->is_relevant ? 'relevant' : ($post->is_relevant === null ? 'unfiltered' : 'irrelevant') }}"
                        class="cursor-pointer"
                        data-post-id="{{ $post->id }}"
                        data-post-b64="{{ base64_encode(json_encode($post)) }}">
                        <td>
                            <div class="small">{{ Str::limit($post->text, 150) }}</div>
                            <div class="mt-1">
                                @if($post->images_json)
                                    <span class="badge bg-info badge-sm">{{ count($post->images_json) }} სურათი</span>
                                @endif
                                @if($post->video_url)
                                    <span class="badge bg-danger badge-sm">ვიდეო</span>
                                @endif
                                @if($post->product_mentions_json)
                                    <span class="badge bg-warning badge-sm">პროდუქტი</span>
                                @endif
                            </div>
                        </td>
                        <td class="small">
                            <div><i data-feather="thumbs-up" style="width:12px;height:12px;"></i> {{ number_format($post->likes_count) }}</div>
                            <div><i data-feather="message-circle" style="width:12px;height:12px;"></i> {{ number_format($post->comments_count) }}</div>
                            <div><i data-feather="share-2" style="width:12px;height:12px;"></i> {{ number_format($post->shares_count) }}</div>
                        </td>
                        <td class="text-center">
                            @if($post->is_relevant !== null)
                                <span class="badge bg-{{ $post->is_relevant ? 'success' : 'secondary' }}">
                                    {{ $post->is_relevant ? $post->relevance_score : '—' }}
                                </span>
                                @if($post->relevance_reason)
                                    <div class="small text-muted mt-1" title="{{ $post->relevance_reason }}">
                                        {{ Str::limit($post->relevance_reason, 20) }}
                                    </div>
                                @endif
                            @else
                                <span class="badge bg-warning">გაუფილტრავი</span>
                            @endif
                        </td>
                        <td class="small">
                            {{ $post->posted_at?->format('M d, Y') }}<br>
                            <span class="text-muted">{{ $post->posted_at?->format('H:i') }}</span>
                        </td>
                        <td>
                            @if($post->post_url)
                                <a href="{{ $post->post_url }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i data-feather="external-link" style="width:12px;height:12px;"></i>
                                </a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">არ არის პოსტები</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($posts->hasPages())
        <div class="card-footer">
            {{ $posts->links() }}
        </div>
        @endif
    </div>
</div>

{{-- Post Details Modal --}}
<div class="modal fade" id="postDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">პოსტის დეტალები</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <span class="badge bg-primary" id="modal-post-date"></span>
                            <span class="badge bg-info ms-2" id="modal-post-type"></span>
                        </div>
                        <a href="#" id="modal-post-link" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i data-feather="external-link" style="width:12px;height:12px;"></i> Facebook-ზე ნახვა
                        </a>
                    </div>
                </div>

                <div class="mb-3">
                    <h6 class="text-muted">ტექსტი:</h6>
                    <div id="modal-post-text" class="border rounded p-3 bg-light" style="white-space: pre-wrap;"></div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <i data-feather="thumbs-up" class="text-primary mb-2" style="width:24px;height:24px;"></i>
                                <div class="small text-muted">Likes</div>
                                <div class="fw-bold" id="modal-post-likes">0</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <i data-feather="message-circle" class="text-info mb-2" style="width:24px;height:24px;"></i>
                                <div class="small text-muted">კომენტარები</div>
                                <div class="fw-bold" id="modal-post-comments">0</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <i data-feather="share-2" class="text-success mb-2" style="width:24px;height:24px;"></i>
                                <div class="small text-muted">გაზიარებები</div>
                                <div class="fw-bold" id="modal-post-shares">0</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3" id="modal-images-container" style="display:none;">
                    <h6 class="text-muted">სურათები:</h6>
                    <div id="modal-post-images" class="d-flex flex-wrap gap-2"></div>
                </div>

                <div class="mb-3" id="modal-video-container" style="display:none;">
                    <h6 class="text-muted">ვიდეო:</h6>
                    <a href="#" id="modal-post-video" target="_blank" class="btn btn-outline-danger">
                        <i data-feather="play" style="width:14px;height:14px;"></i> ვიდეოს ნახვა
                    </a>
                </div>

                <div class="mb-3" id="modal-relevance-container" style="display:none;">
                    <h6 class="text-muted">რელევანტურობა:</h6>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge" id="modal-relevance-badge"></span>
                        <span id="modal-relevance-score"></span>
                    </div>
                    <div class="mt-2" id="modal-relevance-reason"></div>
                </div>

                <div class="mb-3" id="modal-products-container" style="display:none;">
                    <h6 class="text-muted">პროდუქტის მოხსენიებები:</h6>
                    <div id="modal-post-products"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">დახურვა</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
if (window.AdminRouter) {
    window.AdminRouter.registerPageScript('fb-competitors-show', () => {
        initFbCompetitorsShow();
    });
}

function initFbCompetitorsShow() {
    setTimeout(() => {
        if (typeof feather !== 'undefined' && feather.icons) {
            const icons = document.querySelectorAll('i[data-feather]');
            icons.forEach(icon => {
                const iconName = icon.getAttribute('data-feather');
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

    // Post row click handler - open modal with full details
    document.querySelectorAll('tbody tr[data-post-b64]').forEach(row => {
        row.addEventListener('click', function(e) {
            // Don't trigger if clicking on external link button
            if (e.target.closest('a[target="_blank"]')) return;

            const postDataB64 = this.getAttribute('data-post-b64');
            const postData = JSON.parse(atob(postDataB64));
            showPostDetailsModal(postData);
        });
    });

    // Scrape button
    document.getElementById('btn-scrape')?.addEventListener('click', async function() {
        const btn = this;
        const icon = btn.querySelector('i');
        btn.disabled = true;
        icon.classList.add('icon-spin');

        try {
            const res = await fetch('{{ route('admin.fb-competitors.scrape', $page) }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ max_posts: 100 })
            });
            const data = await res.json();

            if (data.success) {
                showToast('success', data.message);
                setTimeout(() => location.reload(), 2000);
            } else {
                showToast('error', data.message);
            }
        } catch (e) {
            showToast('error', 'შეცდომა: ' + e.message);
        } finally {
            btn.disabled = false;
            icon.classList.remove('icon-spin');
        }
    });

    // Analyze button
    document.getElementById('btn-analyze')?.addEventListener('click', async function() {
        const btn = this;
        btn.disabled = true;

        try {
            const res = await fetch('{{ route('admin.fb-competitors.analyze') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ page_id: {{ $page->id }} })
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

    // Filter buttons
    document.querySelectorAll('[data-filter]').forEach(btn => {
        btn.addEventListener('click', function() {
            const filter = this.dataset.filter;
            document.querySelectorAll('[data-filter]').forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            document.querySelectorAll('tbody tr[data-relevance]').forEach(row => {
                if (filter === 'all') {
                    row.style.display = '';
                } else {
                    row.style.display = row.dataset.relevance === filter ? '' : 'none';
                }
            });
        });
    });
}

// Function to show post details in modal
function showPostDetailsModal(post) {
    // Date
    const date = post.posted_at ? new Date(post.posted_at).toLocaleDateString('ka-GE', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }) : 'უცნობი';
    document.getElementById('modal-post-date').textContent = date;

    // Post type
    let type = 'ტექსტი';
    if (post.video_url) type = 'ვიდეო';
    else if (post.images_json && post.images_json.length > 0) type = 'სურათი';
    document.getElementById('modal-post-type').textContent = type;

    // External link
    const linkBtn = document.getElementById('modal-post-link');
    if (post.post_url) {
        linkBtn.href = post.post_url;
        linkBtn.style.display = '';
    } else {
        linkBtn.style.display = 'none';
    }

    // Text
    document.getElementById('modal-post-text').textContent = post.text || 'ტექსტი არ არის';

    // Engagement stats
    document.getElementById('modal-post-likes').textContent = (post.likes_count || 0).toLocaleString();
    document.getElementById('modal-post-comments').textContent = (post.comments_count || 0).toLocaleString();
    document.getElementById('modal-post-shares').textContent = (post.shares_count || 0).toLocaleString();

    // Images
    const imagesContainer = document.getElementById('modal-images-container');
    const imagesDiv = document.getElementById('modal-post-images');
    if (post.images_json && post.images_json.length > 0) {
        imagesDiv.innerHTML = post.images_json.map(img =>
            `<a href="${img}" target="_blank"><img src="${img}" class="img-thumbnail" style="max-width:150px;max-height:150px;"></a>`
        ).join('');
        imagesContainer.style.display = '';
    } else {
        imagesContainer.style.display = 'none';
    }

    // Video
    const videoContainer = document.getElementById('modal-video-container');
    const videoLink = document.getElementById('modal-post-video');
    if (post.video_url) {
        videoLink.href = post.video_url;
        videoContainer.style.display = '';
    } else {
        videoContainer.style.display = 'none';
    }

    // Relevance
    const relevanceContainer = document.getElementById('modal-relevance-container');
    if (post.is_relevant !== null) {
        const badge = document.getElementById('modal-relevance-badge');
        badge.className = 'badge bg-' + (post.is_relevant ? 'success' : 'secondary');
        badge.textContent = post.is_relevant ? 'რელევანტური' : 'არარელევანტური';

        document.getElementById('modal-relevance-score').textContent = post.relevance_score ? `ქულა: ${post.relevance_score}` : '';
        document.getElementById('modal-relevance-reason').textContent = post.relevance_reason || '';
        relevanceContainer.style.display = '';
    } else {
        relevanceContainer.style.display = 'none';
    }

    // Products
    const productsContainer = document.getElementById('modal-products-container');
    const productsDiv = document.getElementById('modal-post-products');
    if (post.product_mentions_json && post.product_mentions_json.length > 0) {
        productsDiv.innerHTML = post.product_mentions_json.map(p =>
            `<span class="badge bg-warning me-1">${p}</span>`
        ).join('');
        productsContainer.style.display = '';
    } else {
        productsContainer.style.display = 'none';
    }

    // Re-initialize feather icons in modal
    setTimeout(() => {
        if (typeof feather !== 'undefined' && feather.icons) {
            const modalIcons = document.querySelectorAll('#postDetailsModal i[data-feather]');
            modalIcons.forEach(icon => {
                const iconName = icon.getAttribute('data-feather');
                if (iconName && feather.icons[iconName]) {
                    const svg = feather.icons[iconName].toSvg({
                        class: icon.className,
                        'stroke-width': icon.getAttribute('stroke-width') || 2
                    });
                    icon.outerHTML = svg;
                }
            });
        }
    }, 100);

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('postDetailsModal'));
    modal.show();
}

document.addEventListener('DOMContentLoaded', () => {
    initFbCompetitorsShow();
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
.cursor-pointer {
    cursor: pointer;
}
.cursor-pointer:hover {
    background-color: rgba(0, 123, 255, 0.05);
}
</style>
@endpush

@endfragment
@endsection
