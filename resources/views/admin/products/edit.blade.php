@extends('admin.layout')

@section('title', 'Edit Product')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-0">Edit Product</h3>
            <p class="text-muted">Update details and images.</p>
        </div>
        <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">Back to Products</a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div id="async-message"></div>
            <form method="POST" action="{{ route('admin.products.update', $product) }}" id="product-form" data-async="true">
                @csrf
                @method('PUT')
                @include('admin.products._form', ['product' => $product])
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Update Product</button>
                    <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    @include('admin.products._variants', ['product' => $product])

    @include('admin.products._images', ['product' => $product])
@endsection

@push('scripts')
    @include('admin.products._async')
@endpush
