@extends('admin.layout')

@section('title', 'New Product')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-0">Create Product</h3>
            <p class="text-muted">Add a new MyTechnic product to the catalog.</p>
        </div>
        <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">Back to Products</a>
    </div>

    <div class="card">
        <div class="card-body">
            <div id="async-message"></div>
            <form method="POST" action="{{ route('admin.products.store') }}" id="product-form" data-async="true" enctype="multipart/form-data">
                @csrf
                @include('admin.products._form', ['product' => $product])

                <!-- Variants Section -->
                <div class="border-top pt-4 mt-4">
                    <h5 class="mb-3">Variants</h5>
                    <p class="text-muted small mb-3">Add different variants (size/color combinations) with stock quantities. You can also add more variants after creating the product.</p>
                    <p class="text-info small"><i class="bi bi-info-circle"></i> Variants can be managed after creating the product.</p>
                </div>

                <div class="border-top pt-4 mt-4">
                    <h5 class="mb-3">Initial Images</h5>
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <label for="images" class="form-label">Upload Images (max 8)</label>
                            <input type="file" name="images[]" id="images" class="form-control" multiple accept="image/*">
                            <div class="invalid-feedback d-block" data-error-for="images"></div>
                        </div>
                        <div class="col-lg-3">
                            <label for="alt_en" class="form-label">Alt Text (EN)</label>
                            <input type="text" name="alt_en" id="alt_en" class="form-control" value="{{ old('alt_en') }}">
                            <div class="invalid-feedback" data-error-for="alt_en"></div>
                        </div>
                        <div class="col-lg-3">
                            <label for="alt_ka" class="form-label">Alt Text (KA)</label>
                            <input type="text" name="alt_ka" id="alt_ka" class="form-control" value="{{ old('alt_ka') }}">
                            <div class="invalid-feedback" data-error-for="alt_ka"></div>
                        </div>
                    </div>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save Product</button>
                    <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    @include('admin.products._async')
@endpush
