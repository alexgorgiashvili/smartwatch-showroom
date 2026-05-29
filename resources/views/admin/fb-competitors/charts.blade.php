@extends('admin.layout')

@section('title', 'FB კონკურენტები - ანალიტიკა — Admin')

@section('content')
@fragment('content')
<div data-page-title="FB კონკურენტები - ანალიტიკა">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 gap-2">
        <div>
            <h4 class="mb-0">კონკურენტების ანალიტიკა</h4>
            <p class="text-muted small mb-0 d-none d-md-block">Engagement trends და შედარებითი ანალიზი</p>
        </div>
        <div class="d-flex gap-2 w-100 w-md-auto">
            <select id="date-range" class="form-select form-select-sm flex-grow-1 flex-md-grow-0" style="min-width:120px;">
                <option value="7">7 დღე</option>
                <option value="14">14 დღე</option>
                <option value="30" selected>30 დღე</option>
                <option value="90">90 დღე</option>
            </select>
            <a href="{{ route('admin.fb-competitors') }}" class="btn btn-outline-secondary btn-sm" data-pjax>
                <i data-feather="arrow-left" style="width:14px;height:14px;"></i> <span class="d-none d-sm-inline">უკან</span>
            </a>
        </div>
    </div>

    {{-- Engagement Trends --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0 small">Engagement Trends</h6>
                </div>
                <div class="card-body p-2 p-md-3">
                    <div style="position:relative; height:250px;">
                        <canvas id="engagement-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Competitor Comparison --}}
    <div class="row mb-4">
        <div class="col-12 col-md-6 mb-3 mb-md-0">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0 small">პოსტების რაოდენობა</h6>
                </div>
                <div class="card-body p-2 p-md-3">
                    <div style="position:relative; height:200px;">
                        <canvas id="posts-comparison-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0 small">საშუალო Engagement</h6>
                </div>
                <div class="card-body p-2 p-md-3">
                    <div style="position:relative; height:200px;">
                        <canvas id="engagement-comparison-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Content Performance --}}
    <div class="row mb-4">
        <div class="col-12 col-md-6 mb-3 mb-md-0">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0 small">საუკეთესო პოსტები</h6>
                </div>
                <div class="card-body p-2 p-md-3">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th class="small">კონკურენტი</th>
                                    <th class="small">პოსტი</th>
                                    <th class="small">Eng.</th>
                                </tr>
                            </thead>
                            <tbody id="top-posts-table">
                                <tr>
                                    <td colspan="3" class="text-center text-muted small">იტვირთება...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0 small">გამოქვეყნების სიხშირე</h6>
                </div>
                <div class="card-body p-2 p-md-3">
                    <div style="position:relative; height:200px;">
                        <canvas id="posting-frequency-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
let engagementChart, postsComparisonChart, engagementComparisonChart, postingFrequencyChart;

async function loadAnalytics(days = 30) {
    try {
        const res = await fetch(`{{ route('admin.fb-competitors.analytics') }}?days=${days}`);
        const data = await res.json();

        if (!data.success) {
            showToast('error', 'მონაცემების ჩატვირთვა ვერ მოხერხდა');
            return;
        }

        renderEngagementTrends(data.engagement_trends);
        renderCompetitorComparison(data.competitor_comparison);
        renderTopPosts(data.top_posts);
        renderPostingFrequency(data.posting_frequency);

    } catch (e) {
        console.error('Analytics load failed:', e);
        showToast('error', 'შეცდომა: ' + e.message);
    }
}

function renderEngagementTrends(data) {
    const ctx = document.getElementById('engagement-chart');

    if (engagementChart) engagementChart.destroy();

    engagementChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.dates,
            datasets: data.competitors.map((comp, idx) => ({
                label: comp.name,
                data: comp.engagement,
                borderColor: getColor(idx),
                backgroundColor: getColor(idx, 0.1),
                tension: 0.4,
                fill: true
            }))
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        boxWidth: 12,
                        font: { size: 10 }
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { font: { size: 10 } },
                    title: {
                        display: window.innerWidth > 768,
                        text: 'Engagement',
                        font: { size: 10 }
                    }
                },
                x: {
                    ticks: {
                        font: { size: 9 },
                        maxRotation: 45,
                        minRotation: 45
                    }
                }
            }
        }
    });
}

function renderCompetitorComparison(data) {
    // Posts comparison
    const postsCtx = document.getElementById('posts-comparison-chart');
    if (postsComparisonChart) postsComparisonChart.destroy();

    postsComparisonChart = new Chart(postsCtx, {
        type: 'bar',
        data: {
            labels: data.map(c => c.name),
            datasets: [{
                label: 'სულ პოსტები',
                data: data.map(c => c.total_posts),
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }, {
                label: 'რელევანტური',
                data: data.map(c => c.relevant_posts),
                backgroundColor: 'rgba(75, 192, 192, 0.5)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        boxWidth: 12,
                        font: { size: 10 }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { font: { size: 10 } }
                },
                x: {
                    ticks: {
                        font: { size: 9 },
                        maxRotation: 45,
                        minRotation: 0
                    }
                }
            }
        }
    });

    // Engagement comparison
    const engCtx = document.getElementById('engagement-comparison-chart');
    if (engagementComparisonChart) engagementComparisonChart.destroy();

    engagementComparisonChart = new Chart(engCtx, {
        type: 'bar',
        data: {
            labels: data.map(c => c.name),
            datasets: [{
                label: 'საშუალო Engagement',
                data: data.map(c => c.avg_engagement),
                backgroundColor: data.map((_, idx) => getColor(idx, 0.5)),
                borderColor: data.map((_, idx) => getColor(idx)),
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
}

function renderTopPosts(posts) {
    const tbody = document.getElementById('top-posts-table');

    if (!posts || posts.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">არ არის მონაცემები</td></tr>';
        return;
    }

    tbody.innerHTML = posts.map(post => `
        <tr>
            <td class="small">${post.competitor}</td>
            <td class="small">${post.text.substring(0, 50)}...</td>
            <td class="text-end">
                <span class="badge bg-success">${post.total_engagement}</span>
            </td>
        </tr>
    `).join('');
}

function renderPostingFrequency(data) {
    const ctx = document.getElementById('posting-frequency-chart');
    if (postingFrequencyChart) postingFrequencyChart.destroy();

    postingFrequencyChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.map(c => c.name),
            datasets: [{
                label: 'პოსტები კვირაში',
                data: data.map(c => c.posts_per_week),
                backgroundColor: 'rgba(255, 159, 64, 0.5)',
                borderColor: 'rgba(255, 159, 64, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
}

function getColor(index, alpha = 1) {
    const colors = [
        `rgba(54, 162, 235, ${alpha})`,
        `rgba(255, 99, 132, ${alpha})`,
        `rgba(75, 192, 192, ${alpha})`,
        `rgba(255, 159, 64, ${alpha})`,
        `rgba(153, 102, 255, ${alpha})`,
    ];
    return colors[index % colors.length];
}

document.addEventListener('DOMContentLoaded', () => {
    loadAnalytics(30);

    document.getElementById('date-range')?.addEventListener('change', function() {
        loadAnalytics(parseInt(this.value));
    });
});
</script>
@endpush

@endfragment
@endsection
