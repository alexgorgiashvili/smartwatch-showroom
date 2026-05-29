@extends('admin.layout')

@section('title', 'SEO მონიტორინგი — Admin')

@section('content')
@fragment('content')
<div data-page-title="SEO მონიტორინგი">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div><h4 class="mb-3 mb-md-0">SEO მონიტორინგი და ოპტიმიზაცია</h4></div>
    </div>

    <!-- SEO Health Stats -->
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="text-muted small">სულ პროდუქტები</div>
                    <div class="h3 mb-0 mt-1">{{ number_format($stats['total_products']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card {{ $stats['meta_percentage'] >= 80 ? 'border-success' : 'border-warning' }}">
                <div class="card-body text-center">
                    <div class="text-muted small">Meta Tags დაფარვა</div>
                    <div class="h3 mb-0 mt-1 {{ $stats['meta_percentage'] >= 80 ? 'text-success' : 'text-warning' }}">
                        {{ $stats['meta_percentage'] }}%
                    </div>
                    <div class="text-muted small">{{ $stats['products_with_meta'] }}/{{ $stats['total_products'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card {{ $stats['images_percentage'] >= 90 ? 'border-success' : 'border-warning' }}">
                <div class="card-body text-center">
                    <div class="text-muted small">სურათების დაფარვა</div>
                    <div class="h3 mb-0 mt-1 {{ $stats['images_percentage'] >= 90 ? 'text-success' : 'text-warning' }}">
                        {{ $stats['images_percentage'] }}%
                    </div>
                    <div class="text-muted small">{{ $stats['products_with_images'] }}/{{ $stats['total_products'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <div class="text-muted small">სტატიები Meta-ით</div>
                    <div class="h3 mb-0 mt-1 text-info">{{ $stats['total_articles'] }}</div>
                    <div class="text-muted small">{{ $stats['articles_with_meta'] }} meta-ით</div>
                </div>
            </div>
        </div>
    </div>

    <!-- SEO Recommendations -->
    @if(!empty($recommendations))
    <div class="card mb-3">
        <div class="card-header bg-light">
            <h6 class="mb-0">🎯 რეკომენდაციები</h6>
        </div>
        <div class="card-body">
            @foreach($recommendations as $rec)
            <div class="d-flex gap-3 p-3 mb-2 rounded {{ $rec['priority'] === 'high' ? 'bg-danger-subtle' : 'bg-warning-subtle' }}">
                <div class="flex-shrink-0">
                    <span class="badge {{ $rec['priority'] === 'high' ? 'bg-danger' : 'bg-warning' }}">
                        {{ $rec['priority'] === 'high' ? 'მაღალი' : 'საშუალო' }}
                    </span>
                </div>
                <div class="flex-grow-1">
                    <h6 class="mb-1">{{ $rec['title'] }}</h6>
                    <p class="mb-1 small">{{ $rec['description'] }}</p>
                    <button class="btn btn-sm btn-outline-primary">→ {{ $rec['action'] }}</button>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="row">
        <!-- Products Missing Meta -->
        <div class="col-md-6 mb-3">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0">პროდუქტები Meta Tags გარეშე (ტოპ 10)</h6>
                </div>
                <div class="card-body">
                    @if(empty($productsMissingMeta))
                        <div class="text-center text-success py-3">
                            <i data-feather="check-circle" style="width:48px;height:48px;"></i>
                            <p class="mt-2">ყველა პროდუქტს აქვს meta tags! 🎉</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th class="small">პროდუქტი</th>
                                        <th class="small">პრობლემა</th>
                                        <th class="small" style="width:80px;">მოქმედება</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($productsMissingMeta as $product)
                                    <tr>
                                        <td class="small">{{ Str::limit($product['name'], 40) }}</td>
                                        <td class="small">
                                            @if($product['missing_title'])
                                                <span class="badge bg-danger-subtle text-danger border me-1">Title</span>
                                            @endif
                                            @if($product['missing_description'])
                                                <span class="badge bg-danger-subtle text-danger border">Description</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.products.edit', $product['slug']) }}" class="btn btn-sm btn-outline-primary p-1">
                                                <i data-feather="edit-2" style="width:14px;height:14px;"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Products Missing Images -->
        <div class="col-md-6 mb-3">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0">პროდუქტები სურათების გარეშე (ტოპ 10)</h6>
                </div>
                <div class="card-body">
                    @if(empty($productsMissingImages))
                        <div class="text-center text-success py-3">
                            <i data-feather="check-circle" style="width:48px;height:48px;"></i>
                            <p class="mt-2">ყველა პროდუქტს აქვს სურათები! 🎉</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th class="small">პროდუქტი</th>
                                        <th class="small" style="width:80px;">მოქმედება</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($productsMissingImages as $product)
                                    <tr>
                                        <td class="small">{{ Str::limit($product['name'], 50) }}</td>
                                        <td>
                                            <a href="{{ route('admin.products.edit', $product['slug']) }}" class="btn btn-sm btn-outline-primary p-1">
                                                <i data-feather="edit-2" style="width:14px;height:14px;"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- SEO Tips -->
    <div class="card">
        <div class="card-header bg-light">
            <h6 class="mb-0">💡 SEO რჩევები</h6>
        </div>
        <div class="card-body">
            <ul class="mb-0 small">
                <li class="mb-2"><strong>Meta Title:</strong> 50-60 სიმბოლო, უნიკალური ყველა გვერდისთვის</li>
                <li class="mb-2"><strong>Meta Description:</strong> 150-160 სიმბოლო, მოიცავს ძირითად საკვანძო სიტყვებს</li>
                <li class="mb-2"><strong>სურათების Alt Tags:</strong> აღწერითი ტექსტი ყველა სურათისთვის</li>
                <li class="mb-2"><strong>URL Structure:</strong> მოკლე, აღწერითი, საკვანძო სიტყვებით</li>
                <li><strong>Schema Markup:</strong> დაამატეთ Product schema პროდუქტების გვერდებზე</li>
            </ul>
        </div>
    </div>
</div>
@endfragment
@endsection
