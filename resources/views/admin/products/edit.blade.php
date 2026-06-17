@extends('admin.layout')

@section('title', 'Edit: ' . $product->name_en . ' — Admin')

@section('content')
@fragment('content')
<div data-page-title="Edit Product">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div>
            <h4 class="mb-3 mb-md-0">Edit: {{ $product->name_en }}</h4>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary btn-sm" data-pjax>
                <i data-feather="arrow-left" style="width:14px;height:14px;"></i> Back
            </a>
            <button type="button" class="btn btn-outline-danger btn-sm btn-delete-product"
                    data-url="{{ route('admin.products.destroy', $product) }}"
                    data-name="{{ $product->name_en }}">
                <i data-feather="trash-2" style="width:14px;height:14px;"></i> Delete
            </button>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form id="productForm" method="POST" action="{{ route('admin.products.update', $product) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        @include('admin.products._form', ['product' => $product])

        <div class="d-flex justify-content-end gap-2 mt-3">
            <button type="submit" class="btn btn-primary">
                <i data-feather="save" style="width:16px;height:16px;"></i> Update Product
            </button>
        </div>
    </form>

    {{-- ── Variants Section ── --}}
    <div class="card mt-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="card-title mb-0">Variants</h6>
                <button type="button" class="btn btn-primary btn-sm" id="btnAddVariant">
                    <i data-feather="plus" style="width:14px;height:14px;"></i> Add Variant
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0" id="variantsTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Color</th>
                            <th>Available</th>
                            <th>Local Qty</th>
                            <th>Bridge Qty</th>
                            <th>Low Stock Threshold</th>
                            <th>Status</th>
                            <th>Bridge Sync</th>
                            <th style="width:120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($product->variants as $variant)
                        <tr data-variant-id="{{ $variant->id }}">
                            <td class="fw-bold">{{ $variant->name }}</td>
                            <td>
                                @if($variant->hasColor())
                                    <span class="d-inline-flex align-items-center gap-1">
                                        <span style="width:14px;height:14px;border-radius:50%;background:{{ $variant->color_hex }};display:inline-block;border:1px solid #dee2e6;"></span>
                                        {{ $variant->color_name }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $variant->available_quantity }}</td>
                            <td>{{ $variant->quantity }}</td>
                            <td>{{ $variant->bridge_stock_quantity ?? '—' }}</td>
                            <td>{{ $variant->low_stock_threshold }}</td>
                            <td>
                                @if($variant->isOutOfStock())
                                    <span class="badge bg-danger">Out of Stock</span>
                                @elseif($variant->isLowStock())
                                    <span class="badge bg-warning text-dark">Low Stock</span>
                                @else
                                    <span class="badge bg-success">In Stock</span>
                                @endif
                            </td>
                            <td>
                                <div class="small">
                                    <div>{{ $variant->stock_sync_status ?: '—' }}</div>
                                    @if($variant->bridge_variation_id)
                                        <div class="text-muted">Var #{{ $variant->bridge_variation_id }}</div>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-outline-primary btn-sm p-1 btn-edit-variant"
                                            data-variant='@json($variant)'
                                            title="Edit">
                                        <i data-feather="edit-2" style="width:14px;height:14px;"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-info btn-sm p-1 btn-adjust-stock"
                                            data-variant-id="{{ $variant->id }}"
                                            data-variant-name="{{ $variant->name }}"
                                            title="Adjust Stock">
                                        <i data-feather="package" style="width:14px;height:14px;"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm p-1 btn-delete-variant"
                                            data-url="{{ route('admin.products.variants.delete', $variant) }}"
                                            data-name="{{ $variant->name }}"
                                            title="Delete">
                                        <i data-feather="trash-2" style="width:14px;height:14px;"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr id="noVariantsRow"><td colspan="9" class="text-center text-muted py-3">No variants yet. Add one above.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── Images Section ── --}}
    <div class="card mt-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="card-title mb-0">Images</h6>
                <form id="imageUploadForm" method="POST" action="{{ route('admin.products.images.store', $product) }}" enctype="multipart/form-data" class="d-inline">
                    @csrf
                    <label class="btn btn-primary btn-sm mb-0" style="cursor:pointer;">
                        <i data-feather="upload" style="width:14px;height:14px;"></i> Upload
                        <input type="file" name="images[]" multiple accept="image/*" class="d-none" id="imageFileInput">
                    </label>
                </form>
            </div>
            <div class="row g-3" id="imagesGrid">
                @forelse($product->images as $image)
                <div class="col-6 col-md-3 col-lg-2" data-image-id="{{ $image->id }}">
                    <div class="card h-100 {{ $image->is_primary ? 'border-primary' : '' }}">
                        <img src="{{ $image->thumbnail_url }}" class="card-img-top" alt="{{ $image->alt }}" style="height:120px;object-fit:cover;">
                        <div class="card-body p-2 text-center">
                            @if($image->is_primary)
                                <span class="badge bg-primary mb-1">Primary</span>
                            @else
                                <button type="button" class="btn btn-outline-primary btn-sm p-1 mb-1 btn-set-primary"
                                        data-url="{{ route('admin.products.images.primary', [$product, $image]) }}"
                                        title="Set as Primary">
                                    <i data-feather="star" style="width:12px;height:12px;"></i>
                                </button>
                            @endif
                            <button type="button" class="btn btn-outline-danger btn-sm p-1 mb-1 btn-delete-image"
                                    data-url="{{ route('admin.products.images.destroy', [$product, $image]) }}"
                                    title="Delete">
                                <i data-feather="trash-2" style="width:12px;height:12px;"></i>
                            </button>
                        </div>
                    </div>
                </div>
                @empty
                <div class="col-12" id="noImagesMsg">
                    <p class="text-center text-muted py-3 mb-0">No images uploaded yet.</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- Product data for JS --}}
@php
    $productEditConfig = [
        'id' => $product->id,
        'slug' => $product->slug,
        'storeVariantUrl' => route('admin.products.variants.store', $product),
    ];
@endphp
<script id="product-data" type="application/json">{!! json_encode($productEditConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

@include('admin.partials._image_manager_modal')

@endfragment
@endsection
