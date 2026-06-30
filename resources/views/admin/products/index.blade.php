@extends('admin.layout')

@section('title', 'Products — Admin')

@section('content')
@fragment('content')
<div data-page-title="პროდუქტები">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div>
            <h4 class="mb-3 mb-md-0">პროდუქტები</h4>
        </div>
        <div>
            <a href="{{ route('admin.products.create') }}" class="btn btn-primary btn-sm d-flex align-items-center gap-1" data-pjax>
                <i data-feather="plus" style="width:16px;height:16px;"></i> პროდუქტის დამატება
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="productsTable" class="table table-hover table-sm w-100">
                    <thead>
                        <tr>
                            <th style="width:60px;">სურათი</th>
                            <th>სახელი</th>
                            <th>ფასი</th>
                            <th>ვარიანტები</th>
                            <th>სელექციური სიაში</th>
                            <th>მარაგი</th>
                            <th>სტატუსი</th>
                            <th style="width:100px;">მოქმედებები</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($products as $product)
                        <tr>
                            <td>
                                @if($product->primaryImage)
                                    <img src="{{ $product->primaryImage->thumbnail_url }}" alt="{{ $product->name_en }}" class="rounded cursor-pointer product-gallery-trigger" style="width:40px;height:40px;object-fit:cover;" data-product-id="{{ $product->getRouteKey() }}">
                                @else
                                    <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                                        <i data-feather="image" style="width:18px;height:18px;" class="text-muted"></i>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.products.edit', $product) }}" class="fw-bold text-decoration-none product-name-ka" data-pjax>{{ $product->name_ka ?: $product->name_en }}</a>
                                @if($product->featured)
                                    <span class="badge bg-warning text-dark ms-1" style="font-size:10px;">რჩეული</span>
                                @endif
                            </td>
                            <td>
                                @if($product->sale_price)
                                    <span class="text-danger fw-bold">GEL {{ number_format($product->sale_price, 2) }}</span>
                                    <br><small class="text-muted text-decoration-line-through">GEL {{ number_format($product->price, 2) }}</small>
                                @else
                                    <span class="fw-bold">GEL {{ number_format($product->price, 2) }}</span>
                                @endif
                            </td>
                            <td>{{ $product->variants_count }}</td>
                            <td>
                                @if(($product->listed_variants_count ?? 0) > 0)
                                    <span class="badge bg-primary">{{ $product->listed_variants_count }}</span>
                                @else
                                    <span class="badge bg-light text-muted border">0</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $totalStock = $product->variants->sum('quantity');
                                    $lowStock = $product->variants->filter(fn($v) => $v->isLowStock() && !$v->isOutOfStock())->count();
                                    $outOfStock = $product->variants->filter(fn($v) => $v->isOutOfStock())->count();
                                @endphp
                                <span class="fw-bold {{ $totalStock <= 0 ? 'text-danger' : ($lowStock > 0 ? 'text-warning' : 'text-success') }}">
                                    {{ $totalStock }}
                                </span>
                                @if($outOfStock > 0)
                                    <span class="badge bg-danger ms-1" style="font-size:10px;">{{ $outOfStock }} OOS</span>
                                @elseif($lowStock > 0)
                                    <span class="badge bg-warning text-dark ms-1" style="font-size:10px;">{{ $lowStock }} ცოტაა</span>
                                @endif
                            </td>
                            <td>
                                @if($product->is_active)
                                    <span class="badge bg-success">აქტიური</span>
                                @else
                                    <span class="badge bg-secondary">არააქტიური</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-outline-primary btn-sm p-1" data-pjax title="რედაქტირება">
                                        <i data-feather="edit-2" style="width:14px;height:14px;"></i>
                                    </a>
                                    <button type="button" class="btn btn-outline-danger btn-sm p-1 btn-delete-product"
                                            data-url="{{ route('admin.products.destroy', $product) }}"
                                            data-name="{{ $product->name_en }}"
                                            title="წაშლა">
                                        <i data-feather="trash-2" style="width:14px;height:14px;"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endfragment
@endsection
