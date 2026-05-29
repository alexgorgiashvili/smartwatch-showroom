@extends('admin.layout')

@section('title', 'კვირეული ანალიზი — FB კონკურენტები')

@section('content')
@fragment('content')
<div data-page-title="კვირეული ანალიზი">

    <div class="mb-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="{{ route('admin.fb-competitors') }}" data-pjax>კონკურენტები</a></li>
                <li class="breadcrumb-item active">ანალიზი</li>
            </ol>
        </nav>
        <h4 class="mb-0">კვირეული ანალიზი — {{ $analysis->analysis_date }}</h4>
        <p class="text-muted small mb-0">AI-ით გენერირებული competitive intelligence</p>
    </div>

    {{-- Summary --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="text-muted small">გაანალიზებული პოსტები</div>
                    <div class="fw-bold fs-3">{{ $analysis->posts_analyzed_count }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="text-muted small">AI მოდელი</div>
                    <div class="small">{{ $analysis->ai_model_used }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="text-muted small">რეკომენდაციები</div>
                    <div class="fw-bold fs-3">{{ count($analysis->recommendations_json ?? []) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="text-muted small">კონკურენტები</div>
                    <div class="fw-bold fs-3">{{ count($pages) }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Competitors Analyzed --}}
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0">გაანალიზებული კონკურენტები</h6>
        </div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2">
                @foreach($pages as $page)
                    <a href="{{ route('admin.fb-competitors.show', $page) }}" class="badge bg-primary text-decoration-none" data-pjax>
                        {{ $page->name }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Recommendations --}}
    @if($analysis->recommendations_json)
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0">რეკომენდაციები</h6>
        </div>
        <div class="card-body">
            <div class="list-group">
                @foreach($analysis->recommendations_json as $rec)
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="mb-0">{{ $rec['title'] ?? $rec['action'] ?? 'N/A' }}</h6>
                        <span class="badge bg-{{ ($rec['priority'] ?? 'medium') === 'high' ? 'danger' : (($rec['priority'] ?? 'medium') === 'medium' ? 'warning' : 'info') }}">
                            {{ strtoupper($rec['priority'] ?? 'medium') }}
                        </span>
                    </div>
                    @if(isset($rec['reasoning']) || isset($rec['description']))
                        <p class="mb-1 text-muted">{{ $rec['reasoning'] ?? $rec['description'] ?? '' }}</p>
                    @endif
                    @if(isset($rec['category']))
                        <span class="badge bg-light text-dark">{{ $rec['category'] }}</span>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Competitive Intelligence --}}
    @if($analysis->competitive_intelligence_json)
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0"><i data-feather="activity" style="width:16px;height:16px;"></i> Competitive Intelligence</h6>
        </div>
        <div class="card-body">
            @php $intel = $analysis->competitive_intelligence_json; @endphp

            {{-- Pricing Insights --}}
            @if(isset($intel['pricing_insights']) && count($intel['pricing_insights']) > 0)
            <h6 class="mb-3">ფასების ანალიზი</h6>
            <div class="table-responsive mb-4">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>კონკურენტი</th>
                            <th>პროდუქტი</th>
                            <th>ფასი</th>
                            <th>ტრენდი</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($intel['pricing_insights'] as $price)
                        <tr>
                            <td>{{ $price['competitor'] ?? 'N/A' }}</td>
                            <td>{{ $price['product'] ?? 'N/A' }}</td>
                            <td class="fw-bold">{{ $price['price'] ?? 'N/A' }}</td>
                            <td>
                                <span class="badge bg-{{ ($price['trend'] ?? 'stable') === 'up' ? 'danger' : (($price['trend'] ?? 'stable') === 'down' ? 'success' : 'secondary') }}">
                                    {{ $price['trend'] ?? 'stable' }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            {{-- Feature Highlights --}}
            @if(isset($intel['feature_highlights']) && count($intel['feature_highlights']) > 0)
            <h6 class="mb-3">აქცენტირებული ფუნქციები</h6>
            <div class="row mb-4">
                @foreach($intel['feature_highlights'] as $feature)
                <div class="col-md-6 mb-3">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6>{{ $feature['competitor'] ?? 'N/A' }}</h6>
                            <div class="d-flex flex-wrap gap-1">
                                @foreach($feature['features_promoted'] ?? [] as $f)
                                    <span class="badge bg-primary">{{ $f }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            {{-- Market Positioning --}}
            @if(isset($intel['market_positioning']) && count($intel['market_positioning']) > 0)
            <h6 class="mb-3">ბაზრის პოზიციონირება</h6>
            @foreach($intel['market_positioning'] as $pos)
            <div class="alert alert-info">
                <strong>{{ $pos['competitor'] ?? 'N/A' }}:</strong> {{ $pos['positioning'] ?? 'N/A' }}
            </div>
            @endforeach
            @endif
        </div>
    </div>
    @endif

    {{-- Content Strategy --}}
    @if($analysis->content_strategy_json)
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0"><i data-feather="edit" style="width:16px;height:16px;"></i> კონტენტ სტრატეგია</h6>
        </div>
        <div class="card-body">
            @php $strategy = $analysis->content_strategy_json; @endphp

            @if(isset($strategy['best_performing_types']) && count($strategy['best_performing_types']) > 0)
            <h6 class="mb-3">საუკეთესო კონტენტის ტიპები</h6>
            <div class="table-responsive mb-4">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>ტიპი</th>
                            <th>კონკურენტი</th>
                            <th>საშუალო ჩართულობა</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($strategy['best_performing_types'] as $type)
                        <tr>
                            <td><span class="badge bg-info">{{ $type['type'] ?? 'N/A' }}</span></td>
                            <td>{{ $type['competitor'] ?? 'N/A' }}</td>
                            <td class="fw-bold">{{ $type['avg_engagement'] ?? 0 }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            @if(isset($strategy['optimal_times']))
            <div class="alert alert-success">
                <strong>ოპტიმალური დრო:</strong> {{ is_array($strategy['optimal_times']) ? implode(', ', $strategy['optimal_times']) : $strategy['optimal_times'] }}
            </div>
            @endif

            @if(isset($strategy['engagement_patterns']))
            <div class="alert alert-info">
                <strong>ჩართულობის პატერნები:</strong> {{ $strategy['engagement_patterns'] }}
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Sentiment Analysis --}}
    @if($analysis->sentiment_analysis_json)
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0"><i data-feather="smile" style="width:16px;height:16px;"></i> სენტიმენტის ანალიზი</h6>
        </div>
        <div class="card-body">
            @php $sentiment = $analysis->sentiment_analysis_json; @endphp

            @if(isset($sentiment['overall']))
            <div class="alert alert-{{ $sentiment['overall'] === 'positive' ? 'success' : ($sentiment['overall'] === 'negative' ? 'danger' : 'secondary') }} mb-3">
                <strong>მთლიანი სენტიმენტი:</strong> {{ $sentiment['overall'] }}
            </div>
            @endif

            @if(isset($sentiment['by_competitor']) && count($sentiment['by_competitor']) > 0)
            <h6 class="mb-3">კონკურენტების მიხედვით</h6>
            <div class="table-responsive mb-3">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>კონკურენტი</th>
                            <th>სენტიმენტი</th>
                            <th>ქულა</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sentiment['by_competitor'] as $comp)
                        <tr>
                            <td>{{ $comp['competitor'] ?? 'N/A' }}</td>
                            <td>
                                <span class="badge bg-{{ ($comp['sentiment'] ?? 'neutral') === 'positive' ? 'success' : (($comp['sentiment'] ?? 'neutral') === 'negative' ? 'danger' : 'secondary') }}">
                                    {{ $comp['sentiment'] ?? 'neutral' }}
                                </span>
                            </td>
                            <td>{{ $comp['score'] ?? 0 }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            <div class="row">
                @if(isset($sentiment['common_praise']) && count($sentiment['common_praise']) > 0)
                <div class="col-md-6 mb-3">
                    <div class="card bg-success bg-opacity-10">
                        <div class="card-body">
                            <h6 class="text-success">ხშირი დადებითი</h6>
                            <ul class="mb-0">
                                @foreach($sentiment['common_praise'] as $praise)
                                    <li>{{ $praise }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
                @endif

                @if(isset($sentiment['common_complaints']) && count($sentiment['common_complaints']) > 0)
                <div class="col-md-6 mb-3">
                    <div class="card bg-danger bg-opacity-10">
                        <div class="card-body">
                            <h6 class="text-danger">ხშირი საჩივრები</h6>
                            <ul class="mb-0">
                                @foreach($sentiment['common_complaints'] as $complaint)
                                    <li>{{ $complaint }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- Trend Analysis --}}
    @if($analysis->trend_analysis_json)
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0"><i data-feather="trending-up" style="width:16px;height:16px;"></i> ტრენდების ანალიზი</h6>
        </div>
        <div class="card-body">
            @php $trends = $analysis->trend_analysis_json; @endphp

            <div class="row">
                @if(isset($trends['emerging_topics']) && count($trends['emerging_topics']) > 0)
                <div class="col-md-6 mb-3">
                    <div class="card bg-success bg-opacity-10">
                        <div class="card-body">
                            <h6 class="text-success">ახალი ტრენდები</h6>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($trends['emerging_topics'] as $topic)
                                    <span class="badge bg-success">{{ $topic }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                @if(isset($trends['declining_interests']) && count($trends['declining_interests']) > 0)
                <div class="col-md-6 mb-3">
                    <div class="card bg-warning bg-opacity-10">
                        <div class="card-body">
                            <h6 class="text-warning">კლებადი ინტერესი</h6>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($trends['declining_interests'] as $topic)
                                    <span class="badge bg-warning">{{ $topic }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            @if(isset($trends['viral_content_patterns']))
            <div class="alert alert-primary">
                <strong>ვირუსული კონტენტის პატერნები:</strong> {{ $trends['viral_content_patterns'] }}
            </div>
            @endif
        </div>
    </div>
    @endif

</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Feather icons handled by template
});
</script>
@endpush

@endfragment
@endsection
