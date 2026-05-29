@extends('admin.layout')

@section('title', 'Start Product Quality Research — Admin')

@section('content')
@fragment('content')
<div data-page-title="Start Product Quality Research">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div>
            <h4 class="mb-1">Start Product Quality Research</h4>
            <p class="text-muted mb-0">Create a catalog-linked or ad-hoc research target, then queue evidence ingestion and analysis.</p>
        </div>
        <div>
            <a href="{{ route('admin.product-quality.index') }}" class="btn btn-outline-secondary btn-sm" data-pjax>Back to Research</a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.product-quality.store') }}">
        @csrf

        <div class="card mb-3">
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label d-block">Mode</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="mode" id="mode_catalog" value="catalog" {{ old('mode', 'catalog') === 'catalog' ? 'checked' : '' }}>
                        <label class="form-check-label" for="mode_catalog">Catalog mode</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="mode" id="mode_ad_hoc" value="ad_hoc" {{ old('mode') === 'ad_hoc' ? 'checked' : '' }}>
                        <label class="form-check-label" for="mode_ad_hoc">Ad-hoc mode</label>
                    </div>
                </div>

                <div id="catalog-mode-fields" class="row g-3">
                    <div class="col-12">
                        <label for="catalog_product_id" class="form-label">Catalog Product</label>
                        <select class="form-select @error('product_id') is-invalid @enderror" id="catalog_product_id" name="product_id">
                            <option value="">Choose a product...</option>
                            @foreach($products as $product)
                                <option
                                    value="{{ $product->id }}"
                                    data-source-url="{{ $product->external_source_url }}"
                                    data-external-source="{{ $product->external_source }}"
                                    data-external-product-id="{{ $product->external_product_id }}"
                                    {{ (string) old('product_id') === (string) $product->id ? 'selected' : '' }}
                                >
                                    {{ $product->name_ka ?: $product->name_en }}{{ $product->brand || $product->model ? ' — ' . trim(($product->brand ?? '') . ' ' . ($product->model ?? '')) : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('product_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div id="ad-hoc-mode-fields" class="row g-3 d-none">
                    <div class="col-md-4">
                        <label for="name" class="form-label">Product Name</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" placeholder="Wonlex KT20 4G">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="brand" class="form-label">Brand</label>
                        <input type="text" class="form-control @error('brand') is-invalid @enderror" id="brand" name="brand" value="{{ old('brand') }}" placeholder="Wonlex">
                        @error('brand')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="model" class="form-label">Model</label>
                        <input type="text" class="form-control @error('model') is-invalid @enderror" id="model" name="model" value="{{ old('model') }}" placeholder="KT20">
                        @error('model')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h6 class="card-title">Evidence Source Inputs</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="source_url" class="form-label">Source URL</label>
                        <input type="url" class="form-control @error('source_url') is-invalid @enderror" id="source_url" name="source_url" value="{{ old('source_url') }}" placeholder="https://www.alibaba.com/product-detail/...">
                        @error('source_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label for="external_source" class="form-label">External Source</label>
                        <input type="text" class="form-control @error('external_source') is-invalid @enderror" id="external_source" name="external_source" value="{{ old('external_source') }}" placeholder="alibaba">
                        @error('external_source')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label for="external_product_id" class="form-label">External Product ID</label>
                        <input type="text" class="form-control @error('external_product_id') is-invalid @enderror" id="external_product_id" name="external_product_id" value="{{ old('external_product_id') }}">
                        @error('external_product_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label for="manual_evidence_input" class="form-label">Manual Evidence Input</label>
                        <textarea class="form-control @error('manual_evidence_input') is-invalid @enderror" id="manual_evidence_input" name="manual_evidence_input" rows="8" placeholder='JSON array of evidence items, or paste text blocks separated by blank lines'>{{ old('manual_evidence_input') }}</textarea>
                        <div class="form-text">Best fallback when live scraping is unavailable. Supports JSON arrays or plain text blocks.</div>
                        @error('manual_evidence_input')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label for="apify_json" class="form-label">Raw Apify JSON</label>
                        <textarea class="form-control @error('apify_json') is-invalid @enderror" id="apify_json" name="apify_json" rows="8" placeholder='Optional raw Apify payload JSON with review/comment fields'>{{ old('apify_json') }}</textarea>
                        @error('apify_json')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('admin.product-quality.index') }}" class="btn btn-outline-secondary" data-pjax>Cancel</a>
            <button type="submit" class="btn btn-primary">Queue Research</button>
        </div>
    </form>
</div>
@endfragment
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    window.AdminProductQuality && window.AdminProductQuality.initCreate();
});
</script>
@endpush
